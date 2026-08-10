<?php
/**
 * A third provider, and the polarity trap.
 *
 * #40 proved the provider layer exists. This one is meant to prove it is
 * GENERAL rather than fitted to two similar platforms: RescueGroups carries
 * roughly 120 animal* fields, the only confirmed dogs/cats/kids triad of the
 * three surveyed, and a family of negations whose `true` means "not good with".
 *
 * The negation family is why #41 matters more than #40. This codebase has
 * already shipped a compatibility display that advertised the opposite of the
 * truth — 4838f0a, 22 of 93 published pets — and for an adoption site that is
 * the worst direction an error can run.
 *
 * Nothing here makes a network request. See tests/fixtures/README.md.
 *
 * @package ShelterKit_Pets
 */

declare( strict_types = 1 );

namespace Petsync\Tests\Integration;

use Petsync\Core\Config;
use Petsync\Core\Pet_Hydrator;
use Petsync\Core\Provider_Map;

final class RescueGroupsProviderTest extends PetTestCase {

	private const PROVIDER = 'rescuegroups';

	/**
	 * The fields whose `true` means NOT good with, and which must therefore
	 * never be the source of a positively-phrased canonical field.
	 *
	 * @var string[]
	 */
	private const NEGATIONS = array(
		'animalNoLargeDogs',
		'animalNoSmallDogs',
		'animalNoFemaleDogs',
		'animalNoMaleDogs',
		'animalNoCold',
		'animalNoHeat',
		'animalOlderKidsOnly',
	);

	public function set_up(): void {
		parent::set_up();
		Provider_Map::flush_cache();
		Pet_Hydrator::flush_cache();
	}

	/**
	 * @return array<string, mixed>
	 */
	private function fixture(): array {
		$raw = file_get_contents( PETSYNC_DIR . 'tests/fixtures/rescuegroups-pet.json' );
		$this->assertNotFalse( $raw, 'the RescueGroups fixture is missing' );

		$data = json_decode( (string) $raw, true );
		$this->assertIsArray( $data );

		return $data;
	}

	/**
	 * @param array<string, mixed> $overrides Fixture keys to replace.
	 */
	private function make_pet( array $overrides = array() ): int {
		$data = array_merge( $this->fixture(), $overrides );
		$id   = $this->make_manual_pet( array( 'post_title' => $data['animalName'] ) );

		update_post_meta( $id, $this->prefix . 'provider', self::PROVIDER );
		update_post_meta( $id, $this->prefix . 'ps_id', $data['animalID'] );
		update_post_meta( $id, $this->prefix . 'api_response', wp_json_encode( $data ) );

		Pet_Hydrator::flush_cache();

		return $id;
	}

	// ─── The polarity trap ──────────────────────────────────────────────────

	/**
	 * THE regression guard for 4838f0a, one layer earlier than the original fix.
	 *
	 * That bug was in display; this one guards the map. A pet recorded as NOT
	 * good with dogs and kids must hydrate to 'no' for both — and, critically,
	 * 'yes' for cats, because a polarity bug that flipped every value uniformly
	 * would still pass a fixture whose answers all agreed.
	 */
	public function test_a_pet_recorded_as_not_good_with_hydrates_as_no(): void {
		$hydrated = Pet_Hydrator::get( $this->make_pet(), 'full' );

		$this->assertSame( 'no', $hydrated['ok_with_dogs'], 'the shelter recorded NOT good with dogs' );
		$this->assertSame( 'no', $hydrated['ok_with_kids'], 'the shelter recorded NOT good with kids' );
		$this->assertSame( 'yes', $hydrated['ok_with_cats'], 'the shelter recorded good with cats' );
	}

	/**
	 * The display layer must agree with the data. This is the assertion that
	 * would have failed before 4838f0a.
	 */
	public function test_the_compatibility_summary_does_not_advertise_the_opposite(): void {
		$hydrated = Pet_Hydrator::get( $this->make_pet(), 'full' );
		$summary  = strtolower( (string) ( $hydrated['compatibility'] ?? '' ) );

		$this->assertStringNotContainsString( 'dog', $summary, 'a pet recorded as NOT good with dogs must not be summarised as good with them' );
		$this->assertStringNotContainsString( 'kid', $summary, 'a pet recorded as NOT good with kids must not be summarised as good with them' );
	}

	/**
	 * The specific mapping #41 was filed about. animalNoLargeDogs is a real
	 * field and it is finer-grained than ok_with_dogs — "not good with LARGE
	 * dogs" is not the claim "not good with dogs". Collapsing one into the other
	 * states something the shelter did not.
	 */
	public function test_no_negation_field_is_the_source_of_a_positive_field(): void {
		$sources = Provider_Map::field_keys( self::PROVIDER );

		foreach ( $sources as $field => $source ) {
			$this->assertNotContains(
				$source,
				self::NEGATIONS,
				"'$field' reads from '$source', whose true means NOT — mapping it positively advertises the opposite of the truth"
			);
		}
	}

	/**
	 * Belt and braces across every provider, not just this one: any source
	 * whose name begins No* must carry an explicit invert.
	 */
	public function test_any_negatively_named_source_declares_its_polarity(): void {
		$is_negation = static fn( string $source ): bool => 1 === preg_match( '/(^|[a-z])No[A-Z]/', $source );

		// No map currently reads a No* field, so the loop below would assert
		// nothing and pass forever. Prove the detector first: it must catch
		// every known negation and none of the positives it sits beside.
		foreach ( self::NEGATIONS as $negation ) {
			if ( 'animalOlderKidsOnly' === $negation ) {
				continue; // Negative in meaning, not in name — covered by the map-level guard above.
			}
			$this->assertTrue( $is_negation( $negation ), "the detector missed '$negation'" );
		}
		foreach ( array( 'animalOKWithDogs', 'animalHousetrained', 'animalNotHousetrainedReason', 'good_with_dogs', 'animalName' ) as $positive ) {
			$this->assertFalse( $is_negation( $positive ), "the detector wrongly flagged '$positive'" );
		}

		foreach ( Provider_Map::available() as $slug ) {
			foreach ( Provider_Map::field_keys( $slug ) as $field => $source ) {
				if ( $is_negation( $source ) ) {
					$this->assertTrue(
						Provider_Map::inverts( $slug, $field ),
						"providers/$slug.json maps '$field' from '$source', which reads as a negation but declares no invert"
					);
				}
			}
		}
	}

	// ─── The invert capability itself ───────────────────────────────────────

	public function test_inversion_flips_a_definite_answer(): void {
		$this->assertSame( 'no', Provider_Map::invert_tristate( 'yes' ) );
		$this->assertSame( 'yes', Provider_Map::invert_tristate( 'no' ) );
	}

	/**
	 * The opposite of "we do not know" is still "we do not know". Flipping it
	 * would manufacture a definite answer out of an absence — the same class of
	 * error as 4838f0a, in a quieter form.
	 */
	public function test_inversion_leaves_the_indefinite_answers_alone(): void {
		$this->assertSame( 'unknown', Provider_Map::invert_tristate( 'unknown' ) );
		$this->assertSame( '', Provider_Map::invert_tristate( '' ) );
	}

	/**
	 * Inversion has to work end to end, on a field declared in a map, or the
	 * capability is theatre. Built here rather than shipped in the RescueGroups
	 * map because RescueGroups' own triad is positively phrased — see the
	 * _unmapped_negations note in that file.
	 */
	public function test_a_declared_inversion_flips_a_hydrated_field(): void {
		$map = Provider_Map::get( self::PROVIDER );
		$this->assertFalse( Provider_Map::inverts( self::PROVIDER, 'ok_with_dogs' ), 'precondition: not inverted as shipped' );

		// A hypothetical provider that phrases the same fact negatively.
		$map['slug']                             = 'testinverted';
		$map['fields']['ok_with_dogs']['from']   = 'animalNoLargeDogs';
		$map['fields']['ok_with_dogs']['invert'] = true;
		$this->write_provider( 'testinverted', $map );

		$data = $this->fixture();
		$pet  = $this->make_manual_pet();
		update_post_meta( $pet, $this->prefix . 'provider', 'testinverted' );
		update_post_meta( $pet, $this->prefix . 'api_response', wp_json_encode( $data ) );
		Pet_Hydrator::flush_cache();

		// The fixture says animalNoLargeDogs: "Yes" — i.e. NOT good with them.
		$this->assertSame( 'Yes', $data['animalNoLargeDogs'] );
		$this->assertSame(
			'no',
			Pet_Hydrator::get( $pet, 'full' )['ok_with_dogs'],
			'a positive raw value on a negated field must hydrate to no'
		);
	}

	/**
	 * Inversion applies to the PROVIDER's value only. A staff member correcting
	 * a pet by hand writes our polarity, and flipping that would be 4838f0a
	 * re-entering through a different door.
	 */
	public function test_inversion_does_not_touch_hand_entered_meta(): void {
		$map                                     = Provider_Map::get( self::PROVIDER );
		$map['fields']['ok_with_dogs']['from']   = 'animalNoLargeDogs';
		$map['fields']['ok_with_dogs']['invert'] = true;
		$this->write_provider( 'testinverted', $map );

		$pet = $this->make_manual_pet();
		update_post_meta( $pet, $this->prefix . 'provider', 'testinverted' );
		update_post_meta( $pet, $this->prefix . 'api_response', wp_json_encode( $this->fixture() ) );
		update_post_meta( $pet, $this->prefix . 'ok_with_dogs', 'no' );
		Pet_Hydrator::flush_cache();

		$this->assertSame(
			'no',
			Pet_Hydrator::get( $pet, 'full' )['ok_with_dogs'],
			"a staff correction of 'no' must stay 'no'"
		);
	}

	// ─── Ordinary mapping ───────────────────────────────────────────────────

	public function test_it_hydrates_the_fields_petstablished_does_not_carry(): void {
		$hydrated = Pet_Hydrator::get( $this->make_pet(), 'full' );

		// The housing group added in #42 came from RescueGroups' vocabulary and
		// had no provider until now — Petstablished carries none of it.
		$this->assertSame( 'yes', $hydrated['yard_required'] );
		$this->assertSame( 'yes', $hydrated['fence_required'] );
		$this->assertSame( 'no', $hydrated['apartment_ok'] );
	}

	public function test_it_hydrates_the_ordinary_fields(): void {
		$hydrated = Pet_Hydrator::get( $this->make_pet(), 'full' );

		$this->assertSame( 'yes', $hydrated['has_special_needs'] );
		$this->assertSame( 'Needs daily joint supplement.', $hydrated['special_needs_detail'] );
		$this->assertSame( '985141000123456', $hydrated['microchip_id'] );
		$this->assertSame( '42', $hydrated['weight'] );
	}

	public function test_the_nested_picture_shape_resolves(): void {
		$this->assertSame(
			'https://example.test/bram-1.jpg',
			Provider_Map::first_image_url( $this->fixture(), self::PROVIDER )
		);
	}

	public function test_it_produces_the_same_entity_shape_as_the_other_providers(): void {
		$theirs = Pet_Hydrator::get( $this->make_pet(), 'full' );

		$ours = $this->make_manual_pet();
		update_post_meta( $ours, $this->prefix . 'provider', \Petsync_Sync::PROVIDER );
		Pet_Hydrator::flush_cache();

		$this->assertSame( array_keys( Pet_Hydrator::get( $ours, 'full' ) ), array_keys( $theirs ) );
	}

	/**
	 * Three providers now, and pruning must still only ever touch its own.
	 */
	public function test_a_petstablished_sync_does_not_prune_rescuegroups_pets(): void {
		$theirs = $this->make_pet();

		$ours = $this->make_manual_pet();
		update_post_meta( $ours, $this->prefix . 'provider', \Petsync_Sync::PROVIDER );
		update_post_meta( $ours, $this->prefix . 'ps_id', '999002' );

		$method  = new \ReflectionMethod( \Petsync_Sync::class, 'remove_stale_pets' );
		$removed = $method->invoke( new \Petsync_Sync(), array( array( 'id' => 5 ) ) );

		$this->assertSame( 'publish', get_post_status( $theirs ) );
		$this->assertSame( 'draft', get_post_status( $ours ) );
		$this->assertSame( 1, $removed );
	}

	// ─── The map itself ─────────────────────────────────────────────────────

	public function test_the_map_records_its_api_version_and_that_it_is_unverified(): void {
		$map = Provider_Map::get( self::PROVIDER );

		$this->assertSame( 'v2', $map['_api_version'] );
		$this->assertStringContainsString( 'unverified', $map['_status'] );
	}

	public function test_the_map_is_internally_consistent(): void {
		$entity = Config::get_path( 'entities', 'entities.vcps_pet', array() );
		$valid  = array_merge(
			array_keys( (array) ( $entity['api_fields'] ?? array() ) ),
			array_keys( (array) ( $entity['fields'] ?? array() ) )
		);

		foreach ( array_keys( Provider_Map::field_keys( self::PROVIDER ) ) as $field ) {
			$this->assertContains( $field, $valid, "the map names '$field', which no entity declares" );
		}

		$registered = array_keys( (array) Config::get_item( 'taxonomies', 'taxonomies', array() ) );
		foreach ( Provider_Map::taxonomies( self::PROVIDER ) as $source => $taxonomy ) {
			$this->assertContains( $taxonomy, $registered, "'$source' targets unregistered '$taxonomy'" );
		}
	}

	// ─── Helpers ────────────────────────────────────────────────────────────

	/**
	 * Write a throwaway provider map, removed after the test.
	 *
	 * @param string               $slug Provider slug.
	 * @param array<string, mixed> $map  Map contents.
	 */
	private function write_provider( string $slug, array $map ): void {
		$path = PETSYNC_DIR . 'config/providers/' . $slug . '.json';
		file_put_contents( $path, (string) wp_json_encode( $map ) );

		$this->temp_providers[] = $path;
		Provider_Map::flush_cache();
	}

	/** @var string[] */
	private array $temp_providers = array();

	public function tear_down(): void {
		foreach ( $this->temp_providers as $path ) {
			if ( file_exists( $path ) ) {
				unlink( $path );
			}
		}
		$this->temp_providers = array();
		Provider_Map::flush_cache();

		parent::tear_down();
	}
}
