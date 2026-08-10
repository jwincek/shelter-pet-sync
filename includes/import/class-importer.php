<?php
/**
 * CSV import.
 *
 * The third way a pet can enter the site, after the provider sync and the block
 * editor. It exists for the shelter with no management platform at all, which
 * is the audience that most needs this plugin and currently has to type eighty
 * animals in one at a time.
 *
 * Two rules shape everything here:
 *
 * 1. **Imported pets are manual pets.** `_pet_provider` stays empty. Setting it
 *    to a real provider would make the next sync treat every imported pet as a
 *    record that vanished upstream and draft the lot — remove_stale_pets() is
 *    provider-scoped. It is the most damaging mistake this feature could make,
 *    so nothing here writes that meta and a test proves the survival path.
 * 2. **Dry run and commit are the same code path.** A preview produced by
 *    different code from the write is a preview of something else. `run()` takes
 *    a $commit flag and branches only where it must actually persist.
 *
 * @package ShelterKit_Pets
 * @since   1.1.0
 */

declare( strict_types = 1 );

namespace Petsync\Import;

use Petsync\Core\Config;
use Petsync\Core\Pet_Hydrator;
use Petsync\Export\Schema;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Importer {

	/**
	 * What to do when an incoming row matches an existing pet.
	 */
	public const ON_MATCH_UPDATE    = 'update';
	public const ON_MATCH_SKIP      = 'skip';
	public const ON_MATCH_DUPLICATE = 'duplicate';

	/**
	 * Rows above this are refused outright rather than half-imported. A shelter
	 * CSV is hundreds of animals; a hundred thousand rows is a wrong file.
	 */
	private const MAX_ROWS = 5000;

	/**
	 * @return array<string, mixed> The vcps_pet entity config.
	 */
	private static function entity(): array {
		return Config::get_path( 'entities', 'entities.vcps_pet', array() );
	}

	private static function prefix(): string {
		return (string) ( self::entity()['meta_prefix'] ?? '_pet_' );
	}

	/**
	 * Parse CSV text into a header row and data rows.
	 *
	 * @param string $csv Raw file contents.
	 * @return array{headers: string[], rows: array<int, string[]>}|\WP_Error
	 */
	public static function parse( string $csv ): array|\WP_Error {
		if ( '' === trim( $csv ) ) {
			return new \WP_Error( 'petsync_import_empty', __( 'The file is empty.', 'shelterkit-pets' ) );
		}

		// php://temp rather than a real file: the upload has already been read
		// into memory by the caller, and fgetcsv is the only correct way to
		// parse CSV — a split on commas breaks on any quoted field containing
		// one, which for this data means every multi-word description.
		$handle = fopen( 'php://temp', 'r+' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- an in-memory stream, not the filesystem.
		if ( false === $handle ) {
			return new \WP_Error( 'petsync_import_stream', __( 'Could not open a temporary stream to read the file.', 'shelterkit-pets' ) );
		}

		fwrite( $handle, $csv ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- in-memory stream.
		rewind( $handle );

		$headers = fgetcsv( $handle, 0, ',', '"', '' );
		if ( ! is_array( $headers ) ) {
			fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- in-memory stream.
			return new \WP_Error( 'petsync_import_no_header', __( 'The file has no header row.', 'shelterkit-pets' ) );
		}

		$rows = array();
		while ( ( $row = fgetcsv( $handle, 0, ',', '"', '' ) ) !== false ) { // phpcs:ignore WordPress.CodeAnalysis.AssignmentInCondition.FoundInWhileCondition -- the idiomatic fgetcsv loop.
			// fgetcsv yields array( null ) for a blank line. Trailing blank
			// lines are normal in a spreadsheet export and are not errors.
			if ( array( null ) === $row || array( '' ) === $row ) {
				continue;
			}
			$rows[] = array_map( static fn( $v ): string => (string) ( $v ?? '' ), $row );

			if ( count( $rows ) > self::MAX_ROWS ) {
				fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- in-memory stream.
				return new \WP_Error(
					'petsync_import_too_large',
					sprintf(
						/* translators: %s: maximum number of rows. */
						__( 'This file has more than %s rows, which is larger than an import should be. Split it, or check it is the file you meant.', 'shelterkit-pets' ),
						number_format_i18n( self::MAX_ROWS )
					)
				);
			}
		}

		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- in-memory stream.

		return array(
			'headers' => array_map( 'strval', $headers ),
			'rows'    => $rows,
		);
	}

	/**
	 * Turn one raw row into canonical column => value.
	 *
	 * @param string[]           $row     Raw cells.
	 * @param array<int, string> $mapping Column index => canonical column.
	 * @return array<string, string>
	 */
	private static function associate( array $row, array $mapping ): array {
		$out = array();
		foreach ( $mapping as $index => $column ) {
			$out[ $column ] = trim( (string) ( $row[ $index ] ?? '' ) );
		}
		return $out;
	}

	/**
	 * Validate one associated row.
	 *
	 * Returns per-row messages rather than throwing, because one bad row in a
	 * sheet of eighty must not cost the other seventy-nine.
	 *
	 * @param array<string, string> $data Canonical column => value.
	 * @return string[] Error messages; empty means valid.
	 */
	private static function validate( array $data ): array {
		$errors = array();

		if ( '' === trim( $data['name'] ?? '' ) ) {
			$errors[] = __( 'Name is required.', 'shelterkit-pets' );
		}

		$api_fields = (array) ( self::entity()['api_fields'] ?? array() );

		foreach ( $data as $column => $value ) {
			if ( '' === $value ) {
				continue;
			}

			$type = $api_fields[ $column ]['type'] ?? 'string';

			// A tristate accepts a wide range of spellings by design, but not
			// anything: 'maybe' resolving to 'unknown' would silently record
			// "asked, inconclusive" for a cell the shelter meant as a note.
			if ( 'tristate' === $type && ! self::is_recognised_tristate( $value ) ) {
				$errors[] = sprintf(
					/* translators: 1: column name, 2: the cell's value. */
					__( '%1$s: "%2$s" is not a yes/no value. Use yes, no, unknown, or leave it blank.', 'shelterkit-pets' ),
					$column,
					$value
				);
			}

			if ( 'numerical_age' === $column && ! is_numeric( $value ) ) {
				$errors[] = sprintf(
					/* translators: %s: the cell's value. */
					__( 'numerical_age: "%s" is not a number.', 'shelterkit-pets' ),
					$value
				);
			}
		}

		return $errors;
	}

	/**
	 * Whether a cell is a spelling resolve_tristate() genuinely understands.
	 *
	 * resolve_tristate() maps ANY unrecognised non-empty string to 'unknown',
	 * which is right for a provider feed — it is a normaliser, not a validator —
	 * and wrong for a human-authored spreadsheet, where an unrecognised value
	 * usually means a typo or a wrongly-mapped column.
	 *
	 * @param string $value Cell value.
	 */
	private static function is_recognised_tristate( string $value ): bool {
		$known = array( 'yes', 'y', 'true', '1', 'no', 'n', 'false', '0', 'unknown', 'unsure', 'not sure', 'ask', '' );

		return in_array( strtolower( trim( $value ) ), $known, true );
	}

	/**
	 * Normalise a validated cell to what gets stored.
	 *
	 * @param string $column Canonical column.
	 * @param string $value  Raw cell.
	 */
	private static function normalise_value( string $column, string $value ): string {
		$type = self::entity()['api_fields'][ $column ]['type'] ?? 'string';

		if ( 'tristate' === $type ) {
			// The hydrator's own resolver, deliberately. Reimplementing it here
			// would let an imported 'Y' and a synced 'Y' drift apart.
			$lower = strtolower( trim( $value ) );
			if ( in_array( $lower, array( 'y', 'n' ), true ) ) {
				$value = ( 'y' === $lower ) ? 'yes' : 'no';
			}
			if ( in_array( $lower, array( 'unsure', 'not sure', 'ask' ), true ) ) {
				$value = 'unknown';
			}

			return Pet_Hydrator::resolve_tristate( $value );
		}

		return sanitize_text_field( $value );
	}

	/**
	 * Find an existing pet this row refers to.
	 *
	 * Matches on microchip_id, which is the only field in the schema that is
	 * genuinely unique per animal. Name is not: shelters have several Lunas.
	 *
	 * @param array<string, string> $data Canonical column => value.
	 */
	private static function find_existing( array $data ): int {
		$chip = trim( $data['microchip_id'] ?? '' );
		if ( '' === $chip ) {
			return 0;
		}

		$found = get_posts(
			array(
				'post_type'        => 'vcps_pet',
				'post_status'      => 'any',
				'numberposts'      => 1,
				'fields'           => 'ids',
				'suppress_filters' => false,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- matching on the one unique field is the point.
				'meta_query'       => array(
					array(
						'key'   => self::prefix() . 'microchip_id',
						'value' => $chip,
					),
				),
			)
		);

		return (int) ( $found[0] ?? 0 );
	}

	/**
	 * Parse, validate, and optionally write.
	 *
	 * @param string               $csv     Raw file contents.
	 * @param array<string, mixed> $options mapping, on_match, commit.
	 * @return array<string, mixed>|\WP_Error
	 */
	public static function run( string $csv, array $options = array() ): array|\WP_Error {
		$parsed = self::parse( $csv );
		if ( is_wp_error( $parsed ) ) {
			return $parsed;
		}

		$commit   = ! empty( $options['commit'] );
		$on_match = (string) ( $options['on_match'] ?? self::ON_MATCH_UPDATE );

		// An explicit mapping from the confirmation UI wins; otherwise resolve
		// the headers ourselves.
		$mapped     = Column_Mapper::map( $parsed['headers'] );
		$mapping    = isset( $options['mapping'] ) && is_array( $options['mapping'] )
			? array_filter( $options['mapping'], static fn( $c ): bool => Schema::is_importable( (string) $c ) )
			: $mapped['mapping'];
		$taxonomies = Schema::taxonomy_columns();

		if ( ! in_array( 'name', $mapping, true ) ) {
			return new \WP_Error(
				'petsync_import_no_name',
				__( 'No column could be matched to the pet\'s name, which is required. Check the header row.', 'shelterkit-pets' )
			);
		}

		$report = array(
			'mapping'    => $mapping,
			'unmapped'   => $mapped['unmapped'],
			'duplicates' => $mapped['duplicates'],
			'committed'  => $commit,
			'created'    => 0,
			'updated'    => 0,
			'skipped'    => 0,
			'failed'     => 0,
			'rows'       => array(),
		);

		foreach ( $parsed['rows'] as $offset => $raw ) {
			// +2: one for the header row, one because humans count from 1. A
			// row number that does not match what the shelter sees in Excel is
			// worse than no row number.
			$line = $offset + 2;

			// A row with the wrong number of cells shifts every value after the
			// gap into the next field. Nothing downstream can detect that: the
			// values are individually plausible, just attached to the wrong
			// columns. Caught here only because it was noticed catching itself
			// by luck — a stray colour landed in a yes/no field and failed
			// validation, where a stray 'yes' would have imported silently.
			$errors = array();
			if ( count( $raw ) !== count( $parsed['headers'] ) ) {
				$errors[] = sprintf(
					/* translators: 1: number of values found, 2: number of columns expected. */
					__( 'This row has %1$d values but the header has %2$d columns, so its values do not line up. Check for a missing or extra comma.', 'shelterkit-pets' ),
					count( $raw ),
					count( $parsed['headers'] )
				);
			}

			$data   = self::associate( $raw, $mapping );
			$errors = array_merge( $errors, self::validate( $data ) );

			if ( $errors ) {
				++$report['failed'];
				$report['rows'][] = array(
					'line'   => $line,
					'name'   => $data['name'] ?? '',
					'action' => 'error',
					'errors' => $errors,
				);
				continue;
			}

			$existing = self::find_existing( $data );
			$action   = 'create';

			if ( $existing ) {
				if ( self::ON_MATCH_SKIP === $on_match ) {
					++$report['skipped'];
					$report['rows'][] = array(
						'line'   => $line,
						'name'   => $data['name'],
						'action' => 'skip',
						'id'     => $existing,
						'errors' => array(),
					);
					continue;
				}
				if ( self::ON_MATCH_UPDATE === $on_match ) {
					$action = 'update';
				}
			}

			$post_id = $commit ? self::write( $data, 'update' === $action ? $existing : 0, $taxonomies ) : 0;

			if ( $commit && is_wp_error( $post_id ) ) {
				++$report['failed'];
				$report['rows'][] = array(
					'line'   => $line,
					'name'   => $data['name'],
					'action' => 'error',
					'errors' => array( $post_id->get_error_message() ),
				);
				continue;
			}

			if ( 'update' === $action ) {
				++$report['updated'];
			} else {
				++$report['created'];
			}

			$report['rows'][] = array(
				'line'   => $line,
				'name'   => $data['name'],
				'action' => $action,
				'id'     => (int) $post_id,
				'errors' => array(),
			);
		}

		return $report;
	}

	/**
	 * Write one row.
	 *
	 * @param array<string, string> $data       Canonical column => value.
	 * @param int                   $existing   Post to update, or 0 to create.
	 * @param array<string, string> $taxonomies Column => taxonomy slug.
	 * @return int|\WP_Error
	 */
	private static function write( array $data, int $existing, array $taxonomies ): int|\WP_Error {
		$postarr = array(
			'post_type'    => 'vcps_pet',
			'post_status'  => 'publish',
			'post_title'   => sanitize_text_field( $data['name'] ),
			'post_content' => wp_kses_post( $data['description'] ?? '' ),
		);

		if ( $existing ) {
			$postarr['ID'] = $existing;
			$post_id       = wp_update_post( $postarr, true );
		} else {
			$post_id = wp_insert_post( $postarr, true );
		}

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		$post_id = (int) $post_id;
		$prefix  = self::prefix();

		// _pet_provider is deliberately NOT written. An imported pet is a manual
		// pet; giving it a provider would make the next sync for that provider
		// find it absent from the feed and draft it. See the class docblock.
		foreach ( $data as $column => $value ) {
			if ( in_array( $column, array( 'id', 'name', 'description' ), true ) || isset( $taxonomies[ $column ] ) ) {
				continue;
			}
			if ( ! Schema::is_importable( $column ) ) {
				continue;
			}

			update_post_meta( $post_id, $prefix . $column, self::normalise_value( $column, $value ) );
		}

		foreach ( $taxonomies as $column => $taxonomy ) {
			$term = trim( $data[ $column ] ?? '' );
			if ( '' === $term || ! taxonomy_exists( $taxonomy ) ) {
				continue;
			}
			// Terms arrive as names a person typed. wp_set_object_terms creates
			// what does not exist, which is what a shelter starting from a
			// spreadsheet needs — their breeds are not our list.
			wp_set_object_terms( $post_id, sanitize_text_field( $term ), $taxonomy );
		}

		// Attribute terms are derived from the hydrated entity, so they have to
		// be recomputed after the meta above lands. The editor path gets this
		// from wp_after_insert_post; an import writes meta afterwards, so it
		// must ask explicitly or imported pets get no pet_attribute terms and
		// silently vanish from compatibility filtering.
		Pet_Hydrator::flush_cache();
		\Petsync\Core\CPT_Registry::sync_attribute_terms( $post_id );

		return $post_id;
	}
}
