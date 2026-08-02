<?php
/**
 * One-time data migration: purge PII from stored Petstablished API snapshots.
 *
 * Context
 * -------
 * Before this release the sync stored the FULL `/public/pets` response in
 * `_pet_api_response` — ~250 fields per pet including owner name/address/phone,
 * cross-post + petlover contact emails, internal notes, euthanasia data, GPS
 * coordinates, and internal admin links. The plugin only ever uses ~28 of them.
 *
 * The sync now persists only the display-relevant whitelist
 * (Petsync_Sync::normalize_api_response()), and the hydrator no longer
 * surfaces the raw snapshot at all. This script retroactively slims every
 * snapshot already in the database so the historical PII is removed at rest,
 * and applies the new "don't show in public search" rule to existing rows.
 *
 * The change-detection hash (`_pet_api_hash`, computed over the full upstream
 * response) is intentionally left untouched, so an unchanged pet keeps its
 * slimmed snapshot without forcing a re-sync.
 *
 * Usage (from the site root, with the Local socket override — see the
 * plugin/site CLAUDE.md and the local-wpcli-db-socket memory):
 *
 *   wp eval-file \
 *     wp-content/plugins/shelter-pets/migration-scripts/2026-06-23-purge-pii-from-api-snapshots.php \
 *     --require=/tmp/dbhost.php --skip-themes
 *
 * Idempotent: re-running on already-slimmed snapshots is a no-op. Set
 * DRY_RUN to true to report what would change without writing.
 *
 * @package Shelter_Pets
 */

// declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "Must run inside WordPress (use: wp eval-file <this>).\n" );
	exit( 1 );
}

if ( ! class_exists( 'Petsync_Sync' ) ) {
	WP_CLI::error( 'Petsync_Sync is not loaded — is the plugin active?' );
}

// Flip to true to preview without writing.
const DRY_RUN = false;

$post_ids = get_posts( array(
	'post_type'      => 'vcps_pet',
	'post_status'    => 'any',
	'posts_per_page' => -1,
	'fields'         => 'ids',
) );

if ( empty( $post_ids ) ) {
	WP_CLI::success( 'No vcps_pet posts found — nothing to migrate.' );
	return;
}

$scanned       = 0;
$slimmed       = 0;
$unpublished   = 0;
$bytes_before  = 0;
$bytes_after   = 0;

foreach ( $post_ids as $post_id ) {
	$raw = get_post_meta( $post_id, '_pet_api_response', true );
	if ( ! is_string( $raw ) || '' === $raw ) {
		continue;
	}

	++$scanned;
	$data = json_decode( $raw, true );
	if ( ! is_array( $data ) ) {
		continue;
	}

	// Apply the "don't show in public search" rule retroactively — must be read
	// from the FULL snapshot, before the flag itself is stripped below.
	if ( ! empty( $data['dont_show_in_public_search'] ) && 'publish' === get_post_status( $post_id ) ) {
		if ( ! DRY_RUN ) {
			wp_update_post( array( 'ID' => $post_id, 'post_status' => 'draft' ) );
		}
		++$unpublished;
	}

	$slim = Petsync_Sync::normalize_api_response( $data );

	// Already slim? Skip the write.
	if ( count( $slim ) === count( $data ) ) {
		continue;
	}

	$new_json = wp_json_encode( $slim, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );

	$bytes_before += strlen( $raw );
	$bytes_after  += strlen( (string) $new_json );
	++$slimmed;

	if ( ! DRY_RUN ) {
		update_post_meta( $post_id, '_pet_api_response', $new_json );
	}
}

$kb_saved = round( ( $bytes_before - $bytes_after ) / 1024, 1 );
$verb     = DRY_RUN ? 'would be' : 'were';

WP_CLI::log( sprintf( 'Scanned %d snapshot(s).', $scanned ) );
WP_CLI::log( sprintf( '%d pet(s) %s slimmed (%s KB of PII/unused data removed).', $slimmed, $verb, $kb_saved ) );
WP_CLI::log( sprintf( '%d previously-public pet(s) flagged "don\'t show in public search" %s set to draft.', $unpublished, $verb ) );

if ( DRY_RUN ) {
	WP_CLI::warning( 'DRY_RUN is on — no changes written. Set DRY_RUN to false to apply.' );
} else {
	WP_CLI::success( 'PII purge complete.' );
}
