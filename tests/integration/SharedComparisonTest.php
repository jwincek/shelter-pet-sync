<?php
/**
 * A shared comparison link must not destroy the visitor's own comparison.
 *
 * The Share button produces `?compare=…`. Opening one used to:
 *
 *   1. seed the right list server-side, and render the right table
 *   2. then re-fetch over REST — a request that cannot carry the URL
 *   3. get [] back, overwrite the seeded state, and render "Compare Pets (0)"
 *   4. and persist that empty array, WIPING the visitor's own saved list
 *
 * Demonstrated in a browser before and after:
 *
 *   with the guards: heading (2), localStorage [111,222,333] intact
 *   without them:    heading (0), localStorage []
 *
 * Step 4 is the reason this is not a cosmetic bug. These tests cover the PHP
 * half — the precedence flag the client relies on — plus a source assertion for
 * the client guards, since there is no JS test harness here.
 *
 * @package ShelterKit_Pets
 */

declare( strict_types = 1 );

namespace Petsync\Tests\Integration;

final class SharedComparisonTest extends PetTestCase {

	public function tear_down(): void {
		unset( $_GET['compare'] );
		parent::tear_down();
	}

	public function test_a_compare_parameter_marks_the_list_as_url_supplied(): void {
		$_GET['compare'] = '12,34';

		$this->assertTrue( \Petsync_Helpers::comparison_is_from_url() );
	}

	public function test_no_parameter_means_the_list_is_the_visitors_own(): void {
		unset( $_GET['compare'] );

		$this->assertFalse( \Petsync_Helpers::comparison_is_from_url() );
	}

	/**
	 * `?compare=` with nothing after it is not a list from the URL. Treating it
	 * as one would suppress the sync and strand the visitor with an empty
	 * comparison they cannot refill.
	 */
	public function test_an_empty_parameter_does_not_count(): void {
		$_GET['compare'] = '';
		$this->assertFalse( \Petsync_Helpers::comparison_is_from_url() );

		$_GET['compare'] = '   ';
		$this->assertFalse( \Petsync_Helpers::comparison_is_from_url() );
	}

	/**
	 * The client cannot see the URL on its REST call, so this flag is the only
	 * way it learns the seeded list outranks anything it could fetch.
	 */
	public function test_the_flag_reaches_the_interactivity_state(): void {
		$a = $this->make_manual_pet();
		$b = $this->make_manual_pet();

		$_GET['compare'] = "$a,$b";

		// register-stores.php holds plain functions, loaded by Petsync_Blocks at
		// runtime rather than through the autoloader.
		require_once PETSYNC_DIR . 'includes/blocks/register-stores.php';
		\Petsync\Blocks\register_stores();

		$state = wp_interactivity_state( 'petsync' );

		$this->assertTrue( $state['comparisonFromUrl'], 'the client would re-fetch and clobber the seeded list' );
		$this->assertSame( array( $a, $b ), $state['comparison'] );
	}

	/**
	 * The guards live in JS, which has no harness here. Asserting the source
	 * cannot prove behaviour — that was done in a browser — but it does catch
	 * the likely regression, which is someone simplifying the guards away.
	 */
	public function test_the_client_guards_are_present(): void {
		$js = (string) file_get_contents( PETSYNC_DIR . 'assets/js/interactivity/comparison.js' );

		$this->assertStringContainsString(
			'comparisonFromUrl',
			$js,
			'init() must skip the sync when the URL supplied the list'
		);
		$this->assertMatchesRegularExpression(
			'/if\s*\(\s*!\s*result\.ids\.length\s*\)\s*\{\s*return;/',
			$js,
			'an empty answer must not overwrite a list the visitor already has'
		);
		$this->assertMatchesRegularExpression(
			'/if\s*\(\s*!\s*result\.favorites\.length\s*\)\s*\{\s*return;/',
			$js,
			'favorites carry the same hazard and need the same guard'
		);
	}

	/**
	 * Clearing is a deliberate action and must still be able to write an empty
	 * list — the guard above is scoped to the init sync, not to persistence.
	 */
	public function test_clearing_is_still_possible(): void {
		$js = (string) file_get_contents( PETSYNC_DIR . 'assets/js/interactivity/comparison.js' );

		$this->assertMatchesRegularExpression(
			'/clearComparison|gs\.comparison\s*=\s*\[\s*\]/',
			$js,
			'the guard must not have removed the ability to clear'
		);
	}
}
