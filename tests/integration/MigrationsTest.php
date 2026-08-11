<?php
/**
 * The stored-data migration rail.
 *
 * Migrations run once, against real installs, and a mistake is not undoable.
 * Each must be idempotent and correctly scoped — over-reaching is the failure
 * that matters, because relabelling a pet nobody asked to relabel is worse
 * than leaving it alone.
 *
 * @package ShelterKit_Pets
 */

declare( strict_types = 1 );

namespace Petsync\Tests\Integration;

final class MigrationsTest extends PetTestCase {

	public function test_the_rail_runs_every_declared_migration(): void {
		$migrations = petsync_get_migrations();

		$this->assertSame( range( 1, PETSYNC_DB_VERSION ), array_keys( $migrations ), 'migrations must be contiguous from 1' );

		foreach ( $migrations as $version => $callback ) {
			$this->assertTrue( is_callable( $callback ), "migration {$version} is not callable" );
		}
	}

	// ── Migration 2: provider backfill ───────────────────────────────────────

	public function test_migration_2_stamps_pets_that_were_imported(): void {
		$imported = $this->make_manual_pet();
		update_post_meta( $imported, $this->prefix . 'ps_id', '4242' );

		petsync_migrate_2_provider_meta();

		$this->assertSame(
			\Petsync_Sync::PROVIDER,
			get_post_meta( $imported, $this->prefix . 'provider', true )
		);
	}

	/**
	 * A record ID is the evidence a pet was imported. Without one it was typed
	 * by hand, and labelling it with a provenance it never had would put it
	 * inside a sync's reach.
	 */
	public function test_migration_2_leaves_hand_entered_pets_alone(): void {
		$manual = $this->make_manual_pet();

		petsync_migrate_2_provider_meta();

		$this->assertSame( '', get_post_meta( $manual, $this->prefix . 'provider', true ) );
	}

	public function test_migration_2_is_idempotent(): void {
		$id = $this->make_manual_pet();
		update_post_meta( $id, $this->prefix . 'ps_id', '4242' );

		petsync_migrate_2_provider_meta();
		$first = get_post_meta( $id, $this->prefix . 'provider', true );

		petsync_migrate_2_provider_meta();

		$this->assertSame( $first, get_post_meta( $id, $this->prefix . 'provider', true ) );
		$this->assertCount( 1, get_post_meta( $id, $this->prefix . 'provider' ), 'must not accumulate duplicate meta rows' );
	}

	// ── Migration 3: default status backfill ─────────────────────────────────

	public function test_migration_3_gives_statusless_hand_entered_pets_a_status(): void {
		$id = $this->make_manual_pet();
		wp_set_object_terms( $id, array(), 'pet_status' );

		petsync_migrate_3_default_status();

		$this->assertSame(
			array( 'available' ),
			wp_get_object_terms( $id, 'pet_status', array( 'fields' => 'slugs' ) )
		);
	}

	public function test_migration_3_never_relabels_a_pet_that_has_a_status(): void {
		$id = $this->make_manual_pet();
		wp_set_object_terms( $id, 'adopted', 'pet_status' );

		petsync_migrate_3_default_status();

		$this->assertSame(
			array( 'adopted' ),
			wp_get_object_terms( $id, 'pet_status', array( 'fields' => 'slugs' ) ),
			'an existing status is the shelter’s statement about the animal'
		);
	}

	/**
	 * An imported pet takes its status from the provider on the next sync.
	 * Guessing on its behalf could contradict the platform.
	 */
	public function test_migration_3_skips_imported_pets(): void {
		$id = $this->make_synced_pet();
		wp_set_object_terms( $id, array(), 'pet_status' );

		petsync_migrate_3_default_status();

		$this->assertSame( array(), wp_get_object_terms( $id, 'pet_status', array( 'fields' => 'slugs' ) ) );
	}

	public function test_migration_3_is_idempotent(): void {
		$id = $this->make_manual_pet();
		wp_set_object_terms( $id, array(), 'pet_status' );

		petsync_migrate_3_default_status();
		petsync_migrate_3_default_status();

		$this->assertSame(
			array( 'available' ),
			wp_get_object_terms( $id, 'pet_status', array( 'fields' => 'slugs' ) )
		);
	}

	public function test_migrations_no_op_on_a_fresh_install(): void {
		// No pets at all — every migration must run without error, because a
		// fresh install starts at version 0 and runs the whole list.
		foreach ( petsync_get_migrations() as $version => $callback ) {
			$callback();
			$this->assertTrue( true, "migration {$version} completed on an empty install" );
		}
	}

	// ── Migration 4: template namespace ──────────────────────────────────────

	/**
	 * File a customized template under a given wp_theme term, the way the Site
	 * Editor does.
	 *
	 * @param string $theme_name wp_theme term name.
	 * @param string $slug       Template slug.
	 * @return int Post ID.
	 */
	private function customize_template_under( string $theme_name, string $slug = 'single-vcps_pet' ): int {
		$post_id = wp_insert_post(
			array(
				'post_type'    => 'wp_template',
				'post_name'    => $slug,
				'post_title'   => $slug,
				'post_status'  => 'publish',
				'post_content' => '<!-- wp:paragraph --><p>customized</p><!-- /wp:paragraph -->',
			)
		);

		wp_set_object_terms( $post_id, $theme_name, 'wp_theme' );

		return (int) $post_id;
	}

	/**
	 * @param int $post_id Template post ID.
	 * @return string[] wp_theme term names on the post.
	 */
	private function theme_terms_of( int $post_id ): array {
		return wp_get_object_terms( $post_id, 'wp_theme', array( 'fields' => 'names' ) );
	}

	public function test_migration_4_carries_a_customization_across_a_rename(): void {
		$customized = $this->customize_template_under( 'shelter-pet-sync' );

		petsync_migrate_4_template_namespace();

		$this->assertSame(
			array( \Petsync_Templates::THEME_NAMESPACE ),
			$this->theme_terms_of( $customized ),
			'a customization filed under the old name must end up under the current one'
		);
		$this->assertFalse(
			get_term_by( 'name', 'shelter-pet-sync', 'wp_theme' ),
			'the legacy term should not survive'
		);
	}

	public function test_migration_4_handles_the_oldest_namespace_too(): void {
		$customized = $this->customize_template_under( 'vcpahumane-pet-sync' );

		petsync_migrate_4_template_namespace();

		$this->assertSame(
			array( \Petsync_Templates::THEME_NAMESPACE ),
			$this->theme_terms_of( $customized )
		);
	}

	/**
	 * The partially-migrated case: someone customized a template after the
	 * rename, so both terms hold real work. Neither side may be discarded.
	 */
	public function test_migration_4_merges_when_both_terms_hold_work(): void {
		$old = $this->customize_template_under( 'shelter-pet-sync', 'single-vcps_pet' );
		$new = $this->customize_template_under( \Petsync_Templates::THEME_NAMESPACE, 'archive-vcps_pet' );

		petsync_migrate_4_template_namespace();

		$this->assertSame( array( \Petsync_Templates::THEME_NAMESPACE ), $this->theme_terms_of( $old ) );
		$this->assertSame( array( \Petsync_Templates::THEME_NAMESPACE ), $this->theme_terms_of( $new ) );
		$this->assertNotNull( get_post( $old ), 'the older customization must survive the merge' );
		$this->assertNotNull( get_post( $new ), 'the newer customization must survive the merge' );
		$this->assertFalse( get_term_by( 'name', 'shelter-pet-sync', 'wp_theme' ) );
	}

	/**
	 * An install can be upgrading across BOTH renames at once — it may have sat
	 * on a version that predates them all.
	 */
	public function test_migration_4_consolidates_several_legacy_namespaces(): void {
		$oldest = $this->customize_template_under( 'vcpahumane-pet-sync', 'single-vcps_pet' );
		$older  = $this->customize_template_under( 'shelter-pet-sync', 'archive-vcps_pet' );

		petsync_migrate_4_template_namespace();

		$this->assertSame( array( \Petsync_Templates::THEME_NAMESPACE ), $this->theme_terms_of( $oldest ) );
		$this->assertSame( array( \Petsync_Templates::THEME_NAMESPACE ), $this->theme_terms_of( $older ) );

		foreach ( \Petsync_Templates::LEGACY_NAMESPACES as $legacy ) {
			$this->assertFalse( get_term_by( 'name', $legacy, 'wp_theme' ), "$legacy should be gone" );
		}
	}

	/**
	 * A customization of a template the plugin no longer ships is exactly the
	 * one a shelter would be upset to lose, so the migration moves a term's
	 * whole contents rather than filtering to slugs it recognises.
	 */
	public function test_migration_4_carries_templates_the_plugin_no_longer_ships(): void {
		$retired = $this->customize_template_under( 'shelter-pet-sync', 'some-retired-template' );

		petsync_migrate_4_template_namespace();

		$this->assertSame( array( \Petsync_Templates::THEME_NAMESPACE ), $this->theme_terms_of( $retired ) );
	}

	public function test_migration_4_is_idempotent(): void {
		$customized = $this->customize_template_under( 'shelter-pet-sync' );

		petsync_migrate_4_template_namespace();
		petsync_migrate_4_template_namespace();

		$this->assertSame( array( \Petsync_Templates::THEME_NAMESPACE ), $this->theme_terms_of( $customized ) );
	}

	public function test_migration_4_leaves_an_unrelated_theme_alone(): void {
		$theme_template = $this->customize_template_under( 'twentytwentyfive' );

		petsync_migrate_4_template_namespace();

		$this->assertSame(
			array( 'twentytwentyfive' ),
			$this->theme_terms_of( $theme_template ),
			'a real theme\'s own customizations are not ours to move'
		);
	}

	/**
	 * The migration exists because the lookup and the storage key drifted
	 * apart. Pinning them to the same constant is the fix; this asserts they
	 * cannot drift again.
	 */
	public function test_the_lookup_and_the_migration_agree_on_the_namespace(): void {
		$customized = $this->customize_template_under( 'shelter-pet-sync' );

		petsync_migrate_4_template_namespace();

		$found = get_posts(
			array(
				'post_type'      => 'wp_template',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- deliberately mirrors the front-end lookup this test is pinning.
				'tax_query'      => array(
					array(
						'taxonomy' => 'wp_theme',
						'field'    => 'name',
						'terms'    => \Petsync_Templates::THEME_NAMESPACE,
					),
				),
			)
		);

		$this->assertContains(
			$customized,
			$found,
			'after migrating, the template must be findable by the same query the front end uses'
		);
	}

	/**
	 * The realistic failure for the rename path: another wp_theme term already
	 * holds the target slug under a different name, so the lookup by name finds
	 * nothing but wp_update_term still rejects the slug.
	 *
	 * The slug is cosmetic — get_customized_template() matches on name — so the
	 * migration retries with the name alone and lets WordPress derive a unique
	 * slug. What must not happen is the rename failing silently and the install
	 * being marked as migrated anyway.
	 */
	public function test_migration_4_survives_a_slug_collision(): void {
		wp_insert_term(
			'Some Other Theme',
			'wp_theme',
			array( 'slug' => \Petsync_Templates::THEME_NAMESPACE )
		);

		$customized = $this->customize_template_under( 'shelter-pet-sync' );

		$this->assertTrue(
			petsync_migrate_4_template_namespace(),
			'a slug collision must not be reported as a failed migration'
		);

		$this->assertSame(
			array( \Petsync_Templates::THEME_NAMESPACE ),
			$this->theme_terms_of( $customized ),
			'the customization must still end up under the current namespace'
		);

		$term = get_term_by( 'name', \Petsync_Templates::THEME_NAMESPACE, 'wp_theme' );
		$this->assertInstanceOf( \WP_Term::class, $term );
		$this->assertNotSame(
			\Petsync_Templates::THEME_NAMESPACE,
			$term->slug,
			'WordPress should have derived a distinct slug, since the tidy one was taken'
		);
	}

	// ── The migration rail itself ────────────────────────────────────────────

	/**
	 * A migration that reports failure must not advance the stored version past
	 * itself, or the failure becomes permanent as well as silent — the install
	 * would be marked as migrated with the work never done.
	 */
	public function test_a_failing_migration_does_not_advance_the_version(): void {
		delete_option( 'petsync_db_version' );

		$ran = array();
		$rail = array(
			1 => function () use ( &$ran ) {
				$ran[] = 1;
				return true;
			},
			2 => function () use ( &$ran ) {
				$ran[] = 2;
				return false;
			},
			3 => function () use ( &$ran ) {
				$ran[] = 3;
				return true;
			},
		);

		// Mirror petsync_maybe_upgrade()'s loop against a controllable rail.
		$installed = 0;
		$completed = $installed;
		foreach ( $rail as $version => $callback ) {
			if ( $version <= $installed ) {
				continue;
			}
			if ( false === call_user_func( $callback ) ) {
				break;
			}
			$completed = $version;
		}

		$this->assertSame( array( 1, 2 ), $ran, 'the rail must stop at the failure, not carry on' );
		$this->assertSame( 1, $completed, 'only the migration that completed may be recorded' );
	}

	/**
	 * The real rail, end to end: every declared migration completes on a fresh
	 * install and the version lands on PETSYNC_DB_VERSION.
	 */
	public function test_the_rail_records_the_full_version_when_everything_succeeds(): void {
		delete_option( 'petsync_db_version' );

		petsync_maybe_upgrade();

		$this->assertSame(
			PETSYNC_DB_VERSION,
			(int) get_option( 'petsync_db_version' ),
			'a clean run must record the full schema version'
		);
	}
	// ── Migration 6: canonical field renames ─────────────────────────────────

	public function test_migration_6_carries_hand_entered_meta_to_the_new_key(): void {
		$id = $this->make_manual_pet();
		update_post_meta( $id, $this->prefix . 'siblings_names', 'Pepper, Sage' );
		update_post_meta( $id, $this->prefix . 'special_needs', 'yes' );

		$this->assertTrue( petsync_migrate_6_field_renames() );

		$this->assertSame( 'Pepper, Sage', get_post_meta( $id, $this->prefix . 'bonded_names', true ) );
		$this->assertSame( 'yes', get_post_meta( $id, $this->prefix . 'has_special_needs', true ) );
		$this->assertSame( '', get_post_meta( $id, $this->prefix . 'siblings_names', true ) );
		$this->assertSame( '', get_post_meta( $id, $this->prefix . 'special_needs', true ) );
	}

	/**
	 * special_needs_detail shares a prefix with special_needs and must not be
	 * swept up — it is a different field with a different meaning.
	 */
	public function test_migration_6_leaves_special_needs_detail_alone(): void {
		$id = $this->make_manual_pet();
		update_post_meta( $id, $this->prefix . 'special_needs_detail', 'FeLV+' );

		petsync_migrate_6_field_renames();

		$this->assertSame( 'FeLV+', get_post_meta( $id, $this->prefix . 'special_needs_detail', true ) );
	}

	/**
	 * Never clobber newer data with older. If someone has already entered a
	 * value under the new key, the stale row is dropped rather than applied.
	 */
	public function test_migration_6_does_not_overwrite_an_existing_value(): void {
		$id = $this->make_manual_pet();
		update_post_meta( $id, $this->prefix . 'special_needs', 'no' );
		update_post_meta( $id, $this->prefix . 'has_special_needs', 'yes' );

		petsync_migrate_6_field_renames();

		$this->assertSame( 'yes', get_post_meta( $id, $this->prefix . 'has_special_needs', true ) );
		$this->assertSame( '', get_post_meta( $id, $this->prefix . 'special_needs', true ) );
	}

	public function test_migration_6_is_idempotent(): void {
		$id = $this->make_manual_pet();
		update_post_meta( $id, $this->prefix . 'siblings_names', 'Pepper' );

		petsync_migrate_6_field_renames();
		petsync_migrate_6_field_renames();

		$this->assertSame( 'Pepper', get_post_meta( $id, $this->prefix . 'bonded_names', true ) );
		$this->assertCount( 1, get_post_meta( $id, $this->prefix . 'bonded_names' ) );
	}

	// ─── The rail runner itself ─────────────────────────────────────────────

	/**
	 * The rail is the riskiest part of an upgrade and it used to fire on
	 * whichever page load happened to be first — on a live site, a member of
	 * the public's. `wp shelterkit migrate` makes it deliberate, and both paths
	 * share this one implementation so they cannot drift.
	 */
	public function test_a_dry_run_reports_every_pending_migration_and_writes_nothing(): void {
		update_option( 'petsync_db_version', 0, true );

		$result = petsync_run_migrations( true );

		$this->assertSame( 0, $result['installed'] );
		$this->assertSame( PETSYNC_DB_VERSION, $result['target'] );
		$this->assertSame( array_keys( petsync_get_migrations() ), $result['ran'] );
		$this->assertNull( $result['failed'] );

		$this->assertSame(
			0,
			(int) get_option( 'petsync_db_version' ),
			'a dry run that advances the recorded version is not a dry run'
		);
	}

	public function test_a_real_run_advances_the_recorded_version(): void {
		update_option( 'petsync_db_version', 0, true );

		$result = petsync_run_migrations();

		$this->assertSame( PETSYNC_DB_VERSION, $result['completed'] );
		$this->assertSame( PETSYNC_DB_VERSION, (int) get_option( 'petsync_db_version' ) );
	}

	public function test_nothing_runs_when_already_up_to_date(): void {
		update_option( 'petsync_db_version', PETSYNC_DB_VERSION, true );

		$result = petsync_run_migrations();

		$this->assertSame( array(), $result['ran'] );
		$this->assertNull( $result['failed'] );
	}

	/**
	 * The rule the whole rail exists for: a failure records the completed steps
	 * and stops, so the failed one RETRIES rather than being skipped forever.
	 *
	 * Unreachable through the shipped migrations, which all succeed — so the
	 * rail is injected. Before this design the version advanced unconditionally,
	 * which made any migration failure both silent and permanent.
	 */
	public function test_a_failure_stops_the_rail_and_records_only_what_completed(): void {
		update_option( 'petsync_db_version', 0, true );

		$ran        = array();
		$migrations = array(
			1 => static function () use ( &$ran ) {
				$ran[] = 1;
			},
			2 => static function () use ( &$ran ) {
				$ran[] = 2;
				return false;
			},
			3 => static function () use ( &$ran ) {
				$ran[] = 3;
			},
		);

		$result = petsync_run_migrations( false, null, $migrations );

		$this->assertSame( array( 1, 2 ), $ran, 'the rail must stop at the failure, not carry on' );
		$this->assertSame( 2, $result['failed'] );
		$this->assertSame( array( 1 ), $result['ran'] );
		$this->assertSame( 1, $result['completed'] );
		$this->assertSame(
			1,
			(int) get_option( 'petsync_db_version' ),
			'recording 2 would skip the failed migration forever; recording 0 would re-run the one that worked'
		);
	}

	/**
	 * And the retry actually happens: running again re-attempts the failed one
	 * without repeating the completed one.
	 */
	public function test_the_failed_migration_is_retried_and_the_completed_one_is_not(): void {
		update_option( 'petsync_db_version', 0, true );

		$ran     = array();
		$fails   = true;
		$rail    = static function () use ( &$ran, &$fails ): array {
			return array(
				1 => static function () use ( &$ran ) {
					$ran[] = 1;
				},
				2 => static function () use ( &$ran, &$fails ) {
					$ran[] = 2;
					return $fails ? false : null;
				},
			);
		};

		petsync_run_migrations( false, null, $rail() );
		$this->assertSame( array( 1, 2 ), $ran );

		$fails = false;
		petsync_run_migrations( false, null, $rail() );

		$this->assertSame( array( 1, 2, 2 ), $ran, 'migration 1 must not run twice; migration 2 must be retried' );
		$this->assertSame( 2, (int) get_option( 'petsync_db_version' ) );
	}

	/**
	 * A dry run must not execute a migration, only name it.
	 */
	public function test_a_dry_run_does_not_call_the_migrations(): void {
		update_option( 'petsync_db_version', 0, true );

		$called = false;
		$result = petsync_run_migrations(
			true,
			null,
			array(
				1 => static function () use ( &$called ) {
					$called = true;
				},
			)
		);

		$this->assertFalse( $called, 'a dry run that calls the migration is not a dry run' );
		$this->assertSame( array( 1 ), $result['ran'] );
	}

	/**
	 * The reporter is what `wp shelterkit migrate` prints. An upgrade that
	 * reports nothing is the situation this command exists to end.
	 */
	public function test_the_reporter_is_called_for_every_migration(): void {
		update_option( 'petsync_db_version', 0, true );

		$events = array();
		petsync_run_migrations(
			false,
			static function ( int $version, string $event, float $seconds ) use ( &$events ): void {
				$events[] = array( $version, $event );
				// Timings are what tell an operator whether to worry.
				if ( 'done' === $event ) {
					self::assertGreaterThanOrEqual( 0.0, $seconds );
				}
			}
		);

		$expected = array_keys( petsync_get_migrations() );

		$this->assertSame( $expected, array_values( array_unique( array_column( $events, 0 ) ) ) );
		foreach ( $expected as $version ) {
			$this->assertContains( array( $version, 'start' ), $events, "migration $version never reported starting" );
			$this->assertContains( array( $version, 'done' ), $events, "migration $version never reported finishing" );
		}
	}

	/**
	 * Every migration on the rail needs a line explaining what it does, because
	 * the operator reading `--dry-run` on a live site is deciding whether to
	 * proceed. A version added without one is a blank line in that output.
	 */
	public function test_every_migration_has_an_operator_facing_description(): void {
		$described = ( new \ReflectionClass( \Petsync\CLI\Migrate::class ) )->getConstant( 'DESCRIPTIONS' );

		foreach ( array_keys( petsync_get_migrations() ) as $version ) {
			$this->assertArrayHasKey(
				$version,
				(array) $described,
				"migration $version has no description, so --dry-run would not say what it does"
			);
			$this->assertNotSame( '', trim( (string) $described[ $version ] ) );
		}
	}
}
