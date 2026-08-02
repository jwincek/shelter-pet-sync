<?php
/**
 * One-time data migration: rename the `pet` post type to `vcps_pet`.
 *
 * Context
 * -------
 * v1.0.0 prefixes the generic `pet` custom post type to `vcps_pet`
 * (WordPress.org collision/rejection risk). The plugin now registers
 * `vcps_pet`, so any rows still stored as `pet` become orphaned until
 * their `post_type` column is updated. Taxonomy term relationships and
 * post meta are keyed by post ID, so they survive the rename untouched —
 * only `wp_posts.post_type` needs changing.
 *
 * This is NOT shipped behavior: a first public release has no prior
 * public version to upgrade from, so the plugin carries no in-line
 * migration. This script exists only to fix pre-1.0.0 dev/local installs
 * (and is excluded from the distributed plugin zip).
 *
 * Usage (from the site root, with the Local socket override — see the
 * plugin/site CLAUDE.md and the local-wpcli-db-socket memory):
 *
 *   wp eval-file \
 *     wp-content/plugins/shelter-pets/migration-scripts/2026-06-23-rename-cpt-pet-to-vcps_pet.php \
 *     --require=/tmp/dbhost.php --skip-themes
 *
 * Idempotent: re-running after a successful migration is a no-op
 * (no rows left with post_type = 'pet'). Pass `--dry-run` style by
 * leaving DRY_RUN=true below to preview the count without writing.
 *
 * @package Shelter_Pets
 */

// declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "Must run inside WordPress (use: wp eval-file <this>).\n" );
	exit( 1 );
}

const OLD_POST_TYPE = 'pet';
const NEW_POST_TYPE = 'vcps_pet';

// Flip to true to preview the affected row count without writing.
const DRY_RUN = false;

global $wpdb;

$count = (int) $wpdb->get_var(
	$wpdb->prepare(
		"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s",
		OLD_POST_TYPE
	)
);

if ( 0 === $count ) {
	WP_CLI::success( sprintf( 'Nothing to migrate: no rows with post_type = "%s".', OLD_POST_TYPE ) );
	return;
}

WP_CLI::log( sprintf( 'Found %d post(s) with post_type = "%s".', $count, OLD_POST_TYPE ) );

if ( DRY_RUN ) {
	WP_CLI::warning( 'DRY_RUN is on — no changes written. Set DRY_RUN to false to apply.' );
	return;
}

// Update the post_type column in place. Term relationships and post meta
// are keyed by post ID and are unaffected.
$updated = $wpdb->update(
	$wpdb->posts,
	[ 'post_type' => NEW_POST_TYPE ],
	[ 'post_type' => OLD_POST_TYPE ],
	[ '%s' ],
	[ '%s' ]
);

if ( false === $updated ) {
	WP_CLI::error( 'Database update failed: ' . $wpdb->last_error );
}

// Invalidate caches for the moved posts and rebuild rewrite rules so the
// /adopt/pets/ archive resolves to the renamed post type.
wp_cache_flush();
flush_rewrite_rules( false );

WP_CLI::success(
	sprintf(
		'Migrated %d post(s) from "%s" to "%s" and flushed rewrite rules.',
		(int) $updated,
		OLD_POST_TYPE,
		NEW_POST_TYPE
	)
);
