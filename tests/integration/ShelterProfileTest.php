<?php
/**
 * The shelter's own identity, shared across the ShelterKit plugins.
 *
 * Until this existed nothing in the family stored a shelter's name, phone or
 * address. The kennel card carried its contact line as literal text in the
 * design, which is how the shipped placeholder — "Add your shelter's phone,
 * email and address here" — reached production and printed on real cards.
 *
 * The card's org name never had that problem, because it came from
 * wp:site-title: it was data, not a string in a layout. This makes the rest of
 * it data too.
 *
 * @package ShelterKit_Pets
 */

declare( strict_types = 1 );

namespace Petsync\Tests\Integration;

final class ShelterProfileTest extends PetTestCase {

	public function tear_down(): void {
		delete_option( \ShelterKit_Profile::OPTION );
		parent::tear_down();
	}

	// ─── The shared-file contract ───────────────────────────────────────────

	/**
	 * Both files are copied byte-identically into sibling plugins. A namespace
	 * would make each copy a different class, and the class_exists() guard that
	 * the whole mechanism rests on would never fire.
	 */
	public function test_the_shared_files_carry_no_plugin_namespace(): void {
		foreach ( array( 'class-shelterkit-profile.php', 'class-shelterkit-profile-versions.php' ) as $file ) {
			$src = (string) file_get_contents( PETSYNC_DIR . 'includes/shelterkit/' . $file );

			$this->assertDoesNotMatchRegularExpression( '/^\s*namespace\s+/m', $src, "$file must not be namespaced" );
			$this->assertStringNotContainsString( 'Petsync', $src, "$file must not reference this plugin's own naming" );
			$this->assertMatchesRegularExpression( '/if\s*\(\s*!\s*class_exists\(/', $src, "$file must guard its own definition" );
		}
	}

	public function test_the_highest_registered_version_wins(): void {
		\ShelterKit_Profile_Versions::register( '1.0.0', '/a/one.php' );
		\ShelterKit_Profile_Versions::register( '1.10.0', '/a/ten.php' );
		\ShelterKit_Profile_Versions::register( '1.2.0', '/a/two.php' );

		$this->assertSame(
			'/a/ten.php',
			\ShelterKit_Profile_Versions::winner(),
			'1.10.0 beats 1.2.0 — a string sort would get this backwards'
		);
	}

	public function test_this_plugin_registers_a_copy(): void {
		$this->assertNotEmpty( \ShelterKit_Profile_Versions::registered() );
		$this->assertTrue( class_exists( 'ShelterKit_Profile' ) );
	}

	// ─── Reading ────────────────────────────────────────────────────────────

	/**
	 * A consumer reading ['phone'] on an unfilled profile must get '' rather
	 * than a notice — the kennel card renders for every pet, filled in or not.
	 */
	public function test_every_field_is_present_even_when_nothing_is_saved(): void {
		delete_option( \ShelterKit_Profile::OPTION );

		$all = \ShelterKit_Profile::all();

		foreach ( array_keys( \ShelterKit_Profile::fields() ) as $field ) {
			$this->assertArrayHasKey( $field, $all );
			$this->assertIsString( $all[ $field ] );
		}
	}

	public function test_the_name_falls_back_to_the_site_title(): void {
		delete_option( \ShelterKit_Profile::OPTION );

		$this->assertSame( get_bloginfo( 'name' ), \ShelterKit_Profile::get( 'name' ) );
	}

	public function test_an_explicit_name_wins_over_the_site_title(): void {
		\ShelterKit_Profile::save( array( 'name' => 'Venango County Humane Society' ) );

		$this->assertSame( 'Venango County Humane Society', \ShelterKit_Profile::get( 'name' ) );
	}

	/**
	 * The name falls back and so is never empty. Counting it would make this
	 * always true, and the kennel card would print an empty contact block
	 * believing it had something to show.
	 */
	public function test_has_contact_details_ignores_the_name(): void {
		delete_option( \ShelterKit_Profile::OPTION );
		$this->assertFalse( \ShelterKit_Profile::has_contact_details() );

		\ShelterKit_Profile::save( array( 'name' => 'Somewhere' ) );
		$this->assertFalse( \ShelterKit_Profile::has_contact_details(), 'a name alone is not contact details' );

		\ShelterKit_Profile::save( array( 'phone' => '814-677-4040' ) );
		$this->assertTrue( \ShelterKit_Profile::has_contact_details() );
	}

	/**
	 * A shelter that fills in only some of the address must not get stray
	 * commas on a printed card.
	 */
	public function test_the_address_line_skips_what_is_missing(): void {
		\ShelterKit_Profile::save(
			array(
				'street_address' => '286 South Main Street',
				'locality'       => 'Seneca',
				'region'         => 'PA',
				'postal_code'    => '16346',
			)
		);
		$this->assertSame( '286 South Main Street, Seneca, PA 16346', \ShelterKit_Profile::address_line() );

		\ShelterKit_Profile::save( array( 'locality' => 'Seneca' ) );
		$this->assertSame( 'Seneca', \ShelterKit_Profile::address_line() );

		delete_option( \ShelterKit_Profile::OPTION );
		$this->assertSame( '', \ShelterKit_Profile::address_line() );
	}

	// ─── Writing ────────────────────────────────────────────────────────────

	public function test_unknown_keys_are_dropped_rather_than_stored(): void {
		\ShelterKit_Profile::save(
			array(
				'phone' => '814-677-4040',
				'evil'  => 'anything',
			)
		);

		$this->assertArrayNotHasKey( 'evil', (array) get_option( \ShelterKit_Profile::OPTION ) );
	}

	public function test_each_field_is_sanitised_by_its_own_rule(): void {
		\ShelterKit_Profile::save(
			array(
				'name'  => '<script>alert(1)</script>Shelter',
				'email' => 'not an email',
				'url'   => 'javascript:alert(1)',
			)
		);

		$this->assertStringNotContainsString( '<script>', \ShelterKit_Profile::get( 'name' ) );
		$this->assertSame( '', \ShelterKit_Profile::get( 'email' ), 'sanitize_email rejects a non-address' );
		$this->assertStringNotContainsString( 'javascript:', \ShelterKit_Profile::get( 'url' ) );
	}

	/**
	 * The option is shared. Removing Pets must not blank the contact details on
	 * another ShelterKit plugin's output.
	 */
	public function test_uninstall_does_not_delete_the_shared_option(): void {
		$uninstall = (string) file_get_contents( PETSYNC_DIR . 'uninstall.php' );

		$this->assertStringNotContainsString(
			"delete_option( 'shelterkit_organization' )",
			$uninstall,
			'the shelter profile is shared with sibling plugins and must survive uninstalling this one'
		);
		$this->assertStringContainsString( 'shelterkit_organization', $uninstall, 'and the reason should be written down' );
	}
}
