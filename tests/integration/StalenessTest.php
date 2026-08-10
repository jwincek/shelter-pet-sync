<?php
/**
 * Noticing when the provider goes dark.
 *
 * remove_stale_pets() only prunes when a fetch came back COMPLETE. That is the
 * right call — pruning on a partial feed drafts live pets sitting on an
 * unfetched page — but it means a provider that goes away entirely leaves every
 * pet published against a frozen snapshot with nothing raising a hand. The
 * site-wide petsync_last_sync option does not help: cron keeps running, so
 * "last sync" stays recent while the catalogue rots.
 *
 * @package ShelterKit_Pets
 */

declare( strict_types = 1 );

namespace Petsync\Tests\Integration;

use Petsync_Sync;

final class StalenessTest extends PetTestCase {

	/**
	 * @param int|null $seen_days_ago Null for a pet with no timestamp at all.
	 * @param bool     $provider      Whether it is a provider pet.
	 * @return int Post ID.
	 */
	private function make_pet_seen( ?int $seen_days_ago, bool $provider = true ): int {
		$id = $this->make_manual_pet();
		if ( $provider ) {
			update_post_meta( $id, '_pet_provider', Petsync_Sync::PROVIDER );
			update_post_meta( $id, '_pet_ps_id', (string) ( 9000 + $id ) );
		}
		if ( null !== $seen_days_ago ) {
			update_post_meta( $id, '_pet_last_seen', (string) ( time() - ( $seen_days_ago * DAY_IN_SECONDS ) ) );
		}
		return $id;
	}

	public function test_a_pet_absent_for_longer_than_the_threshold_is_stale(): void {
		$old   = $this->make_pet_seen( 30 );
		$fresh = $this->make_pet_seen( 1 );

		$stale = Petsync_Sync::get_stale_pets( 7 );

		$this->assertContains( $old, $stale );
		$this->assertNotContains( $fresh, $stale );
	}

	/**
	 * The failure that would make this feature hated: flagging every pet the
	 * moment it ships, because nothing had a timestamp yet. Migration 8 seeds
	 * them, and until then an absent timestamp must not count as stale.
	 */
	public function test_a_pet_with_no_timestamp_is_not_counted_stale(): void {
		$untimed = $this->make_pet_seen( null );

		$this->assertNotContains( $untimed, Petsync_Sync::get_stale_pets( 7 ) );
	}

	/**
	 * A hand-entered pet is never "seen" by a provider, so it can never be
	 * stale — otherwise a shelter with no platform would have its whole
	 * catalogue flagged.
	 */
	public function test_a_manual_pet_is_never_stale(): void {
		$manual = $this->make_pet_seen( 90, false );

		$this->assertNotContains( $manual, Petsync_Sync::get_stale_pets( 7 ) );
	}

	public function test_a_threshold_of_zero_disables_the_check(): void {
		$this->make_pet_seen( 365 );

		$this->assertSame( array(), Petsync_Sync::get_stale_pets( 0 ) );
	}

	public function test_drafting_takes_stale_pets_off_the_archive(): void {
		$old   = $this->make_pet_seen( 30 );
		$fresh = $this->make_pet_seen( 1 );

		$drafted = Petsync_Sync::draft_stale_pets( 7 );

		$this->assertSame( 1, $drafted );
		$this->assertSame( 'draft', get_post_status( $old ) );
		$this->assertSame( 'publish', get_post_status( $fresh ) );
	}

	/**
	 * Drafting is opt-in. The default must be off, because it partially
	 * reverses the incomplete-fetch protection and the cost of being wrong is a
	 * live animal vanishing from the site.
	 */
	public function test_auto_drafting_is_off_by_default(): void {
		$defaults = \Petsync_Admin::get_defaults();

		$this->assertSame( 0, $defaults['stale_draft_days'], 'auto-drafting must default to off' );
		$this->assertGreaterThan( 0, $defaults['stale_notice_days'], 'the warning should be on by default' );
	}

	/**
	 * A drafted pet stops being stale because get_stale_pets() only looks at
	 * published ones — so the notice count falls as the problem is dealt with,
	 * rather than nagging forever.
	 */
	public function test_a_drafted_pet_drops_out_of_the_stale_count(): void {
		$old = $this->make_pet_seen( 30 );
		$this->assertContains( $old, Petsync_Sync::get_stale_pets( 7 ) );

		wp_update_post(
			array(
				'ID'          => $old,
				'post_status' => 'draft',
			)
		);

		$this->assertNotContains( $old, Petsync_Sync::get_stale_pets( 7 ) );
	}

	public function test_migration_8_seeds_only_untimed_provider_pets(): void {
		$untimed = $this->make_pet_seen( null );
		$timed   = $this->make_pet_seen( 30 );
		$manual  = $this->make_pet_seen( null, false );
		$before  = get_post_meta( $timed, '_pet_last_seen', true );

		petsync_migrate_8_seed_last_seen();

		$this->assertNotSame( '', get_post_meta( $untimed, '_pet_last_seen', true ), 'an untimed provider pet must be seeded' );
		$this->assertSame( $before, get_post_meta( $timed, '_pet_last_seen', true ), 'an existing timestamp must not be overwritten' );
		$this->assertSame( '', get_post_meta( $manual, '_pet_last_seen', true ), 'a manual pet must not be seeded' );
	}
}
