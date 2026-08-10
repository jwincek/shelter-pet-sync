<?php
/**
 * A second provider, against fixtures.
 *
 * The point of #40 is not Adopt-a-Pet support — it cannot be shipped without an
 * account, and the gates on that issue say so. The point is that a second field
 * map is the only thing that makes a provider abstraction real. With one
 * provider, a hardcoded key and a config-driven one behave identically.
 *
 * Nothing here makes a network request. The fixture is hand-written from
 * publicly documented field names; see tests/fixtures/README.md.
 *
 * @package ShelterKit_Pets
 */

declare( strict_types = 1 );

namespace Petsync\Tests\Integration;

use Petsync\Core\Config;
use Petsync\Core\Pet_Hydrator;
use Petsync\Core\Provider_Map;

final class AdoptAPetProviderTest extends PetTestCase {

	private const PROVIDER = 'adoptapet';

	public function set_up(): void {
		parent::set_up();
		Provider_Map::flush_cache();
		Pet_Hydrator::flush_cache();
	}

	/**
	 * @return array<string, mixed>
	 */
	private function fixture(): array {
		$raw = file_get_contents( PETSYNC_DIR . 'tests/fixtures/adoptapet-pet.json' );
		$this->assertNotFalse( $raw, 'the Adopt-a-Pet fixture is missing' );

		$data = json_decode( (string) $raw, true );
		$this->assertIsArray( $data );

		return $data;
	}

	/**
	 * A pet stored exactly as a sync from this provider would store it.
	 */
	private function make_adoptapet_pet(): int {
		$data = $this->fixture();
		$id   = $this->make_manual_pet( array( 'post_title' => $data['pet_name'] ) );

		update_post_meta( $id, $this->prefix . 'provider', self::PROVIDER );
		update_post_meta( $id, $this->prefix . 'ps_id', $data['pet_id'] );
		update_post_meta( $id, $this->prefix . 'api_response', wp_json_encode( $data ) );

		Pet_Hydrator::flush_cache();

		return $id;
	}

	public function test_it_renames_fields_through_the_map(): void {
		$pet = $this->make_adoptapet_pet();

		$hydrated = Pet_Hydrator::get( $pet, 'full' );

		// post_title / post_content are columns, so they come from the map's
		// `post` section rather than from `fields`.
		$this->assertSame( 'pet_name', Provider_Map::post_keys( self::PROVIDER )['title'] );
		$this->assertSame( 'description', Provider_Map::post_keys( self::PROVIDER )['content'] );

		$this->assertSame( 'https://www.adoptapet.com/pet/884213', $hydrated['adoption_form_url'] );
	}

	/**
	 * resolve_tristate() already handles is_bool(), so Adopt-a-Pet's booleans
	 * normalise with no new code. Worth pinning: it is defensive design paying
	 * off by accident, and an accident can be refactored away.
	 */
	public function test_boolean_compatibility_normalises_without_new_code(): void {
		$hydrated = Pet_Hydrator::get( $this->make_adoptapet_pet(), 'full' );

		$this->assertSame( 'yes', $hydrated['ok_with_dogs'], 'true must normalise to yes' );
		$this->assertSame( 'no', $hydrated['ok_with_kids'], 'false must normalise to no' );
		$this->assertSame( 'yes', $hydrated['housebroken'], 'the string "yes" must normalise too' );
		$this->assertSame( 'yes', $hydrated['spayed_neutered'] );
		$this->assertSame( 'no', $hydrated['declawed'] );
	}

	/**
	 * THE gap this issue exists to close, and the one the compatibility blocks
	 * would misreport if it were fudged.
	 *
	 * The reference client reads good_with_dogs and good_with_kids and never
	 * cats. Our compatibility feature is a dogs/cats/kids triad, so the third
	 * leg is genuinely unknown — and an unknown must read as '' (never asked),
	 * not 'unknown' (asked, inconclusive), which pet-compatibility renders as
	 * "Ask us".
	 */
	public function test_the_unverified_cats_field_reads_as_never_asked(): void {
		$hydrated = Pet_Hydrator::get( $this->make_adoptapet_pet(), 'full' );

		$this->assertArrayHasKey( 'ok_with_cats', $hydrated );
		$this->assertSame(
			'',
			$hydrated['ok_with_cats'],
			'Adopt-a-Pet is not known to carry good_with_cats; claiming "Ask us" would invite calls about an assessment nobody made'
		);
	}

	/**
	 * Absent fields must fall to their declared defaults, not vanish. This
	 * fixture omits nine of them precisely to exercise that.
	 */
	public function test_fields_this_provider_lacks_fall_to_their_defaults(): void {
		$hydrated = Pet_Hydrator::get( $this->make_adoptapet_pet(), 'full' );

		foreach ( array( 'weight', 'microchip_id', 'numerical_age', 'adoption_fee', 'hypoallergenic', 'coat_pattern', 'secondary_color', 'tertiary_color', 'bonded_group_id' ) as $field ) {
			$this->assertArrayHasKey( $field, $hydrated, "'$field' disappeared rather than defaulting" );
			$this->assertSame( '', $hydrated[ $field ], "'$field' must default to empty" );
		}

		$this->assertSame( array(), $hydrated['bonded_pet_ids'] );
	}

	/**
	 * An Adopt-a-Pet pet must produce the same entity keys a Petstablished pet
	 * does. Differing key sets would mean every consumer needs to know which
	 * provider it is looking at, which is the coupling this layer removes.
	 */
	public function test_it_produces_the_same_entity_shape_as_a_petstablished_pet(): void {
		$theirs = Pet_Hydrator::get( $this->make_adoptapet_pet(), 'full' );

		$ours = $this->make_manual_pet();
		update_post_meta( $ours, $this->prefix . 'provider', \Petsync_Sync::PROVIDER );
		Pet_Hydrator::flush_cache();
		$ours = Pet_Hydrator::get( $ours, 'full' );

		$this->assertSame(
			array_keys( $ours ),
			array_keys( $theirs ),
			'the two providers must hydrate to the same entity shape, in the same order'
		);
	}

	public function test_the_nested_photo_shape_resolves(): void {
		$hydrated = Pet_Hydrator::get( $this->make_adoptapet_pet(), 'full' );

		$this->assertSame( 'https://example.test/marigold-1.jpg', $hydrated['image'] );
		$this->assertCount( 2, $hydrated['gallery'] );
	}

	// ─── Value translation ──────────────────────────────────────────────────

	public function test_it_translates_a_value_the_provider_spells_differently(): void {
		$values = Provider_Map::values( self::PROVIDER, 'sex' );

		$this->assertSame( 'Female', Provider_Map::apply_values( $values, 'f' ) );
		$this->assertSame( 'Male', Provider_Map::apply_values( $values, 'm' ) );
	}

	/**
	 * Adopt-a-Pet aggregates from many upstream shelter systems and warns of
	 * inconsistent formatting between them, so matching cannot be exact.
	 */
	public function test_matching_ignores_case_and_surrounding_whitespace(): void {
		$values = Provider_Map::values( self::PROVIDER, 'sex' );

		$this->assertSame( 'Female', Provider_Map::apply_values( $values, 'F' ) );
		$this->assertSame( 'Female', Provider_Map::apply_values( $values, ' f ' ) );
		$this->assertSame( 'Male', Provider_Map::apply_values( $values, 'M' ) );
	}

	/**
	 * Passing an unmatched value through is the deliberate choice: a surprise
	 * shows up as itself and can be added to the map, where blanking would
	 * discard real data and look exactly like a field never sent.
	 */
	public function test_an_unmatched_value_passes_through_rather_than_blanking(): void {
		$values = Provider_Map::values( self::PROVIDER, 'sex' );

		$this->assertSame( 'unspecified', Provider_Map::apply_values( $values, 'unspecified' ) );
		$this->assertSame( '', Provider_Map::apply_values( $values, '' ) );
	}

	/**
	 * A boolean must not be stringified by the value layer — resolve_tristate()
	 * distinguishes a real boolean from the string 'false', and casting here
	 * would destroy that.
	 */
	public function test_booleans_are_left_alone_by_value_translation(): void {
		$values = array( 'f' => 'Female' );

		$this->assertTrue( Provider_Map::apply_values( $values, true ) );
		$this->assertFalse( Provider_Map::apply_values( $values, false ) );
	}

	/**
	 * Meta is already in our vocabulary. Running it through a provider's value
	 * map would be wrong even where it happens to be harmless.
	 */
	public function test_hand_entered_meta_is_not_run_through_the_value_map(): void {
		$pet = $this->make_adoptapet_pet();
		update_post_meta( $pet, $this->prefix . 'housebroken', 'unknown' );
		Pet_Hydrator::flush_cache();

		$this->assertSame( 'unknown', Pet_Hydrator::get( $pet, 'full' )['housebroken'] );
	}

	/**
	 * The identity key and the image path were literals in the sync — `id` and
	 * `$data['images'][0]['image']['url']`. A provider spelling them differently
	 * is precisely what proves they are no longer literals.
	 */
	public function test_the_identity_key_and_image_path_come_from_the_map(): void {
		$this->assertSame( 'pet_id', Provider_Map::identity_key( self::PROVIDER ) );
		$this->assertSame( 'id', Provider_Map::identity_key( \Petsync_Sync::PROVIDER ) );

		$this->assertSame(
			'https://example.test/marigold-1.jpg',
			Provider_Map::first_image_url( $this->fixture(), self::PROVIDER ),
			'Adopt-a-Pet nests photos one level deep where Petstablished nests three'
		);
	}

	public function test_a_response_with_no_photos_yields_an_empty_url(): void {
		$this->assertSame( '', Provider_Map::first_image_url( array(), self::PROVIDER ) );
		$this->assertSame( '', Provider_Map::first_image_url( array( 'pet_photos' => array() ), self::PROVIDER ) );
		$this->assertSame( '', Provider_Map::first_image_url( array( 'pet_photos' => 'not-an-array' ), self::PROVIDER ) );
	}

	// ─── Provider scoping ───────────────────────────────────────────────────

	/**
	 * A Petstablished sync only knows which of ITS OWN records vanished
	 * upstream. An Adopt-a-Pet pet is legitimately absent from that response and
	 * must not be drafted. The scoping already exists; this proves it
	 * generalises to a provider it was not written against.
	 */
	public function test_a_petstablished_sync_does_not_prune_adopt_a_pet_pets(): void {
		$theirs = $this->make_adoptapet_pet();

		$ours = $this->make_manual_pet();
		update_post_meta( $ours, $this->prefix . 'provider', \Petsync_Sync::PROVIDER );
		update_post_meta( $ours, $this->prefix . 'ps_id', '999001' );

		// A Petstablished response that contains neither pet.
		// No setAccessible(): it has been a no-op since PHP 8.1 — this plugin's
		// floor — and is deprecated in 8.5, which CI at 8.1 would never flag.
		$method  = new \ReflectionMethod( \Petsync_Sync::class, 'remove_stale_pets' );
		$removed = $method->invoke( new \Petsync_Sync(), array( array( 'id' => 5 ) ) );

		$this->assertSame( 'publish', get_post_status( $theirs ), 'an Adopt-a-Pet pet must survive a Petstablished sync' );
		$this->assertSame( 'draft', get_post_status( $ours ), 'a Petstablished pet missing upstream must still be drafted' );
		$this->assertSame( 1, $removed );
	}

	// ─── The map itself ─────────────────────────────────────────────────────

	/**
	 * Every field name and taxonomy target in the map has to be real. The map is
	 * unverified against a live account, so this checks OUR half of it — the
	 * half we can actually be right about.
	 */
	public function test_the_map_is_internally_consistent(): void {
		$entity = Config::get_path( 'entities', 'entities.vcps_pet', array() );
		$valid  = array_merge(
			array_keys( (array) ( $entity['api_fields'] ?? array() ) ),
			array_keys( (array) ( $entity['fields'] ?? array() ) )
		);

		$this->assertContains( self::PROVIDER, Provider_Map::available() );

		foreach ( array_keys( Provider_Map::field_keys( self::PROVIDER ) ) as $field ) {
			$this->assertContains( $field, $valid, "the map names '$field', which no entity declares" );
		}

		$registered = array_keys( (array) Config::get_item( 'taxonomies', 'taxonomies', array() ) );
		foreach ( Provider_Map::taxonomies( self::PROVIDER ) as $source => $taxonomy ) {
			$this->assertContains( $taxonomy, $registered, "'$source' targets unregistered '$taxonomy'" );
		}
	}

	/**
	 * The map is honest about not being verified. If someone ships it to a
	 * shelter they should have to delete this line first.
	 */
	public function test_the_map_records_that_it_is_unverified(): void {
		$map = Provider_Map::get( self::PROVIDER );

		$this->assertArrayHasKey( '_status', $map );
		$this->assertStringContainsString( 'unverified', $map['_status'] );
	}
}
