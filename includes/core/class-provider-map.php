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
	 * An entry is either a bare slug or an object carrying a value map:
	 *
	 *     "primary_breed": "pet_breed"
	 *     "sex": { "to": "pet_sex", "values": { "f": "Female", "m": "Male" } }
	 *
	 * Both forms normalise to the same thing here, so callers that only want to
	 * know where a key lands need no special case.
	 *
	 * @param string $slug Provider slug.
	 * @return array<string, string>
	 */
	public static function taxonomies( string $slug ): array {
		$out = array();
		foreach ( (array) ( self::get( $slug )['taxonomies'] ?? array() ) as $source => $cfg ) {
			if ( is_string( $cfg ) ) {
				$out[ $source ] = $cfg;
			} elseif ( is_array( $cfg ) && ! empty( $cfg['to'] ) && is_string( $cfg['to'] ) ) {
				$out[ $source ] = $cfg['to'];
			}
		}
		return $out;
	}

	/**
	 * The value map for one field or taxonomy source, if it declares one.
	 *
	 * Renaming a field is not enough for every provider. Adopt-a-Pet sends sex
	 * as 'f' / 'm', and the pet_sex taxonomy holds Female / Male — a difference
	 * an `api_key` cannot express. Putting the translation in config rather than
	 * reaching for a PHP callback is deliberate: "config could not express it"
	 * is exactly how the ten hardcoded provider keys in #33 got written.
	 *
	 * @param string $slug  Provider slug.
	 * @param string $field Canonical field name, or a taxonomy source key.
	 * @return array<string, string>
	 */
	public static function values( string $slug, string $field ): array {
		$map = self::get( $slug );

		$from_field = $map['fields'][ $field ]['values'] ?? null;
		if ( is_array( $from_field ) ) {
			return $from_field;
		}

		$from_taxonomy = $map['taxonomies'][ $field ]['values'] ?? null;
		return is_array( $from_taxonomy ) ? $from_taxonomy : array();
	}

	/**
	 * Translate one raw provider value through a value map.
	 *
	 * Matching is case- and whitespace-insensitive because Adopt-a-Pet
	 * aggregates from many upstream shelter systems and its own documentation
	 * warns of "inconsistent data formatting" between them.
	 *
	 * An unmatched value passes through unchanged rather than blanking. A
	 * surprise value then shows up as itself — visible, and correctable in the
	 * map — where blanking would silently discard real data and look identical
	 * to a field the provider never sent.
	 *
	 * @param array<string, string> $values Value map.
	 * @param mixed                 $raw    Raw provider value.
	 * @return mixed
	 */
	public static function apply_values( array $values, mixed $raw ): mixed {
		if ( array() === $values || ! is_scalar( $raw ) || is_bool( $raw ) ) {
			return $raw;
		}

		$needle = strtolower( trim( (string) $raw ) );
		foreach ( $values as $from => $to ) {
			if ( strtolower( trim( (string) $from ) ) === $needle ) {
				return $to;
			}
		}

		return $raw;
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
	 * The provider keys that drive the WP_Post itself rather than post meta:
	 * `title`, `content`, and `private_when` (a truthy value drafts the pet).
	 *
	 * These are not entity fields — post_title and post_content are columns, and
	 * the entity reads them back through the `description` computed field — so
	 * they cannot be expressed in `fields`. They were the last hardcoded
	 * provider keys in the sync.
	 *
	 * @param string $slug Provider slug.
	 * @return array<string, string>
	 */
	public static function post_keys( string $slug ): array {
		$out = array();
		foreach ( (array) ( self::get( $slug )['post'] ?? array() ) as $role => $key ) {
			if ( is_string( $key ) && '' !== $key ) {
				$out[ $role ] = $key;
			}
		}
		return $out;
	}

	/**
	 * Provider key => taxonomy that its value is APPENDED to, alongside whatever
	 * the primary source already set. Petstablished sends a secondary breed and
	 * colour; a provider with only one of each declares nothing here.
	 *
	 * @param string $slug Provider slug.
	 * @return array<string, string>
	 */
	public static function appends( string $slug ): array {
		$out = array();
		foreach ( (array) ( self::get( $slug )['appends'] ?? array() ) as $key => $taxonomy ) {
			if ( is_string( $taxonomy ) && '' !== $taxonomy ) {
				$out[ $key ] = $taxonomy;
			}
		}
		return $out;
	}

	/**
	 * Whether a field's polarity is reversed relative to ours.
	 *
	 * RescueGroups carries animalNoLargeDogs, animalNoSmallDogs,
	 * animalNoFemaleDogs, animalNoMaleDogs — fields whose `true` means NOT good
	 * with. Read naively into a tristate, `true` becomes 'yes' and the site
	 * advertises the exact opposite of what the shelter recorded.
	 *
	 * That is not hypothetical: 4838f0a fixed precisely this failure, where an
	 * emptiness test counted 'no' as true and 22 of 93 published pets displayed
	 * "Good with dogs, cats, kids" for animals recorded as unsafe with them. For
	 * an adoption site it is the worst direction an error can run.
	 *
	 * Declaring it in config is the point. A format that cannot express
	 * inversion pushes it into PHP, which is how the ten hardcoded provider keys
	 * in #33 came to exist in the first place.
	 *
	 * @param string $slug  Provider slug.
	 * @param string $field Canonical field name.
	 */
	public static function inverts( string $slug, string $field ): bool {
		return ! empty( self::get( $slug )['fields'][ $field ]['invert'] );
	}

	/**
	 * Flip a resolved tristate.
	 *
	 * Operates on the CANONICAL value, never the raw one: a provider may send
	 * true, 'Yes', '1' or 'y', and inverting before normalisation would have to
	 * re-implement resolve_tristate() to know what it was looking at.
	 *
	 * 'unknown' and '' are returned untouched. The opposite of "we do not know"
	 * is still "we do not know" — inverting it would manufacture a definite
	 * answer out of an absence, which is the same class of error in a quieter
	 * form.
	 *
	 * @param string $tristate One of 'yes', 'no', 'unknown', ''.
	 */
	public static function invert_tristate( string $tristate ): string {
		return match ( $tristate ) {
			'yes'   => 'no',
			'no'    => 'yes',
			default => $tristate,
		};
	}

	/**
	 * The key holding the provider's own record ID — what gets stored as
	 * _pet_ps_id and matched against on the next sync.
	 *
	 * @param string $slug Provider slug.
	 */
	public static function identity_key( string $slug ): string {
		$key = self::get( $slug )['identity'] ?? '';
		return is_string( $key ) ? $key : '';
	}

	/**
	 * Walk a declared path into a response.
	 *
	 * @param array<mixed> $data Response data.
	 * @param array<mixed> $path Path segments.
	 * @return mixed
	 */
	public static function dig( array $data, array $path ): mixed {
		$value = $data;
		foreach ( $path as $segment ) {
			if ( ! is_array( $value ) || ! array_key_exists( $segment, $value ) ) {
				return null;
			}
			$value = $value[ $segment ];
		}
		return $value;
	}

	/**
	 * The first photo URL in a raw response, via the provider's declared images
	 * shape.
	 *
	 * The sync had this path written out as a literal —
	 * `$data['images'][0]['image']['url']` — duplicating the shape the hydrator
	 * already reads from config. Two copies of one provider's nesting is exactly
	 * the coupling #33 catalogued.
	 *
	 * @param array<mixed> $data Raw response for one pet.
	 * @param string       $slug Provider slug.
	 */
	public static function first_image_url( array $data, string $slug ): string {
		$shape = self::shapes( $slug )['images'] ?? array();
		if ( empty( $shape['list'] ) || empty( $shape['url'] ) ) {
			return '';
		}

		$url = self::dig( $data, array_merge( (array) $shape['list'], array( 0 ), (array) $shape['url'] ) );

		return is_string( $url ) ? $url : '';
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
