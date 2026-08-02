<?php
/**
 * Abilities Provider — config-driven registration from abilities.json.
 *
 * Reads ability definitions from config/abilities.json and registers them
 * with the WordPress 6.9 Abilities API. Callbacks are resolved by convention:
 *
 *   'petsync/toggle-favorite' → Petsync\Abilities\Favorites\toggle()
 *   'petsync/list-pets'       → Petsync\Abilities\Pets\list_pets()
 *   'petsync/get-comparison'  → Petsync\Abilities\Comparison\get()
 *
 * @package Shelter_Pets
 * @since 1.0.0
 */

declare( strict_types = 1 );

namespace Petsync\Abilities;

use Petsync\Core\Config;

class Provider {

	/**
	 * Maps ability name prefixes to callback files and namespaces.
	 *
	 * The key is matched against the action part of the ability name
	 * (the part after the slash). If a keyword matches, that file is used.
	 *
	 * @var array<string, string>
	 */
	private static array $callback_files = [
		'pets'       => 'pets.php',
		'favorites'  => 'favorites.php',
		'comparison' => 'comparison.php',
		'stats'      => 'stats.php',
		'gallery'    => 'gallery.php',
	];

	/**
	 * Maps ability names to their callback file group.
	 *
	 * Explicit mapping for abilities that can't be auto-detected.
	 *
	 * @var array<string, string>
	 */
	private static array $ability_file_map = [
		'petsync/get-pet'            => 'pets',
		'petsync/list-pets'          => 'pets',
		'petsync/filter-pets'        => 'pets',
		'petsync/batch-get-pets'     => 'pets',
		'petsync/get-filter-options' => 'pets',
		'petsync/get-adoption-stats' => 'stats',
		'petsync/toggle-favorite'    => 'favorites',
		'petsync/get-favorites'      => 'favorites',
		'petsync/clear-favorites'    => 'favorites',
		'petsync/update-comparison'  => 'comparison',
		'petsync/get-comparison'     => 'comparison',
	];

	/**
	 * Register all abilities from config.
	 *
	 * @since 1.0.0
	 */
	public static function register(): void {
		$abilities = Config::get_item( 'abilities', 'abilities', [] );

		self::load_callback_files();

		foreach ( $abilities as $name => $config ) {
			self::register_ability( $name, $config );
		}
	}

	/**
	 * Load all callback files.
	 *
	 * @since 1.0.0
	 */
	private static function load_callback_files(): void {
		$base = PETSYNC_DIR . 'includes/abilities/';

		foreach ( self::$callback_files as $group => $file ) {
			$path = $base . $file;
			if ( file_exists( $path ) ) {
				require_once $path;
			}
		}
	}

	/**
	 * Register a single ability.
	 *
	 * @since 1.0.0
	 *
	 * @param string $name   Ability name (e.g. 'petsync/toggle-favorite').
	 * @param array  $config Ability configuration from JSON.
	 */
	private static function register_ability( string $name, array $config ): void {
		$execute_callback = self::resolve_callback( $name );
		if ( ! $execute_callback ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( sprintf( 'Petstablished: Missing callback for ability "%s"', $name ) );
			}
			return;
		}

		$permission_callback = self::resolve_permission( $config['permission'] ?? 'public' );

		// Ability label/description come from abilities.json (dynamic), so they
		// can't be wrapped in __() — the string extractor only sees literals,
		// and a non-literal __() call yields no POT entry anyway.
		$args = [
			'label'               => $config['label'] ?? $name,
			'description'         => $config['description'] ?? '',
			'category'            => $config['category'] ?? 'pets',
			'execute_callback'    => $execute_callback,
			'permission_callback' => $permission_callback,
		];

		if ( ! empty( $config['input_schema'] ) ) {
			$args['input_schema'] = $config['input_schema'];
		}

		if ( ! empty( $config['output_schema'] ) ) {
			$args['output_schema'] = $config['output_schema'];
		}

		if ( ! empty( $config['meta'] ) ) {
			$args['meta'] = $config['meta'];
		}

		wp_register_ability( $name, $args );
	}

	/**
	 * Resolve the execute callback for an ability by convention.
	 *
	 * Convention: 'petsync/toggle-favorite' →
	 *   1. Look up file group: 'favorites'
	 *   2. Derive function: 'toggle' (strip common prefixes like 'get-', 'list-', 'batch-get-')
	 *   3. Full callable: 'Petsync\Abilities\Favorites\toggle'
	 *
	 * Special cases are handled by explicit name-to-function mapping.
	 *
	 * @since 1.0.0
	 *
	 * @param string $name Ability name.
	 * @return callable|null
	 */
	private static function resolve_callback( string $name ): ?callable {
		// Explicit function mappings for non-obvious names.
		$explicit_map = [
			'petsync/get-pet'            => 'Petsync\\Abilities\\Pets\\get',
			'petsync/list-pets'          => 'Petsync\\Abilities\\Pets\\list_pets',
			'petsync/filter-pets'        => 'Petsync\\Abilities\\Pets\\filter_pets',
			'petsync/batch-get-pets'     => 'Petsync\\Abilities\\Pets\\batch_get',
			'petsync/get-filter-options' => 'Petsync\\Abilities\\Pets\\get_filter_options',
			'petsync/get-adoption-stats' => 'Petsync\\Abilities\\Stats\\get_adoption_stats',
			'petsync/toggle-favorite'    => 'Petsync\\Abilities\\Favorites\\toggle',
			'petsync/get-favorites'      => 'Petsync\\Abilities\\Favorites\\get_favorites',
			'petsync/clear-favorites'    => 'Petsync\\Abilities\\Favorites\\clear_all',
			'petsync/update-comparison'  => 'Petsync\\Abilities\\Comparison\\update',
			'petsync/get-comparison'     => 'Petsync\\Abilities\\Comparison\\get_comparison',
			'petsync/set-pet-gallery'    => 'Petsync\\Abilities\\Gallery\\set_gallery',
		];

		if ( isset( $explicit_map[ $name ] ) ) {
			$callable = $explicit_map[ $name ];
			if ( function_exists( $callable ) ) {
				return $callable;
			}
		}

		return null;
	}

	/**
	 * Resolve a permission string to a callback.
	 *
	 * @since 1.0.0
	 *
	 * @param string|callable $permission Permission type or callback.
	 * @return callable
	 */
	private static function resolve_permission( mixed $permission ): callable {
		if ( is_callable( $permission ) ) {
			return $permission;
		}

		if ( is_string( $permission ) ) {
			return match ( $permission ) {
				'public' => fn() => true,

				'logged_in' => fn() => is_user_logged_in(),

				// Anonymous users tracked via cookie/session — always allowed.
				// Favorites and comparison work for both logged-in and anonymous users.
				'public_with_session' => fn() => true,

				'admin', 'manage_options' => fn() => current_user_can( 'manage_options' ),

				'edit_posts' => fn() => current_user_can( 'edit_posts' ),

				default => fn() => current_user_can( $permission ),
			};
		}

		return fn() => true;
	}

	/**
	 * Get all registered ability names from config.
	 *
	 * @since 1.0.0
	 *
	 * @return string[]
	 */
	public static function get_ability_names(): array {
		return array_keys( Config::get_item( 'abilities', 'abilities', [] ) );
	}
}
