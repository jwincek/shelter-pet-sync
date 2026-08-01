<?php
/**
 * Bootstrap for the WordPress integration suite.
 *
 * Loads the WordPress PHPUnit test library (installed by
 * bin/install-wp-tests.sh), then loads this plugin on `muplugins_loaded` so
 * its real post type, taxonomies, meta and abilities are registered against a
 * genuine WordPress and database.
 *
 * Most of what is worth testing here — hydration precedence, provider scoping,
 * migrations, meta sanitisation — only exists once WordPress has registered
 * things, so it cannot be covered by the unit suite.
 *
 * @package Petstablished_Sync
 */

declare( strict_types = 1 );

$_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! $_tests_dir ) {
	$_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

if ( ! file_exists( $_tests_dir . '/includes/functions.php' ) ) {
	fwrite(
		STDERR,
		"WordPress test library not found at {$_tests_dir}.\n" .
		"Run: bin/install-wp-tests.sh <db-name> <db-user> <db-pass> [db-host] <wp-version>\n" .
		"Note the version must be explicit (e.g. 7.0.2) — 'latest' is not a tag in\n" .
		"the wordpress-develop repository the library is fetched from.\n" .
		"Or set WP_TESTS_DIR to an existing installation.\n"
	);
	exit( 1 );
}

// Composer autoload first so the polyfills the WP suite requires exist, then
// point the suite at them explicitly.
require_once dirname( __DIR__, 2 ) . '/vendor/autoload.php';
if ( ! defined( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH' ) ) {
	define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', dirname( __DIR__, 2 ) . '/vendor/yoast/phpunit-polyfills' );
}

require_once $_tests_dir . '/includes/functions.php';

tests_add_filter(
	'muplugins_loaded',
	function (): void {
		require dirname( __DIR__, 2 ) . '/shelter-pets.php';
	}
);

require $_tests_dir . '/includes/bootstrap.php';

// The shared base class, loaded after the WP suite so WP_UnitTestCase exists.
// PHPUnit loads test files alphabetically and does not autoload them, so a
// case extending this would otherwise fail before the file is ever reached.
require_once __DIR__ . '/PetTestCase.php';
