<?php
/**
 * Shelter Pets Sync Log
 *
 * Rolling per-run audit trail for manual and cron-triggered syncs.
 *
 * @package Shelter_Pets
 * @since 1.0.0
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Petsync_Sync_Log {

	public const OPTION_NAME = 'petsync_sync_log';
	public const MAX_ENTRIES = 30;

	private const MAX_ERROR_MESSAGES = 10;
	private const MAX_OPTION_BYTES   = 262144; // 256 KB hard cap.

	/**
	 * Append a sync run entry, trim to MAX_ENTRIES, and persist.
	 *
	 * Newest entries are prepended so `all()` returns them in reverse-chronological order.
	 */
	public static function record( array $entry ): void {
		$normalized = self::normalize_entry( $entry );

		$log = self::all();
		array_unshift( $log, $normalized );
		$log = array_slice( $log, 0, self::MAX_ENTRIES );

		// Defensive: if a pathological entry has bloated the log past 256 KB,
		// trim oldest entries until it fits. Won't fire under normal use.
		$entries = count( $log );
		$bytes   = strlen( serialize( $log ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- measuring the exact stored size.
		while ( $entries > 1 && $bytes > self::MAX_OPTION_BYTES ) {
			array_pop( $log );
			--$entries;
			$bytes = strlen( serialize( $log ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
		}

		if ( false === get_option( self::OPTION_NAME, false ) ) {
			add_option( self::OPTION_NAME, $log, '', 'no' );
		} else {
			update_option( self::OPTION_NAME, $log );
		}
	}

	/**
	 * Return all log entries, newest first.
	 */
	public static function all(): array {
		$log = get_option( self::OPTION_NAME, array() );
		return is_array( $log ) ? $log : array();
	}

	/**
	 * Find a single entry by its UUID.
	 */
	public static function find( string $id ): ?array {
		foreach ( self::all() as $entry ) {
			if ( ( $entry['id'] ?? '' ) === $id ) {
				return $entry;
			}
		}
		return null;
	}

	/**
	 * Wipe the log. Used by uninstall.
	 */
	public static function clear(): void {
		delete_option( self::OPTION_NAME );
	}

	/**
	 * Build a log entry array with sane defaults so callers can pass partial data.
	 */
	public static function build_entry(
		int $started,
		int $ended,
		string $trigger,
		string $outcome,
		array $stats,
		array $errors = array(),
		?string $note = null
	): array {
		return array(
			'id'       => wp_generate_uuid4(),
			'started'  => $started,
			'ended'    => $ended,
			'duration' => max( 0, $ended - $started ),
			'trigger'  => $trigger,
			'outcome'  => $outcome,
			'stats'    => array(
				'created'   => (int) ( $stats['created'] ?? 0 ),
				'updated'   => (int) ( $stats['updated'] ?? 0 ),
				'unchanged' => (int) ( $stats['unchanged'] ?? 0 ),
				'removed'   => (int) ( $stats['removed'] ?? 0 ),
				'errors'    => (int) ( $stats['errors'] ?? count( $errors ) ),
			),
			'errors'   => array_slice( array_values( array_map( 'strval', $errors ) ), 0, self::MAX_ERROR_MESSAGES ),
			'note'     => $note,
		);
	}

	private static function normalize_entry( array $entry ): array {
		$entry['id']       = $entry['id'] ?? wp_generate_uuid4();
		$entry['started']  = (int) ( $entry['started'] ?? time() );
		$entry['ended']    = (int) ( $entry['ended'] ?? $entry['started'] );
		$entry['duration'] = (int) ( $entry['duration'] ?? max( 0, $entry['ended'] - $entry['started'] ) );
		$entry['trigger']  = in_array( $entry['trigger'] ?? '', array( 'manual', 'cron' ), true )
			? $entry['trigger'] : 'manual';
		$entry['outcome']  = in_array( $entry['outcome'] ?? '', array( 'success', 'partial', 'error' ), true )
			? $entry['outcome'] : 'success';
		$entry['stats']    = is_array( $entry['stats'] ?? null ) ? $entry['stats'] : array();
		$entry['errors']   = is_array( $entry['errors'] ?? null )
			? array_slice( array_values( array_map( 'strval', $entry['errors'] ) ), 0, self::MAX_ERROR_MESSAGES )
			: array();
		$entry['note']     = isset( $entry['note'] ) ? (string) $entry['note'] : null;

		// Backfill stats sub-keys.
		$entry['stats'] = array(
			'created'   => (int) ( $entry['stats']['created'] ?? 0 ),
			'updated'   => (int) ( $entry['stats']['updated'] ?? 0 ),
			'unchanged' => (int) ( $entry['stats']['unchanged'] ?? 0 ),
			'removed'   => (int) ( $entry['stats']['removed'] ?? 0 ),
			'errors'    => (int) ( $entry['stats']['errors'] ?? count( $entry['errors'] ) ),
		);

		return $entry;
	}
}
