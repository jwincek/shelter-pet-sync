<?php
/**
 * The export column schema.
 *
 * Derived from config, never hand-listed, so a field added to entities.json
 * becomes an exportable column with no change here — and so #31's importer can
 * derive its columns from the same place, making the round-trip true by
 * construction rather than by discipline.
 *
 * @package ShelterKit_Pets
 * @since   1.1.0
 */

declare( strict_types = 1 );

namespace Petsync\Export;

use Petsync\Core\Config;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Schema {

	/**
	 * Columns that survive a round-trip: identity, taxonomies, editable fields.
	 */
	public const PORTABLE = 'portable';

	/**
	 * Everything the hydrator emits, computed fields included. For reading, not
	 * for re-importing.
	 */
	public const FULL = 'full';

	/**
	 * Identity and display columns. `id` is the local post ID and is
	 * informational on import; `name` is the post title; `description` is the
	 * post content.
	 *
	 * `description` is a COMPUTED field, so it does not arrive via
	 * editable_fields — but omitting it meant a portable export carried every
	 * pet's data and none of its story, and a backup restored through the
	 * importer came back with empty descriptions. Found by #31's round-trip
	 * test, which is exactly the sort of thing only a round trip finds.
	 *
	 * @var string[]
	 */
	private const IDENTITY = array( 'id', 'name', 'description' );

	/**
	 * @return array<string, mixed> The vcps_pet entity config.
	 */
	private static function entity(): array {
		return Config::get_path( 'entities', 'entities.vcps_pet', array() );
	}

	/**
	 * Column names for a mode, in a stable order.
	 *
	 * Stable matters: a column order that shifts between exports turns a diff
	 * of two backups into noise.
	 *
	 * @param string $mode Schema::PORTABLE or Schema::FULL.
	 * @return string[]
	 */
	public static function columns( string $mode = self::PORTABLE ): array {
		$entity = self::entity();

		// taxonomies, not taxonomy_source_map: the map is keyed on the
		// PROVIDER's field names and its values are taxonomy slugs, whereas the
		// hydrated entity keys terms by the canonical name (status, animal, …).
		// Exporting pet_status when the entity says status is the kind of thing
		// that only shows up as a column of empty cells.
		$columns = array_merge(
			self::IDENTITY,
			array_keys( (array) ( $entity['taxonomies'] ?? array() ) ),
			array_keys( (array) ( $entity['editable_fields'] ?? array() ) )
		);

		if ( self::FULL === $mode ) {
			$columns = array_merge(
				$columns,
				self::slug_columns(),
				array_keys( (array) ( $entity['api_fields'] ?? array() ) ),
				array_keys( (array) ( $entity['computed'] ?? array() ) )
			);
		}

		// Never exported: the raw provider snapshot and the change-detection
		// hash. The snapshot is a cache of a third party's data — normalise
		// already strips its PII, and there is no reason to widen that.
		$columns = array_diff( $columns, array( 'api_response', 'api_hash' ) );

		return array_values( array_unique( $columns ) );
	}

	/**
	 * Whether a column can be written back by an importer.
	 *
	 * @param string $column Column name.
	 */
	public static function is_importable( string $column ): bool {
		return in_array( $column, self::columns( self::PORTABLE ), true );
	}

	/**
	 * Column name => taxonomy slug, so an importer can write terms back.
	 *
	 * @return array<string, string>
	 */
	public static function taxonomy_columns(): array {
		$map = array();
		foreach ( (array) ( self::entity()['taxonomies'] ?? array() ) as $column => $config ) {
			$map[ $column ] = $config['taxonomy'] ?? '';
		}
		return $map;
	}

	/**
	 * The hydrator emits a `…Slug` twin for every taxonomy. They are derived
	 * from the term name, so a portable export omits them — a human types
	 * "Available", not "available", and re-importing both would invite the two
	 * to disagree. `full` keeps them, since a report may want to sort on them.
	 *
	 * @return string[]
	 */
	public static function slug_columns(): array {
		return array_map(
			static fn( string $c ): string => $c . 'Slug',
			array_keys( (array) ( self::entity()['taxonomies'] ?? array() ) )
		);
	}
}
