<?php
/**
 * How one provider names things.
 *
 * The canonical vocabulary — what a pet HAS — lives in entities.json. A
 * provider map is the only place that knows what a given platform CALLS those
 * things. Adding a platform is adding a file to config/providers/, not editing
 * the entity.
 *
 * That separation is what #33 was about: before it, the entity carried
 * Petstablished's spelling in `api_key`, `taxonomy_source_map` and
 * `api_shapes`, so "what a pet is" and "what one vendor calls it" were the same
 * document.
 *
 * @package ShelterKit_Pets
 * @since   1.1.0
 */

declare( strict_types = 1 );

namespace Petsync\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Provider_Map {

	/**
	 * Loaded maps, by slug. Per-request; these come from JSON on disk.
	 *
	 * @var array<string, array<string, mixed>|null>
	 */
	private static array $cache = array();

	/**
	 * The provider a pet came from, or '' for hand-entered.
	 *
	 * @param int $post_id Pet post ID.
	 */
	public static function for_pet( int $post_id ): string {
		$config = Config::get_path( 'entities', 'entities.vcps_pet', array() );
		$prefix = $config['meta_prefix'] ?? '_pet_';

		return (string) get_post_meta( $post_id, $prefix . 'provider', true );
	}

	/**
	 * Load a provider map.
	 *
	 * A hand-entered pet has no provider and therefore no map — every value
	 * comes from post meta, so there is nothing to translate. Returns an empty
	 * map rather than null so callers need no special case.
	 *
	 * @param string $slug Provider slug.
	 * @return array<string, mixed>
	 */
	public static function get( string $slug ): array {
		if ( '' === $slug ) {
			return array();
		}

		if ( ! array_key_exists( $slug, self::$cache ) ) {
			// A slug reaches this from post meta, so it is not necessarily
			// something we wrote. Validate rather than sanitise: scrubbing bad
			// characters out would turn '../../entities' into 'entities' and
			// "petstablished\0" into a real provider, quietly resolving a
			// malformed slug to a map it did not name. An unrecognised slug has
			// no map, and that is the honest answer.
			$valid = (bool) preg_match( '/^[a-z0-9][a-z0-9-]*$/', $slug );
			$path  = PETSYNC_DIR . 'config/providers/' . $slug . '.json';

			$map = array();
			if ( $valid && is_readable( $path ) ) {
				$decoded = json_decode( (string) file_get_contents( $path ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_get_contents -- bundled config, same as Config::load().
				if ( is_array( $decoded ) ) {
					$map = $decoded;
				}
			}

			self::$cache[ $slug ] = $map;
		}

		return self::$cache[ $slug ] ?? array();
	}

	/**
	 * Canonical field name => the provider's key for it.
	 *
	 * @param string $slug Provider slug.
	 * @return array<string, string>
	 */
	public static function field_keys( string $slug ): array {
		$out = array();
		foreach ( (array) ( self::get( $slug )['fields'] ?? array() ) as $field => $cfg ) {
			if ( ! empty( $cfg['from'] ) && is_string( $cfg['from'] ) ) {
				$out[ $field ] = $cfg['from'];
			}
		}
		return $out;
	}

	/**
	 * The provider's key for one canonical field, or null if it does not carry
	 * that field at all.
	 *
	 * Null is meaningful: a field a provider does not have stays unmapped, so
	 * it hydrates to '' and the renderers omit the row. It must NOT become
	 * 'unknown', which both compatibility and health display as "Ask us" —
	 * asserting an assessment nobody made.
	 *
	 * @param string $slug  Provider slug.
	 * @param string $field Canonical field name.
	 */
	public static function key_for( string $slug, string $field ): ?string {
		return self::field_keys( $slug )[ $field ] ?? null;
	}

	/**
	 * Provider key => taxonomy slug.
	 *
	 * @param string $slug Provider slug.
	 * @return array<string, string>
	 */
	public static function taxonomies( string $slug ): array {
		return (array) ( self::get( $slug )['taxonomies'] ?? array() );
	}

	/**
	 * Response shapes an api_key cannot express — nested paths.
	 *
	 * @param string $slug Provider slug.
	 * @return array<string, mixed>
	 */
	public static function shapes( string $slug ): array {
		return (array) ( self::get( $slug )['shapes'] ?? array() );
	}

	/**
	 * Every provider with a map on disk.
	 *
	 * @return string[]
	 */
	public static function available(): array {
		$slugs = array();
		foreach ( glob( PETSYNC_DIR . 'config/providers/*.json' ) ?: array() as $file ) {
			$slugs[] = basename( $file, '.json' );
		}
		sort( $slugs );
		return $slugs;
	}

	/**
	 * Drop the per-request cache. Tests write provider files.
	 */
	public static function flush_cache(): void {
		self::$cache = array();
	}
}
