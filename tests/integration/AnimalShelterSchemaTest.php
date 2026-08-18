<?php
/**
 * AnimalShelter structured data, and the duplicate it must never create.
 *
 * schema.org's AnimalShelter defines no properties of its own — it is
 * Organization > LocalBusiness > AnimalShelter, an address-and-hours type. So
 * this is a type refinement, and the hard part is not building the node but
 * declining to emit a second one where an SEO plugin already describes the same
 * organisation.
 *
 * Every hook name here was read from the installed plugin's source, not from
 * documentation. The earlier assumption that no such hook existed was wrong in
 * all three cases.
 *
 * @package ShelterKit_Pets
 */

declare( strict_types = 1 );

namespace Petsync\Tests\Integration;

use Petsync\Schema\Animal_Shelter;

final class AnimalShelterSchemaTest extends PetTestCase {

	public function tear_down(): void {
		delete_option( \ShelterKit_Profile::OPTION );
		delete_option( 'petsync_settings' );
		parent::tear_down();
	}

	private function fill_profile(): void {
		\ShelterKit_Profile::save(
			array(
				'name'           => 'Venango County Humane Society',
				'street_address' => '286 South Main Street',
				'locality'       => 'Seneca',
				'region'         => 'PA',
				'postal_code'    => '16346',
				'phone'          => '814-677-4040',
			)
		);
	}

	private function enable(): void {
		update_option( 'petsync_settings', array( Animal_Shelter::SETTING => true ) );
	}

	// ─── Off by default ─────────────────────────────────────────────────────

	/**
	 * This changes what search engines are told about a site. A plugin does not
	 * get to do that unasked.
	 */
	public function test_it_is_off_until_switched_on(): void {
		delete_option( 'petsync_settings' );

		$this->assertFalse( Animal_Shelter::is_enabled() );
	}

	public function test_nothing_is_printed_when_it_is_off(): void {
		$this->fill_profile();
		delete_option( 'petsync_settings' );

		ob_start();
		Animal_Shelter::register();
		do_action( 'wp_head' );
		$html = (string) ob_get_clean();

		$this->assertStringNotContainsString( 'AnimalShelter', $html );
	}

	// ─── Never a half-populated entity ──────────────────────────────────────

	/**
	 * Claiming to be a local business while withholding the address is worse
	 * than saying nothing. The name does not count — it falls back to the site
	 * title, so it is never empty.
	 */
	public function test_an_empty_profile_produces_no_node(): void {
		delete_option( \ShelterKit_Profile::OPTION );

		$this->assertNull( Animal_Shelter::data() );
	}

	public function test_a_name_alone_produces_no_node(): void {
		\ShelterKit_Profile::save( array( 'name' => 'Somewhere' ) );

		$this->assertNull( Animal_Shelter::data() );
	}

	public function test_a_filled_profile_produces_a_valid_node(): void {
		$this->fill_profile();

		$data = Animal_Shelter::data();

		$this->assertSame( 'https://schema.org', $data['@context'] );
		$this->assertSame( 'AnimalShelter', $data['@type'] );
		$this->assertSame( 'PostalAddress', $data['address']['@type'] );
		$this->assertSame( 'Seneca', $data['address']['addressLocality'] );
		$this->assertSame( '814-677-4040', $data['telephone'] );
		$this->assertArrayNotHasKey( 'email', $data, 'an unset field must be omitted, not emitted empty' );
	}

	/**
	 * A shelter with only a phone gets no address object rather than one
	 * containing nothing but its own @type.
	 */
	public function test_an_address_with_no_parts_is_omitted_entirely(): void {
		\ShelterKit_Profile::save( array( 'phone' => '814-677-4040' ) );

		$this->assertArrayNotHasKey( 'address', (array) Animal_Shelter::data() );
	}

	// ─── Encoding ───────────────────────────────────────────────────────────

	/**
	 * JSON-LD is a script context. A shelter's name containing markup must not
	 * be able to close the block.
	 */
	public function test_the_node_is_encoded_not_concatenated(): void {
		\ShelterKit_Profile::save(
			array(
				'name'  => 'Shelter</script><script>alert(1)</script>',
				'phone' => '814-677-4040',
			)
		);
		$this->enable();

		ob_start();
		Animal_Shelter::print_own_node();
		$html = (string) ob_get_clean();

		$this->assertStringNotContainsString( '</script><script>', $html, 'the name must not be able to break out' );
		$this->assertStringContainsString( 'application/ld+json', $html );
	}

	// ─── The duplicate this exists to avoid ─────────────────────────────────

	/**
	 * Every adapter names a filter that exists in the installed plugin. A typo
	 * here fails silently: the filter never fires and the type is never refined.
	 */
	public function test_every_adapter_declares_a_detection_constant_and_a_hook(): void {
		foreach ( Animal_Shelter::adapters() as $slug => $adapter ) {
			$this->assertNotEmpty( $adapter['constant'], "$slug needs a detection constant" );
			$this->assertNotEmpty( $adapter['hook'], "$slug needs a hook" );
			$this->assertContains( $adapter['shape'], array( 'type', 'graph' ), "$slug has an unknown shape" );
		}
	}

	/**
	 * SEOPress hands over the @type string itself.
	 */
	public function test_the_type_shape_refines_only_the_default(): void {
		$this->assertSame( 'AnimalShelter', Animal_Shelter::refine_type( 'Organization' ) );
		$this->assertSame( 'Person', Animal_Shelter::refine_type( 'Person' ), 'a deliberate choice must be left alone' );
	}

	/**
	 * Slim SEO and The SEO Framework hand over a list of nodes.
	 */
	public function test_the_graph_shape_specialises_the_organization_node(): void {
		$graph = array(
			array( '@type' => 'WebSite' ),
			array(
				'@type' => 'Organization',
				'name'  => 'VCHS',
			),
			array( '@type' => 'WebPage' ),
		);

		$after = Animal_Shelter::refine_graph( $graph );

		$this->assertSame( array( 'WebSite', 'AnimalShelter', 'WebPage' ), array_column( $after, '@type' ) );
		$this->assertSame( 'VCHS', $after[1]['name'], 'the rest of the node must survive' );
	}

	public function test_the_graph_shape_handles_a_type_list(): void {
		$after = Animal_Shelter::refine_graph( array( array( '@type' => array( 'Organization', 'LocalBusiness' ) ) ) );

		$this->assertSame( array( 'AnimalShelter', 'LocalBusiness' ), $after[0]['@type'] );
	}

	public function test_a_graph_with_no_organization_is_returned_untouched(): void {
		$graph = array( array( '@type' => 'WebSite' ) );

		$this->assertSame( $graph, Animal_Shelter::refine_graph( $graph ) );
	}

	public function test_a_non_array_graph_is_returned_untouched(): void {
		$this->assertSame( 'nonsense', Animal_Shelter::refine_graph( 'nonsense' ) );
	}

	/**
	 * The case the whole three-way branch exists for: an SEO plugin we cannot
	 * work with means emitting nothing, because a second Organization is worse
	 * than no refinement at all.
	 */
	public function test_an_unrecognised_seo_plugin_suppresses_our_own_node(): void {
		$this->fill_profile();
		$this->enable();

		// Simulate Yoast being present.
		if ( ! defined( 'WPSEO_VERSION' ) ) {
			define( 'WPSEO_VERSION', '99.0' );
		}

		$this->assertTrue( Animal_Shelter::unknown_seo_plugin_active() );

		ob_start();
		Animal_Shelter::register();
		do_action( 'wp_head' );
		$html = (string) ob_get_clean();

		$this->assertStringNotContainsString( 'AnimalShelter', $html, 'a duplicate Organization is worse than no refinement' );
	}
}
