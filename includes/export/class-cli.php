<?php
/**
 * WP-CLI export command.
 *
 * @package ShelterKit_Pets
 * @since   1.1.0
 */

declare( strict_types = 1 );

namespace Petsync\Export;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CLI {

	/**
	 * Export pets to CSV or JSON.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : csv or json.
	 * ---
	 * default: csv
	 * options:
	 *   - csv
	 *   - json
	 * ---
	 *
	 * [--mode=<mode>]
	 * : portable re-imports; full includes computed fields and does not.
	 * ---
	 * default: portable
	 * options:
	 *   - portable
	 *   - full
	 * ---
	 *
	 * [--status=<status>]
	 * : Only pets with this status term.
	 *
	 * [--animal=<animal>]
	 * : Only pets of this species.
	 *
	 * [--provider=<provider>]
	 * : Provider slug, or `manual` for hand-entered pets.
	 *
	 * [--file=<path>]
	 * : Write here instead of STDOUT.
	 *
	 * ## EXAMPLES
	 *
	 *     wp shelterkit export --file=pets.csv
	 *     wp shelterkit export --format=json --mode=full --status=available
	 *     wp shelterkit export --provider=manual --file=hand-entered.csv
	 *
	 * @param array<int, string>    $args       Positional args.
	 * @param array<string, string> $assoc_args Flags.
	 */
	public function export( array $args, array $assoc_args ): void {
		$format = $assoc_args['format'] ?? 'csv';
		$mode   = ( $assoc_args['mode'] ?? Schema::PORTABLE ) === Schema::FULL ? Schema::FULL : Schema::PORTABLE;

		$ids = Exporter::pet_ids(
			array_filter(
				array(
					'status'   => $assoc_args['status'] ?? null,
					'animal'   => $assoc_args['animal'] ?? null,
					'provider' => $assoc_args['provider'] ?? null,
				)
			)
		);

		if ( ! $ids ) {
			\WP_CLI::warning( 'No pets matched.' );
			return;
		}

		$output = 'json' === $format
			? Exporter::to_json( $ids, $mode )
			: Exporter::to_csv( $ids, $mode );

		$file = $assoc_args['file'] ?? '';

		if ( $file ) {
			if ( false === file_put_contents( $file, $output ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- CLI writing to a caller-specified path.
				\WP_CLI::error( "Could not write to $file" );
			}
			\WP_CLI::success(
				sprintf( 'Exported %d pet(s) to %s (%s, %s).', count( $ids ), $file, $format, $mode )
			);
			return;
		}

		// STDOUT, so it can be piped.
		\WP_CLI::line( $output );
	}
}
