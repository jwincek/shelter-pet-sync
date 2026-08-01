<?php
/**
 * The stored-data migration rail.
 *
 * Migrations run once, against real installs, and a mistake is not undoable.
 * Each must be idempotent and correctly scoped — over-reaching is the failure
 * that matters, because relabelling a pet nobody asked to relabel is worse
 * than leaving it alone.
 *
 * @package Petstablished_Sync
 */

declare( strict_types = 1 );

namespace Petstablished\Tests\Integration;

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
			\Petstablished_Sync::PROVIDER,
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
}
