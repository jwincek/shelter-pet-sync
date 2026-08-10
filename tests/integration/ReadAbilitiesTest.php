<?php
/**
 * The plugin's read surface.
 *
 * These five abilities are what every block on the front end goes through —
 * pet-card, pet-slider, pet-listing-grid, pet-gallery, the template helpers and
 * the block-bindings source all route here. Until now none of them had a direct
 * test, which #39 turns from untidy into a problem: it proposes handing them to
 * MCP clients as public tools.
 *
 * @package ShelterKit_Pets
 */

declare( strict_types = 1 );

namespace Petsync\Tests\Integration;

use Petsync\Core\CPT_Registry;

use function Petsync\Abilities\Pets\filter_pets;
use function Petsync\Abilities\Pets\get as get_pet;
use function Petsync\Abilities\Pets\get_filter_options;
use function Petsync\Abilities\Pets\list_pets;
use function Petsync\Abilities\Stats\get_adoption_stats;

final class ReadAbilitiesTest extends PetTestCase {

	public function set_up(): void {
		parent::set_up();

		foreach ( array( 'pets', 'stats' ) as $group ) {
			require_once PETSYNC_DIR . "includes/abilities/{$group}.php";
		}
	}

	/**
	 * A published pet with a species and, optionally, a compatibility claim.
	 *
	 * @param string      $animal Species term.
	 * @param string|null $compat Canonical compatibility field to set to 'yes'.
	 * @return int Post ID.
	 */
	private function make_listed_pet( string $animal = 'Dog', ?string $compat = null ): int {
		$id = $this->make_manual_pet();
		wp_set_object_terms( $id, $animal, 'pet_animal' );
		wp_set_object_terms( $id, 'available', 'pet_status' );

		if ( $compat ) {
			update_post_meta( $id, $this->prefix . $compat, 'yes' );
			CPT_Registry::sync_attribute_terms( $id );
		}

		return $id;
	}

	// ── get-pet ──────────────────────────────────────────────────────────────

	public function test_get_pet_returns_the_hydrated_entity(): void {
		$id  = $this->make_listed_pet();
		$pet = get_pet( array( 'id' => $id ) );

		$this->assertIsArray( $pet );
		$this->assertSame( $id, $pet['id'] );
		$this->assertSame( get_the_title( $id ), $pet['name'] );
	}

	/**
	 * A draft pet must not be readable through a public ability. The sync drafts
	 * withdrawn and adopted animals, so this is the difference between a public
	 * catalogue and a leak of records a shelter has taken down.
	 */
	public function test_get_pet_refuses_anything_not_published(): void {
		$id = $this->make_listed_pet();
		wp_update_post(
			array(
				'ID'          => $id,
				'post_status' => 'draft',
			)
		);

		$result = get_pet( array( 'id' => $id ) );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'not_found', $result->get_error_code() );
	}

	public function test_get_pet_refuses_a_post_that_is_not_a_pet(): void {
		$page = self::factory()->post->create( array( 'post_type' => 'page' ) );

		$this->assertInstanceOf( \WP_Error::class, get_pet( array( 'id' => $page ) ) );
	}

	public function test_get_pet_refuses_an_unknown_id(): void {
		$this->assertInstanceOf( \WP_Error::class, get_pet( array( 'id' => 999999 ) ) );
	}

	// ── list-pets ────────────────────────────────────────────────────────────

	public function test_list_pets_returns_the_declared_envelope(): void {
		$this->make_listed_pet();
		$result = list_pets( array( 'per_page' => 5 ) );

		foreach ( array( 'pets', 'total', 'page', 'totalPages' ) as $key ) {
			$this->assertArrayHasKey( $key, $result );
		}
		$this->assertIsArray( $result['pets'] );
		$this->assertIsInt( $result['total'] );
	}

	public function test_list_pets_paginates(): void {
		foreach ( range( 1, 5 ) as $ignored ) {
			$this->make_listed_pet();
		}

		$page1 = list_pets(
			array(
				'per_page' => 2,
				'page'     => 1,
			)
		);
		$page2 = list_pets(
			array(
				'per_page' => 2,
				'page'     => 2,
			)
		);

		$this->assertCount( 2, $page1['pets'] );
		$this->assertCount( 2, $page2['pets'] );
		$this->assertGreaterThanOrEqual( 5, $page1['total'] );
		$this->assertSame(
			array(),
			array_intersect( array_column( $page1['pets'], 'id' ), array_column( $page2['pets'], 'id' ) ),
			'pages must not overlap'
		);
	}

	public function test_list_pets_honours_exclude(): void {
		$keep = $this->make_listed_pet();
		$drop = $this->make_listed_pet();

		$ids = array_column(
			list_pets(
				array(
					'per_page' => 50,
					'exclude'  => array( $drop ),
				)
			)['pets'],
			'id'
		);

		$this->assertContains( $keep, $ids );
		$this->assertNotContains( $drop, $ids );
	}

	public function test_list_pets_filters_by_species(): void {
		$dog = $this->make_listed_pet( 'Dog' );
		$cat = $this->make_listed_pet( 'Cat' );

		$ids = array_column(
			list_pets(
				array(
					'per_page' => 50,
					'animal'   => 'dog',
				)
			)['pets'],
			'id'
		);

		$this->assertContains( $dog, $ids );
		$this->assertNotContains( $cat, $ids );
	}

	// ── filter-pets ──────────────────────────────────────────────────────────

	/**
	 * The integration nothing pinned until now: compatibility filtering is a
	 * tax_query against pet_attribute, and #49 changed where those terms come
	 * from. This asserts the ability and the term derivation still agree.
	 */
	public function test_filter_pets_finds_a_pet_by_compatibility(): void {
		$good  = $this->make_listed_pet( 'Dog', 'ok_with_cats' );
		$other = $this->make_listed_pet( 'Dog' );

		$result = filter_pets(
			array(
				'per_page'     => 50,
				'goodWithCats' => '1',
			)
		);
		$ids    = array_column( $result['pets'], 'id' );

		$this->assertContains( $good, $ids );
		$this->assertNotContains( $other, $ids, 'a pet with no claim must not match' );
	}

	/**
	 * 'no' and 'unknown' are non-empty strings. A pet known NOT to suit cats
	 * must never surface in a good-with-cats filter.
	 */
	public function test_filter_pets_excludes_an_explicit_no(): void {
		$id = $this->make_listed_pet( 'Dog' );
		update_post_meta( $id, $this->prefix . 'ok_with_cats', 'no' );
		CPT_Registry::sync_attribute_terms( $id );

		$ids = array_column(
			filter_pets(
				array(
					'per_page'     => 50,
					'goodWithCats' => '1',
				)
			)['pets'],
			'id'
		);

		$this->assertNotContains( $id, $ids );
	}

	public function test_filter_pets_returns_counts_alongside_results(): void {
		$this->make_listed_pet( 'Dog', 'ok_with_dogs' );

		$result = filter_pets( array( 'per_page' => 50 ) );

		$this->assertArrayHasKey( 'counts', $result );
		$this->assertIsArray( $result['counts'] );
	}

	// ── get-filter-options ───────────────────────────────────────────────────

	public function test_filter_options_report_terms_actually_in_use(): void {
		$this->make_listed_pet( 'Ferret' );

		$options = get_filter_options();

		$this->assertIsArray( $options );
		$flat = wp_json_encode( $options );
		$this->assertStringContainsString( 'Ferret', (string) $flat, 'a species in use should be offerable as a filter' );
	}

	// ── get-adoption-stats ───────────────────────────────────────────────────

	public function test_adoption_stats_count_available_pets_by_species(): void {
		$this->make_listed_pet( 'Dog' );
		$this->make_listed_pet( 'Dog' );
		$this->make_listed_pet( 'Cat' );

		$stats = get_adoption_stats( array( 'status' => 'available' ) );

		$this->assertIsArray( $stats );
		$this->assertArrayHasKey( 'species_counts', $stats );
		$this->assertSame( 2, $stats['species_counts']['Dog'] ?? null );
		$this->assertSame( 1, $stats['species_counts']['Cat'] ?? null );
	}

	/**
	 * A drafted pet is not adoptable and must not be counted, or the shelter
	 * advertises a number it cannot deliver.
	 */
	public function test_adoption_stats_ignore_unpublished_pets(): void {
		$live = $this->make_listed_pet( 'Dog' );
		$gone = $this->make_listed_pet( 'Dog' );
		wp_update_post(
			array(
				'ID'          => $gone,
				'post_status' => 'draft',
			)
		);

		$stats = get_adoption_stats( array( 'status' => 'available' ) );

		$this->assertSame( 1, $stats['species_counts']['Dog'] ?? null, "#$live should count, #$gone should not" );
	}

	// ── the contract every one of them publishes ─────────────────────────────
	// ── cursor pagination: a published mode with no internal caller ──────────

	/**
	 * list-pets has TWO return shapes. The paginated branch returns total /
	 * page / totalPages; the cursor branch returns hasMore and, when there is
	 * one, nextCursor. Nothing in the plugin ever passes a cursor, so the whole
	 * mode was unexercised — and #39 proposes handing this ability to agents,
	 * who may well reach for it.
	 */
	public function test_cursor_mode_walks_the_set_without_overlap(): void {
		foreach ( range( 1, 5 ) as $ignored ) {
			$this->make_listed_pet();
		}

		$first = list_pets(
			array(
				'per_page' => 2,
				'cursor'   => null,
			)
		);
		$page1 = list_pets( array( 'per_page' => 2 ) );
		$this->assertArrayNotHasKey( 'hasMore', $page1, 'the paginated branch has its own shape' );

		// Seed a cursor from the newest pet, then page with it.
		$newest = list_pets( array( 'per_page' => 2 ) )['pets'];
		$this->assertNotEmpty( $newest );

		$cursor = \Petsync_Helpers::encode_cursor(
			(int) $newest[0]['id'],
			get_post_field( 'post_date', (int) $newest[0]['id'] )
		);

		$result = list_pets(
			array(
				'per_page' => 2,
				'cursor'   => $cursor,
			)
		);

		$this->assertArrayHasKey( 'hasMore', $result );
		$this->assertIsBool( $result['hasMore'] );
		$this->assertNotContains(
			(int) $newest[0]['id'],
			array_column( $result['pets'], 'id' ),
			'a cursor must exclude the pet it points at'
		);
	}

	public function test_a_cursor_round_trips(): void {
		$id     = $this->make_listed_pet();
		$date   = get_post_field( 'post_date', $id );
		$cursor = \Petsync_Helpers::encode_cursor( $id, $date );

		$decoded = \Petsync_Helpers::decode_cursor( $cursor );

		$this->assertSame( $id, $decoded['id'] );
		$this->assertSame( $date, $decoded['date'] );
	}

	/**
	 * Cursors are HMAC-signed with wp_hash and checked with hash_equals, so a
	 * caller cannot hand-craft one to page into arbitrary date ranges. Nothing
	 * tested that the signature is actually enforced.
	 */
	public function test_a_tampered_cursor_is_rejected(): void {
		$id     = $this->make_listed_pet();
		$cursor = \Petsync_Helpers::encode_cursor( $id, get_post_field( 'post_date', $id ) );

		// Re-sign nothing: swap the payload, keep the original signature.
		$raw           = base64_decode( $cursor, true );
		list( , $sig ) = explode( '|', $raw, 2 );
		$forged        = base64_encode(
			wp_json_encode(
				array(
					'id'   => 1,
					'date' => '1970-01-01 00:00:00',
				)
			) . '|' . $sig
		);

		$this->assertNull( \Petsync_Helpers::decode_cursor( $forged ), 'a forged payload must not decode' );
		$this->assertNull( \Petsync_Helpers::decode_cursor( 'not-base64-at-all' ) );
		$this->assertNull( \Petsync_Helpers::decode_cursor( base64_encode( 'no-separator' ) ) );
	}

	/**
	 * An unusable cursor must fall back to normal listing rather than erroring
	 * or returning nothing — an agent passing a stale cursor should still get
	 * pets.
	 */
	public function test_an_invalid_cursor_falls_back_to_listing(): void {
		$this->make_listed_pet();

		$result = list_pets(
			array(
				'per_page' => 5,
				'cursor'   => 'garbage',
			)
		);

		$this->assertArrayHasKey( 'pets', $result );
		$this->assertNotEmpty( $result['pets'] );
	}

	// ── the contract every one of them publishes ─────────────────────────────

	/**
	 * #39 would expose these to agents, which read the declared output_schema.
	 * JSON Schema does not require a declared property to be present unless it
	 * is in `required` — none of these declare one — so this checks the weaker
	 * but real contract: whatever IS returned must match the declared type, and
	 * nothing undeclared may appear.
	 */
	public function test_returned_keys_are_declared_and_correctly_typed(): void {
		$abilities = \Petsync\Core\Config::get_item( 'abilities', 'abilities', array() );
		$this->make_listed_pet( 'Dog', 'ok_with_dogs' );

		$calls = array(
			'petsync/list-pets'          => static fn() => list_pets( array( 'per_page' => 2 ) ),
			'petsync/filter-pets'        => static fn() => filter_pets( array( 'per_page' => 2 ) ),
			'petsync/get-adoption-stats' => static fn() => get_adoption_stats( array() ),
		);

		$checkers = array(
			'integer' => 'is_int',
			'boolean' => 'is_bool',
			'string'  => 'is_string',
			'array'   => 'is_array',
			'object'  => 'is_array',
		);

		foreach ( $calls as $name => $call ) {
			$props = $abilities[ $name ]['output_schema']['properties'] ?? array();
			$this->assertNotEmpty( $props, "$name declares no output schema" );

			foreach ( $call() as $key => $value ) {
				$this->assertArrayHasKey( $key, $props, "$name returns '$key', which its schema does not declare" );

				$expected = $props[ $key ]['type'] ?? null;
				if ( isset( $checkers[ $expected ] ) ) {
					$this->assertTrue(
						$checkers[ $expected ]( $value ),
						"$name returns '$key' as " . get_debug_type( $value ) . ", schema says $expected"
					);
				}
			}
		}
	}
}
