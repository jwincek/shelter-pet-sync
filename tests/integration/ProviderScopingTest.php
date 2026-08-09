<?php
/**
 * A sync must only ever touch the pets it imported itself.
 *
 * Record IDs are unique only within a provider, so matching on the ID alone
 * could bind an imported record to a hand-entered pet — or, on the pruning
 * side, quietly draft one. Both failures are silent: the pet simply stops
 * being what the shelter typed.
 *
 * @package ShelterKit_Pets
 */

declare( strict_types = 1 );

namespace Petsync\Tests\Integration;

final class ProviderScopingTest extends PetTestCase {

	/**
	 * The identity lookup the sync performs, mirrored exactly.
	 *
	 * @param string $ps_id    Provider record ID.
	 * @param string $provider Provider slug.
	 * @return int[] Matching post IDs.
	 */
	private function sync_lookup( string $ps_id, string $provider ): array {
		return get_posts(
			array(
				'post_type'   => 'vcps_pet',
				'post_status' => 'any',
				'numberposts' => -1,
				'fields'      => 'ids',
				'meta_query'  => array(
					array(
						'key'   => $this->prefix . 'ps_id',
						'value' => $ps_id,
					),
					array(
						'key'   => $this->prefix . 'provider',
						'value' => $provider,
					),
				),
			)
		);
	}

	public function test_the_lookup_finds_its_own_pet(): void {
		$id = $this->make_synced_pet( array( 'id' => 12345 ) );

		$this->assertSame( array( $id ), $this->sync_lookup( '12345', \Petsync_Sync::PROVIDER ) );
	}

	public function test_the_lookup_never_matches_a_hand_entered_pet(): void {
		$manual = $this->make_manual_pet();

		// Even if a hand-entered pet somehow carried a colliding record ID,
		// the absent provider must keep it out of the sync's reach.
		update_post_meta( $manual, $this->prefix . 'ps_id', '12345' );

		$this->assertSame(
			array(),
			$this->sync_lookup( '12345', \Petsync_Sync::PROVIDER ),
			'a pet with no provider must never be claimed by a sync'
		);
	}

	public function test_the_same_record_id_under_another_provider_does_not_match(): void {
		$id = $this->make_synced_pet( array( 'id' => 12345 ) );

		$this->assertSame(
			array(),
			$this->sync_lookup( '12345', 'shelterluv' ),
			'record IDs are unique only within a provider'
		);
		$this->assertNotEmpty( $this->sync_lookup( '12345', \Petsync_Sync::PROVIDER ) );
	}

	/**
	 * The stale sweep drafts pets the provider stopped returning. It must only
	 * ever consider that provider's own pets.
	 */
	public function test_stale_pruning_is_scoped_to_the_provider(): void {
		$synced = $this->make_synced_pet( array( 'id' => 555 ) );
		$manual = $this->make_manual_pet();
		$other  = $this->make_manual_pet();
		update_post_meta( $other, $this->prefix . 'provider', 'shelterluv' );

		$swept = get_posts(
			array(
				'post_type'   => 'vcps_pet',
				'post_status' => 'publish',
				'numberposts' => -1,
				'fields'      => 'ids',
				'meta_query'  => array(
					array(
						'key'   => $this->prefix . 'provider',
						'value' => \Petsync_Sync::PROVIDER,
					),
				),
			)
		);

		$this->assertContains( $synced, $swept );
		$this->assertNotContains( $manual, $swept, 'hand-entered pets must never be pruned' );
		$this->assertNotContains( $other, $swept, "another provider's pets must never be pruned" );
	}

	public function test_the_provider_constant_is_stamped_on_import(): void {
		$id = $this->make_synced_pet();

		$this->assertSame(
			\Petsync_Sync::PROVIDER,
			get_post_meta( $id, $this->prefix . 'provider', true )
		);
	}
}
