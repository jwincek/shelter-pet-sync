<?php
/**
 * A computed field must mean the same thing in every shape.
 *
 * The bug: `summary` and `grid` request the computed `is_bonded_pair` but not
 * `bonded_group_id`, the api_field it is derived from. Hydration filtered
 * api_fields to the shape BEFORE running the computed loop, so the computed
 * field derived from a partial entity and every bonded pet in a listing grid,
 * slider or favourites modal came out not-bonded and lost its badge — while the
 * same pet on its own page, hydrated `full`, showed it correctly.
 *
 * Introduced by 69ab745 (#51), which moved compute_is_bonded_pair() from reading
 * $api_data['group_id'] — always present — to reading $entity['bonded_group_id'],
 * which is shape-filtered. That change was right about removing the literal
 * provider key and silently wrong about where the data comes from.
 *
 * Fixed structurally: api_fields hydrate in full and the OUTPUT is narrowed at
 * the end, so a computed field always sees a complete entity. These tests hold
 * that open, and the last one generalises it beyond the one field that broke.
 *
 * @package ShelterKit_Pets
 */

declare( strict_types = 1 );

namespace Petsync\Tests\Integration;

use Petsync\Core\Config;
use Petsync\Core\Pet_Hydrator;

final class HydrationShapesTest extends PetTestCase {

	/** @var string[] */
	private const SHAPES = array( 'full', 'summary', 'grid' );

	/**
	 * Two pets sharing a provider group, as a sync would leave them.
	 *
	 * @return int[] The two post IDs.
	 */
	private function make_bonded_pair(): array {
		$a = $this->make_manual_pet( array( 'post_title' => 'Elmira' ) );
		$b = $this->make_manual_pet( array( 'post_title' => 'Bram' ) );

		foreach ( array(
			$a => 5001,
			$b => 5002,
		) as $id => $ps_id ) {
			$partner = ( 5001 === $ps_id ) ? 5002 : 5001;

			update_post_meta( $id, $this->prefix . 'provider', \Petsync_Sync::PROVIDER );
			update_post_meta( $id, $this->prefix . 'ps_id', (string) $ps_id );
			update_post_meta(
				$id,
				$this->prefix . 'api_response',
				(string) wp_json_encode(
					array(
						'id'              => $ps_id,
						'name'            => get_the_title( $id ),
						'group_id'        => 44488,
						'grouped_pet_ids' => array( $ps_id, $partner ),
					)
				)
			);
		}

		Pet_Hydrator::flush_cache();

		return array( $a, $b );
	}

	/**
	 * THE regression guard. A badge that shows on the pet's own page and not in
	 * the listing grid is the exact symptom this was reported as.
	 */
	public function test_a_bonded_pet_is_bonded_in_every_shape(): void {
		[ $a ] = $this->make_bonded_pair();

		foreach ( self::SHAPES as $shape ) {
			Pet_Hydrator::flush_cache();
			$entity = Pet_Hydrator::get( $a, $shape );

			$this->assertTrue(
				$entity['is_bonded_pair'],
				"is_bonded_pair is false in '$shape' — the badge disappears from every block using that shape"
			);
		}
	}

	public function test_the_partner_resolves_in_every_shape(): void {
		[ $a ] = $this->make_bonded_pair();

		foreach ( self::SHAPES as $shape ) {
			Pet_Hydrator::flush_cache();
			$names = (array) Pet_Hydrator::get( $a, $shape )['bonded_pair_names'];

			$this->assertCount( 1, $names, "no partner resolved in '$shape'" );
			$this->assertSame( 'Bram', $names[0]['name'] );
		}
	}

	public function test_a_pet_with_no_group_is_not_bonded_in_any_shape(): void {
		$lone = $this->make_manual_pet();
		Pet_Hydrator::flush_cache();

		foreach ( self::SHAPES as $shape ) {
			Pet_Hydrator::flush_cache();
			$this->assertFalse(
				Pet_Hydrator::get( $lone, $shape )['is_bonded_pair'],
				"a pet with no group must not be bonded in '$shape'"
			);
		}
	}

	/**
	 * Hydrating every api_field must not widen what a shape RETURNS. The
	 * narrowing moved to the end of hydration; if it were dropped, `grid` would
	 * quietly start returning all 62 fields to every card on an archive page.
	 */
	public function test_a_shape_returns_only_its_declared_fields(): void {
		$pet    = $this->make_manual_pet();
		$entity = Config::get_path( 'entities', 'entities.vcps_pet', array() );

		foreach ( array(
			'summary' => 'summary_fields',
			'grid'    => 'grid_fields',
		) as $shape => $key ) {
			Pet_Hydrator::flush_cache();
			$declared = (array) ( $entity[ $key ] ?? array() );
			$returned = array_keys( Pet_Hydrator::get( $pet, $shape ) );

			// The …Slug twins ride along with their taxonomy name by design.
			$extra = array_values( array_diff( $returned, $declared ) );
			foreach ( $extra as $field ) {
				$this->assertStringEndsWith(
					'Slug',
					$field,
					"'$shape' returned '$field', which it does not declare"
				);
			}

			$this->assertSame(
				array(),
				array_diff( $declared, $returned ),
				"'$shape' declares fields it did not return"
			);
		}
	}

	/**
	 * The bug introduced while fixing the bug: narrowing the output with a plain
	 * intersection dropped every `…Slug` twin, which the listing grid filters
	 * on. Silent — the cards render, the filters stop matching.
	 */
	public function test_the_taxonomy_slug_twins_survive_narrowing(): void {
		$pet = $this->make_manual_pet();
		wp_set_object_terms( $pet, 'Cat', 'pet_animal' );
		wp_set_object_terms( $pet, 'Available', 'pet_status' );

		foreach ( array( 'summary', 'grid' ) as $shape ) {
			Pet_Hydrator::flush_cache();
			$entity = Pet_Hydrator::get( $pet, $shape );

			$this->assertSame( 'cat', $entity['animalSlug'] ?? null, "animalSlug missing from '$shape'" );
			$this->assertSame( 'available', $entity['statusSlug'] ?? null, "statusSlug missing from '$shape'" );
		}
	}

	/**
	 * The general invariant, beyond the one field that broke: a computed field
	 * a shape requests must resolve to the SAME value it does in `full`. If it
	 * does not, the shape is filtering away something it derives from.
	 *
	 * This is what would have caught the original bug without anyone knowing to
	 * look at bonded pairs.
	 */
	public function test_every_computed_field_agrees_between_its_shape_and_full(): void {
		[ $a ] = $this->make_bonded_pair();
		wp_set_object_terms( $a, 'Dog', 'pet_animal' );
		update_post_meta( $a, $this->prefix . 'ok_with_dogs', 'yes' );
		update_post_meta( $a, $this->prefix . 'has_special_needs', 'yes' );

		$entity   = Config::get_path( 'entities', 'entities.vcps_pet', array() );
		$computed = array_keys( (array) ( $entity['computed'] ?? array() ) );
		$this->assertNotEmpty( $computed );

		Pet_Hydrator::flush_cache();
		$full = Pet_Hydrator::get( $a, 'full' );

		$checked = 0;
		foreach ( array(
			'summary' => 'summary_fields',
			'grid'    => 'grid_fields',
		) as $shape => $key ) {
			Pet_Hydrator::flush_cache();
			$narrow = Pet_Hydrator::get( $a, $shape );

			foreach ( (array) ( $entity[ $key ] ?? array() ) as $field ) {
				if ( ! in_array( $field, $computed, true ) ) {
					continue;
				}
				$this->assertEquals(
					$full[ $field ],
					$narrow[ $field ],
					"computed '$field' differs between 'full' and '$shape' — that shape omits something it is derived from"
				);
				++$checked;
			}
		}

		$this->assertGreaterThan( 5, $checked, 'the loop must actually compare something' );
	}
}
