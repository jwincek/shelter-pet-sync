<?php
/**
 * Plugin Name: Shelter Pet Sync
 * Description: Sync adoptable pets from Petstablished with WordPress 6.9 Abilities API, Block Bindings, and Interactivity API.
 * Version: 1.0.0
 * Requires at least: 6.9
 * Requires PHP: 8.1
 * Author: Jerome Wincek
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: shelter-pet-sync
 *
 * @package Petstablished_Sync
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin constants.
define( 'PETSTABLISHED_SYNC_VERSION', '1.0.0' );
define( 'PETSTABLISHED_SYNC_FILE', __FILE__ );
define( 'PETSTABLISHED_SYNC_DIR', plugin_dir_path( __FILE__ ) );
define( 'PETSTABLISHED_SYNC_URL', plugin_dir_url( __FILE__ ) );

// Autoload classes.
spl_autoload_register(
	function ( string $class ): void {
		// Legacy classes: Petstablished_Foo → includes/class-petstablished-foo.php
		if ( str_starts_with( $class, 'Petstablished_' ) ) {
				$file = PETSTABLISHED_SYNC_DIR . 'includes/class-' . strtolower( str_replace( '_', '-', $class ) ) . '.php';
			if ( file_exists( $file ) ) {
				require_once $file;
				return;
			}
		}

		// Namespaced classes: Petstablished\Core\Config → includes/core/class-config.php
		if ( str_starts_with( $class, 'Petstablished\\' ) ) {
			$relative = substr( $class, strlen( 'Petstablished\\' ) );
			$parts    = explode( '\\', $relative );
			$name     = array_pop( $parts ); // Class name.
			$dir      = strtolower( implode( '/', $parts ) ); // Sub-directory.
			$file     = PETSTABLISHED_SYNC_DIR . 'includes/' . $dir . '/class-' . strtolower( str_replace( '_', '-', $name ) ) . '.php';

			if ( file_exists( $file ) ) {
				require_once $file;
				return;
			}
		}
	}
);

/**
 * Plugin activation — register CPTs early and flush rewrite rules.
 *
 * register_activation_hook must be called from the main plugin file,
 * not from inside a plugins_loaded callback. CPTs must be registered
 * before flush_rewrite_rules() or the /pets/ archive will 404.
 */
register_activation_hook(
	__FILE__,
	function (): void {
		// Initialize config so CPT_Registry can read post-types.json.
		\Petstablished\Core\Config::init( PETSTABLISHED_SYNC_DIR . 'config/' );

		// Register CPTs and taxonomies so WP knows about the rewrite rules.
		\Petstablished\Core\CPT_Registry::register_post_types();
		\Petstablished\Core\CPT_Registry::register_taxonomies();

		// Flush rewrite rules so /pets/ and taxonomy archives work immediately.
		flush_rewrite_rules();

		// Schedule cron sync. Route through the shared helper so the 6pm-anchor
		// and Sunday-skip semantics apply identically at activation and settings save.
		$settings = Petstablished_Admin::get_settings();
		if ( ! wp_next_scheduled( 'petsync_scheduled_sync' ) ) {
			Petstablished_Admin::reschedule_cron( $settings['auto_sync'], $settings['sync_interval'] );
		}
	}
);

/**
 * Plugin deactivation — clean up cron and flush rewrite rules.
 */
register_deactivation_hook(
	__FILE__,
	function (): void {
		wp_clear_scheduled_hook( 'petsync_scheduled_sync' );

		// Flush rewrite rules to remove our custom rules cleanly.
		flush_rewrite_rules();
	}
);

/**
 * Initialize the plugin.
 */
function petstablished_sync_init(): void {
	// Initialize config loader.
	\Petstablished\Core\Config::init( PETSTABLISHED_SYNC_DIR . 'config/' );

	// Config-driven CPT, taxonomy, and meta registration.
	\Petstablished\Core\CPT_Registry::init();

	// Apply compatibility filters (?compat_goodWithDogs=1 etc.) to the pet
	// archive / taxonomy main query. Compatibility data lives in the
	// pet_attribute taxonomy — it is NOT stored as post meta (the sync
	// keeps it in the _pet_api_response snapshot + attribute terms), so
	// this must be a tax_query. Mirrors Query::whereAttribute() and the
	// filter-pets ability so a no-JS request and the grid block agree.
	add_action(
		'pre_get_posts',
		function ( \WP_Query $query ): void {
			if ( is_admin() || ! $query->is_main_query() ) {
				return;
			}

			$entities   = \Petstablished\Core\Config::get_item( 'entities', 'entities', [] );
			$taxonomies = array_column( $entities['vcps_pet']['taxonomies'] ?? [], 'taxonomy' );

			if ( ! $query->is_post_type_archive( 'vcps_pet' ) && ! $query->is_tax( $taxonomies ) ) {
				return;
			}

			// camelCase URL key (compat_<key>) => pet_attribute term slug.
			// Mirrors \Petstablished\Abilities\Pets\COMPAT_MAP and the grid block's URL params.
			$compat_map    = [
				'goodWithDogs'   => 'good-with-dogs',
				'goodWithCats'   => 'good-with-cats',
				'goodWithKids'   => 'good-with-kids',
				'shotsCurrent'   => 'shots-current',
				'spayedNeutered' => 'spayed-neutered',
				'housebroken'    => 'housebroken',
				'specialNeeds'   => 'special-needs',
				'hypoallergenic' => 'hypoallergenic',
				'declawed'       => 'declawed',
			];
			$attribute_tax = $entities['vcps_pet']['attribute_taxonomy'] ?? 'pet_attribute';

			$compat_clauses = [];
			foreach ( $compat_map as $input_key => $term_slug ) {
				if ( ! empty( $_GET[ 'compat_' . $input_key ] ) ) {
					$compat_clauses[] = [
						'taxonomy' => $attribute_tax,
						'field'    => 'slug',
						'terms'    => $term_slug,
					];
				}
			}

			if ( empty( $compat_clauses ) ) {
				return;
			}

			if ( count( $compat_clauses ) > 1 ) {
				$compat_clauses['relation'] = 'AND';
			}

			// Combine with any pre-existing tax_query (e.g. an explicit one)
			// via AND. On a taxonomy archive the term constraint comes from
			// query vars and WordPress AND-merges it during parse_tax_query.
			$existing = $query->get( 'tax_query' ) ?: [];
			$query->set(
				'tax_query',
				$existing ? [
					'relation' => 'AND',
					$existing,
					$compat_clauses,
				] : $compat_clauses
			);
		}
	);

	// Template helpers — shared functions for block render callbacks.
	require_once PETSTABLISHED_SYNC_DIR . 'includes/template-helpers.php';

	// Core functionality.
	new Petstablished_Blocks();
	new Petstablished_Variations();
	new Petstablished_Templates();

	// Config-driven abilities registration (replaces old Petstablished_Abilities class).
	add_action(
		'wp_abilities_api_categories_init',
		function () {
			wp_register_ability_category(
				'pets',
				[
					'label'       => __( 'Pets', 'shelter-pet-sync' ),
					'description' => __( 'Pet adoption data operations.', 'shelter-pet-sync' ),
				]
			);
		}
	);
	add_action( 'wp_abilities_api_init', [ \Petstablished\Abilities\Provider::class, 'register' ] );

	// Plugin-scoped REST routes for client-side ability execution.
	// The core Abilities REST API at /wp-abilities/v1/ requires an authenticated
	// user for ALL endpoints. Favorites and comparison must work for anonymous
	// front-end visitors, so we register thin routes that delegate to the
	// abilities directly while respecting each ability's permission_callback.
	require_once PETSTABLISHED_SYNC_DIR . 'includes/class-petstablished-rest.php';
	add_action( 'rest_api_init', [ 'Petstablished_REST', 'register_routes' ] );

	// Admin & Sync (admin only).
	if ( is_admin() ) {
		new Petstablished_Admin();
	}
	new Petstablished_Sync();
}
add_action( 'plugins_loaded', 'petstablished_sync_init' );

/**
 * Stored-data schema version this build of the plugin expects.
 *
 * Bump this whenever stored data (options, post meta, cron hook names) changes
 * shape, and add the matching entry to petsync_get_migrations(). This is the
 * plugin's DATA version and is deliberately independent of the release version
 * in the plugin header — most releases change no stored data at all.
 */
define( 'PETSYNC_DB_VERSION', 2 );

/**
 * The ordered migration list.
 *
 * Keyed by the schema version each migration brings the install UP TO. They run
 * in key order, and only those above the installed version run. Every migration
 * must be idempotent anyway: a fresh install starts at 0 and runs the whole list,
 * so each one has to no-op cleanly when there is nothing to convert.
 *
 * @return array<int, callable> Migration callables keyed by target version.
 */
function petsync_get_migrations(): array {
	return array(
		1 => 'petsync_migrate_1_option_names',
		2 => 'petsync_migrate_2_provider_meta',
	);
}

/**
 * Run any migrations the installed schema version hasn't seen yet.
 *
 * Hooked late on `init` so the CPT (priority 10) and its registered meta
 * (priority 11) both exist before a migration touches pet records.
 */
function petsync_maybe_upgrade(): void {
	$installed = (int) get_option( 'petsync_db_version', 0 );

	if ( $installed >= PETSYNC_DB_VERSION ) {
		return;
	}

	foreach ( petsync_get_migrations() as $version => $callback ) {
		if ( $version > $installed && is_callable( $callback ) ) {
			call_user_func( $callback );
		}
	}

	update_option( 'petsync_db_version', PETSYNC_DB_VERSION, true );
}
add_action( 'init', 'petsync_maybe_upgrade', 20 );

/**
 * Migration 1 — legacy petstablished_* option and cron names to the neutral
 * petsync_* names (pre-multi-provider cleanup).
 *
 * Idempotent: returns early unless the old settings exist and the new ones
 * don't, then removes the old rows.
 */
function petsync_migrate_1_option_names(): void {
	if ( get_option( 'petsync_settings' ) !== false || get_option( 'petstablished_sync_settings' ) === false ) {
		return;
	}

	$map = array(
		'petstablished_sync_settings'   => 'petsync_settings',
		'petstablished_last_sync'       => 'petsync_last_sync',
		'petstablished_last_sync_stats' => 'petsync_last_sync_stats',
		'petstablished_sync_log'        => 'petsync_sync_log',
	);

	foreach ( $map as $old => $new ) {
		$value = get_option( $old, null );
		if ( null !== $value ) {
			add_option( $new, $value, '', 'petsync_sync_log' === $new ? false : true );
			delete_option( $old );
		}
	}

	// Move the scheduled sync to the renamed hook.
	wp_clear_scheduled_hook( 'petstablished_scheduled_sync' );
	$settings = Petstablished_Admin::get_settings();
	Petstablished_Admin::reschedule_cron( (bool) $settings['auto_sync'], $settings['sync_interval'] );
}

/**
 * Migration 2 — stamp the provider slug onto pets imported before the sync
 * became provider-aware.
 *
 * Those pets carry `_pet_ps_id` but no `_pet_provider`. The sync now matches on
 * the pair, so without this backfill it would fail to recognise them and
 * re-import every pet as a duplicate. Everything imported before this change
 * came from Petstablished, so that is the correct slug for all of them.
 *
 * Scoped to pets that actually carry a `_pet_ps_id`: that meta is the evidence a
 * pet was imported at all. Pets authored by hand in the editor have no provider
 * and must not be labelled with one, or a later sync would treat them as records
 * the API had forgotten.
 *
 * Idempotent: only selects pets missing the key, and no-ops on a fresh install.
 */
function petsync_migrate_2_provider_meta(): void {
	$pet_ids = get_posts(
		array(
			'post_type'        => 'vcps_pet',
			'post_status'      => 'any',
			'numberposts'      => -1,
			'fields'           => 'ids',
			'suppress_filters' => false,
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- one-time migration, not a request-path query.
			'meta_query'       => array(
				'relation' => 'AND',
				array(
					'key'     => '_pet_provider',
					'compare' => 'NOT EXISTS',
				),
				array(
					'key'     => '_pet_ps_id',
					'compare' => 'EXISTS',
				),
			),
		)
	);

	foreach ( $pet_ids as $pet_id ) {
		update_post_meta( $pet_id, '_pet_provider', Petstablished_Sync::PROVIDER );
	}
}
