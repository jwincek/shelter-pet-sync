<?php
/**
 * Provider response shapes declared in config rather than written into code.
 *
 * An api_key names one flat key. Petstablished nests photo URLs three levels
 * deep (images[].image.url), and Petfinder v2 — the API this plugin was
 * originally built against — nests almost everything. Those shapes used to be
 * literals inside compute methods, so a provider changing its structure meant
 * editing PHP, and the ?? fallbacks meant it failed to a blank image rather
 * than an error.
 *
 * They now live in config/providers/<slug>.json rather than on the entity: a
 * response shape is one platform's business, not part of what a pet is.
 *
 * @package ShelterKit_Pets
 */

declare( strict_types = 1 );

namespace Petsync\Tests\Integration;

use Petsync\Core\Pet_Hydrator;
use ReflectionMethod;

final class ApiShapesTest extends PetTestCase {

	/**
	 * @param array<mixed> $data Data to walk.
	 * @param array<mixed> $path Path segments.
	 * @return mixed
	 */
	private function dig( array $data, array $path ) {
		return ( new ReflectionMethod( Pet_Hydrator::class, 'dig' ) )->invoke( null, $data, $path );
	}

	public function test_it_walks_a_nested_path(): void {
		$data = array( 'images' => array( array( 'image' => array( 'url' => 'https://example.test/a.jpg' ) ) ) );

		$this->assertSame(
			'https://example.test/a.jpg',
			$this->dig( $data, array( 'images', 0, 'image', 'url' ) )
		);
	}

	public function test_a_missing_segment_yields_null_rather_than_a_warning(): void {
		$this->assertNull( $this->dig( array( 'images' => array() ), array( 'images', 0, 'image', 'url' ) ) );
		$this->assertNull( $this->dig( array(), array( 'images' ) ) );
		$this->assertNull( $this->dig( array( 'images' => 'not-an-array' ), array( 'images', 0 ) ) );
	}

	public function test_an_empty_path_returns_the_whole_structure(): void {
		$data = array( 'a' => 1 );
		$this->assertSame( $data, $this->dig( $data, array() ) );
	}

	/**
	 * The declared shape has to match what the provider actually sends, or every
	 * pet silently loses its photos. This pins the two against each other.
	 */
	public function test_the_declared_image_shape_matches_a_real_response(): void {
		$shape = \Petsync\Core\Provider_Map::shapes( \Petsync_Sync::PROVIDER )['images'] ?? array();

		$this->assertNotEmpty( $shape['list'] ?? array() );
		$this->assertNotEmpty( $shape['url'] ?? array() );

		// A response shaped the way Petstablished sends one.
		$response = array(
			'images' => array(
				array( 'image' => array( 'url' => 'https://example.test/1.jpg' ) ),
				array( 'image' => array( 'url' => 'https://example.test/2.jpg' ) ),
			),
		);

		$list = $this->dig( $response, $shape['list'] );
		$this->assertCount( 2, $list, 'the list path must find the photo array' );
		$this->assertSame(
			'https://example.test/1.jpg',
			$this->dig( $list[0], $shape['url'] ),
			'the url path must find the URL inside an entry'
		);
	}

	/**
	 * date_aquired is the provider's own misspelling of "acquired". If they ever
	 * correct it the created_at fallback absorbs the change — but only while the
	 * fallback is actually declared.
	 */
	public function test_the_intake_date_declares_a_fallback(): void {
		$paths = \Petsync\Core\Provider_Map::shapes( \Petsync_Sync::PROVIDER )['intake_date']['paths'] ?? array();

		$this->assertGreaterThanOrEqual( 2, count( $paths ), 'a single path leaves no fallback' );
		$flat = array_map( static fn( $p ) => implode( '.', (array) $p ), $paths );
		$this->assertContains( 'date_aquired', $flat );
		$this->assertContains( 'created_at', $flat );
	}

	/**
	 * Both shapes are read for every synced pet, so every provider owes both.
	 * Adding a provider file without them yields blank images and pets that are
	 * never "new" — silently, and only on a site with that provider's data.
	 */
	public function test_every_provider_declares_both_shapes(): void {
		$providers = \Petsync\Core\Provider_Map::available();
		$this->assertNotEmpty( $providers, 'no provider maps on disk — nothing could hydrate' );

		foreach ( $providers as $slug ) {
			$shapes = \Petsync\Core\Provider_Map::shapes( $slug );
			$this->assertNotEmpty( $shapes['images']['list'] ?? array(), "$slug declares no images.list" );
			$this->assertNotEmpty( $shapes['images']['url'] ?? array(), "$slug declares no images.url" );
			$this->assertNotEmpty( $shapes['intake_date']['paths'] ?? array(), "$slug declares no intake_date.paths" );
		}
	}

	/**
	 * The whole point of part 2: no compute method may name a provider key.
	 */
	public function test_no_literal_provider_key_survives_in_the_hydrator(): void {
		foreach ( array( 'includes/core/class-pet-hydrator.php', 'includes/class-petsync-helpers.php' ) as $rel ) {
			$src = (string) file_get_contents( PETSYNC_DIR . $rel );
			$this->assertSame(
				0,
				preg_match_all( "/api_data\[\s*'/", $src ),
				"$rel still reads a literal provider key; it should come from config"
			);
		}
	}
}
