<?php
/**
 * WP-CLI import command.
 *
 * @package ShelterKit_Pets
 * @since   1.1.0
 */

declare( strict_types = 1 );

namespace Petsync\Import;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CLI {

	/**
	 * Import pets from a CSV file.
	 *
	 * Runs as a dry run unless --commit is passed, because an import that
	 * writes on the first invocation gives nobody a chance to notice a
	 * mis-mapped column.
	 *
	 * ## OPTIONS
	 *
	 * <file>
	 * : Path to the CSV file.
	 *
	 * [--commit]
	 * : Actually write. Without this, nothing is created or changed.
	 *
	 * [--on-match=<action>]
	 * : What to do when a row's microchip_id matches an existing pet.
	 * ---
	 * default: update
	 * options:
	 *   - update
	 *   - skip
	 *   - duplicate
	 * ---
	 *
	 * [--verbose]
	 * : List every row, not just the failures.
	 *
	 * ## EXAMPLES
	 *
	 *     wp shelterkit import pets.csv
	 *     wp shelterkit import pets.csv --commit
	 *     wp shelterkit import pets.csv --commit --on-match=skip
	 *
	 * @param string[]              $args       Positional arguments.
	 * @param array<string, string> $assoc_args Flags.
	 */
	public function __invoke( array $args, array $assoc_args ): void {
		$file = $args[0] ?? '';

		if ( ! is_readable( $file ) ) {
			\WP_CLI::error( "Cannot read $file" );
		}

		$csv = file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_get_contents -- a local path the operator passed on the command line.
		if ( false === $csv ) {
			\WP_CLI::error( "Could not read $file" );
		}

		$commit = ! empty( $assoc_args['commit'] );

		$report = Importer::run(
			$csv,
			array(
				'commit'   => $commit,
				'on_match' => (string) ( $assoc_args['on-match'] ?? Importer::ON_MATCH_UPDATE ),
			)
		);

		if ( is_wp_error( $report ) ) {
			\WP_CLI::error( $report->get_error_message() );
		}

		\WP_CLI::log( sprintf( 'Mapped %d columns.', count( $report['mapping'] ) ) );

		foreach ( $report['unmapped'] as $header ) {
			\WP_CLI::warning( sprintf( 'Column "%s" did not match any field and was ignored.', $header ) );
		}
		foreach ( $report['duplicates'] as $header ) {
			\WP_CLI::warning( sprintf( 'Column "%s" maps to a field another column already claimed; the first one wins.', $header ) );
		}

		foreach ( $report['rows'] as $row ) {
			if ( $row['errors'] ) {
				\WP_CLI::warning( sprintf( 'Row %d (%s): %s', $row['line'], $row['name'], implode( ' ', $row['errors'] ) ) );
			} elseif ( ! empty( $assoc_args['verbose'] ) ) {
				\WP_CLI::log( sprintf( '  Row %d: %s — %s', $row['line'], $row['name'], $row['action'] ) );
			}
		}

		$summary = sprintf(
			'%d to create, %d to update, %d skipped, %d failed.',
			$report['created'],
			$report['updated'],
			$report['skipped'],
			$report['failed']
		);

		if ( ! $commit ) {
			\WP_CLI::success( "Dry run: $summary Re-run with --commit to write." );
			return;
		}

		\WP_CLI::success( str_replace( array( 'to create', 'to update' ), array( 'created', 'updated' ), $summary ) );
	}
}
