<?php
/**
 * Version bumper for ShelterKit Pets.
 *
 * The plugin version is declared in a lot of places — the header, the
 * PETSYNC_VERSION constant, readme.txt's Stable tag, package.json,
 * and one per block.json. Hand-editing that many files per release is how the
 * header and Stable tag drift apart, and a Stable tag that doesn't match the
 * tagged release makes WordPress.org serve the wrong code or nothing at all.
 *
 * This rewrites all of them from a single argument. `bin/validate-config.php`
 * check 8 fails the build if they ever disagree again.
 *
 * Usage:
 *   php bin/bump-version.php 1.0.1
 *   php bin/bump-version.php 1.0.1 --dry-run
 *
 * Note: block.json versions are core's asset cache-buster (wp-includes/blocks.php).
 * They are kept in lockstep with the plugin version deliberately — that way every
 * release busts block asset caches, which is what you want when a release can
 * change any block's CSS or view.js.
 *
 * Exit code: 1 on bad input or if nothing could be written.
 *
 * @package ShelterKit_Pets
 */

declare( strict_types = 1 );

$root = dirname( __DIR__ );
$args = array_slice( $argv, 1 );

$dry_run = in_array( '--dry-run', $args, true );
$version = null;

foreach ( $args as $arg ) {
	if ( ! str_starts_with( $arg, '--' ) ) {
		$version = $arg;
		break;
	}
}

if ( null === $version ) {
	fwrite( STDERR, "Usage: php bin/bump-version.php <version> [--dry-run]\n" );
	exit( 1 );
}

// WordPress.org sorts versions with PHP's version_compare, which wants a plain
// dotted numeric form. Reject anything it would order unpredictably.
if ( ! preg_match( '/^\d+\.\d+(\.\d+)?$/', $version ) ) {
	fwrite( STDERR, "Error: '$version' is not a dotted numeric version (e.g. 1.0.1).\n" );
	exit( 1 );
}

$main_file = $root . '/' . basename( $root ) . '.php';
$changes   = [];
$errors    = [];

/**
 * Apply a regex substitution to a file, recording what changed.
 *
 * @param string $path        Absolute file path.
 * @param string $pattern     Pattern with one capture group around the version.
 * @param string $replacement Replacement using $1/$2 backreferences.
 * @param string $label       Human label for output.
 */
$patch = static function ( string $path, string $pattern, string $replacement, string $label ) use ( $root, &$changes, &$errors, $dry_run ): void {
	if ( ! is_file( $path ) ) {
		$errors[] = "missing file: " . str_replace( $root . '/', '', $path );
		return;
	}

	$src = (string) file_get_contents( $path );
	$out = preg_replace( $pattern, $replacement, $src, 1, $count );

	if ( null === $out || 0 === $count ) {
		$errors[] = "no match for $label in " . str_replace( $root . '/', '', $path );
		return;
	}

	if ( $out === $src ) {
		return; // Already at target version.
	}

	if ( ! $dry_run ) {
		file_put_contents( $path, $out );
	}
	$changes[] = str_replace( $root . '/', '', $path ) . "  ($label)";
};

// Plugin header — the source of truth.
$patch( $main_file, '/^(\s*\*\s*Version:\s*)\S+/m', '${1}' . $version, 'header' );

// Runtime constant.
$patch( $main_file, "/(define\(\s*'PETSYNC_VERSION'\s*,\s*')[^']+(')/", '${1}' . $version . '${2}', 'constant' );

// readme.txt Stable tag — the one WordPress.org actually reads.
$patch( $root . '/readme.txt', '/^(Stable tag:\s*)\S+/mi', '${1}' . $version, 'Stable tag' );

// package.json.
$patch( $root . '/package.json', '/("version"\s*:\s*")[^"]+(")/', '${1}' . $version . '${2}', 'package.json' );

// Every block.json — core uses this as the block's asset version.
foreach ( glob( $root . '/blocks/*/block.json' ) ?: [] as $block_json ) {
	$patch( $block_json, '/("version"\s*:\s*")[^"]+(")/', '${1}' . $version . '${2}', 'block asset version' );
}

// ── Report ───────────────────────────────────────────────────────────────────
$prefix = $dry_run ? '[dry-run] ' : '';

if ( $changes ) {
	echo $prefix . 'Set version ' . $version . ' in ' . count( $changes ) . " file(s):\n";
	foreach ( $changes as $c ) {
		echo "  $c\n";
	}
} else {
	echo $prefix . "Everything already at $version — nothing to do.\n";
}

if ( $errors ) {
	echo "\nProblems:\n";
	foreach ( $errors as $e ) {
		echo "  ! $e\n";
	}
	exit( 1 );
}

if ( ! $dry_run ) {
	echo "\nNext: update the CHANGELOG.md and readme.txt changelog sections by hand,\n";
	echo "then run `php bin/validate-config.php` to confirm everything agrees.\n";
}
