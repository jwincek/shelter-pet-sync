<?php
/**
 * Export pets to CSV or JSON.
 *
 * @package ShelterKit_Pets
 * @since   1.1.0
 */

declare( strict_types = 1 );

namespace Petsync\Export;

use Petsync\Core\Pet_Hydrator;
use Petsync\Core\Query;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Exporter {

	/**
	 * Characters that make a spreadsheet treat a cell as a formula.
	 *
	 * Excel and Sheets also honour tab and carriage return as leading
	 * separators, which is why they are here alongside the obvious four.
	 *
	 * @var string[]
	 */
	private const FORMULA_TRIGGERS = array( '=', '+', '-', '@', "\t", "\r" );

	/**
	 * Neutralise a cell that a spreadsheet would otherwise execute.
	 *
	 * Pet names, breeds, `special_needs_detail` and `bonded_names` are free text
	 * an adopter or volunteer can influence, and they land in a file whose whole
	 * purpose is to be opened in Excel. A cell beginning `=`, `+`, `-` or `@`
	 * runs as a formula, and HYPERLINK or WEBSERVICE turn that into data
	 * exfiltration against whoever opens the sheet.
	 *
	 * Genuine numbers are left alone, so adoption_fee and weight still import as
	 * numbers rather than as text that has to be cleaned up.
	 *
	 * @param mixed $value Cell value.
	 * @return string Safe to write.
	 */
	public static function esc_csv_field( $value ): string {
		if ( is_bool( $value ) ) {
			$value = $value ? '1' : '0';
		}

		$value = (string) $value;

		if ( '' === $value || is_numeric( $value ) ) {
			return $value;
		}

		if ( in_array( $value[0], self::FORMULA_TRIGGERS, true ) ) {
			return "'" . $value;
		}

		return $value;
	}

	/**
	 * Flatten one hydrated pet to the given columns.
	 *
	 * Falls back to post meta for anything the entity does not carry.
	 * gallery_ids is the live example: it is an editable field a shelter curates
	 * by hand, but the hydrator surfaces the resolved `gallery` URLs instead and
	 * never emits the ids themselves. Reading only the entity silently drops the
	 * one column somebody actually chose.
	 *
	 * @param array<string, mixed> $pet     Hydrated entity.
	 * @param string[]             $columns Column names.
	 * @param int                  $post_id Post ID, for the meta fallback.
	 * @return array<string, string>
	 */
	public static function row( array $pet, array $columns, int $post_id = 0 ): array {
		$prefix = \Petsync\Core\Config::get_path( 'entities', 'entities.vcps_pet.meta_prefix', '_pet_' );
		$row    = array();

		foreach ( $columns as $column ) {
			$value = $pet[ $column ] ?? null;

			if ( null === $value && $post_id && Schema::is_importable( $column ) ) {
				$value = get_post_meta( $post_id, $prefix . $column, true );
			}

			$value = $value ?? '';

			if ( is_array( $value ) ) {
				// gallery_ids is a list of ints; gallery is a list of maps. Only
				// the scalar list round-trips, so the other is flattened for
				// reading rather than pretending it could come back.
				$flat  = array_map(
					static fn( $v ) => is_array( $v ) ? ( $v['url'] ?? $v['name'] ?? '' ) : $v,
					$value
				);
				$value = implode( '|', array_filter( array_map( 'strval', $flat ), 'strlen' ) );
			}

			$row[ $column ] = (string) $value;
		}

		return $row;
	}

	/**
	 * Post IDs to export.
	 *
	 * @param array<string, mixed> $filters status / animal / provider.
	 * @return int[]
	 */
	public static function pet_ids( array $filters = array() ): array {
		$query = Query::for( 'vcps_pet' );

		if ( ! empty( $filters['status'] ) ) {
			$query->status( (string) $filters['status'] );
		}
		if ( ! empty( $filters['animal'] ) ) {
			$query->where( 'animal', (string) $filters['animal'] );
		}

		$ids = $query->ids();

		if ( ! empty( $filters['provider'] ) ) {
			$provider = (string) $filters['provider'];
			$ids      = array_values(
				array_filter(
					$ids,
					static function ( int $id ) use ( $provider ): bool {
						$actual = (string) get_post_meta( $id, '_pet_provider', true );
						return 'manual' === $provider ? '' === $actual : $actual === $provider;
					}
				)
			);
		}

		return $ids;
	}

	/**
	 * Rows for the given pets, in column order.
	 *
	 * @param int[]  $ids  Post IDs.
	 * @param string $mode Schema mode.
	 * @return array<int, array<string, string>>
	 */
	public static function rows( array $ids, string $mode = Schema::PORTABLE ): array {
		$columns = Schema::columns( $mode );
		$rows    = array();

		foreach ( $ids as $id ) {
			$pet = Pet_Hydrator::get( (int) $id, 'full' );
			if ( $pet ) {
				$rows[] = self::row( $pet, $columns, (int) $id );
			}
		}

		return $rows;
	}

	/**
	 * CSV as a string.
	 *
	 * @param int[]  $ids  Post IDs.
	 * @param string $mode Schema mode.
	 */
	public static function to_csv( array $ids, string $mode = Schema::PORTABLE ): string {
		$columns = Schema::columns( $mode );
		$handle  = fopen( 'php://temp', 'r+' );

		// Escape passed explicitly: PHP 8.4 deprecates relying on the default,
		// and CI runs the 8.1 floor so it would not surface there.
		fputcsv( $handle, $columns, ',', '"', '\\' );

		foreach ( self::rows( $ids, $mode ) as $row ) {
			fputcsv( $handle, array_map( array( self::class, 'esc_csv_field' ), array_values( $row ) ), ',', '"', '\\' );
		}

		rewind( $handle );
		$csv = (string) stream_get_contents( $handle );
		fclose( $handle );

		return $csv;
	}

	/**
	 * JSON as a string. Keeps arrays as arrays, which is the reason to offer it
	 * at all — CSV flattens gallery_ids and the taxonomy sets.
	 *
	 * @param int[]  $ids  Post IDs.
	 * @param string $mode Schema mode.
	 */
	public static function to_json( array $ids, string $mode = Schema::PORTABLE ): string {
		$columns = Schema::columns( $mode );
		$prefix  = \Petsync\Core\Config::get_path( 'entities', 'entities.vcps_pet.meta_prefix', '_pet_' );
		$pets    = array();

		foreach ( $ids as $id ) {
			$pet = Pet_Hydrator::get( (int) $id, 'full' );
			if ( ! $pet ) {
				continue;
			}
			// Same meta fallback as the CSV path, so both formats carry the
			// same columns rather than JSON quietly having fewer.
			$record = array();
			foreach ( $columns as $column ) {
				if ( array_key_exists( $column, $pet ) ) {
					$record[ $column ] = $pet[ $column ];
					continue;
				}
				if ( Schema::is_importable( $column ) ) {
					$meta                = get_post_meta( (int) $id, $prefix . $column, true );
					$record[ $column ]   = '' === $meta ? null : $meta;
				}
			}
			$pets[] = $record;
		}

		return (string) wp_json_encode(
			array(
				'generator'  => 'ShelterKit Pets ' . PETSYNC_VERSION,
				'exported'   => gmdate( 'c' ),
				'mode'       => $mode,
				'importable' => Schema::PORTABLE === $mode,
				'columns'    => $columns,
				'pets'       => $pets,
			),
			JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
		);
	}
}
