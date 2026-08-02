<?php
/**
 * Field resolution order: post meta, then the provider snapshot, then default.
 *
 * This is what lets a pet exist with no provider at all. Break it and either
 * hand-entered pets silently lose every field a sync would have supplied, or
 * — worse — imported pets start reading back values nobody entered.
 *
 * @package Shelter_Pets
 */

declare( strict_types = 1 );

namespace Petsync\Tests\Integration;

use Petsync\Core\Pet_Hydrator;

final class HydratorPrecedenceTest extends PetTestCase {

	public function test_manual_meta_wins_over_the_snapshot(): void {
		$id = $this->make_synced_pet( array( 'adoption_fee' => '250' ) );

		$entity = Pet_Hydrator::get( $id, 'full' );
		$this->assertSame( '250', $entity['adoption_fee'], 'snapshot value should apply when no meta is set' );

		update_post_meta( $id, $this->prefix . 'adoption_fee', '95' );
		Pet_Hydrator::flush_cache();

		$entity = Pet_Hydrator::get( $id, 'full' );
		$this->assertSame( '95', $entity['adoption_fee'], 'manual meta must take precedence' );
	}

	public function test_snapshot_is_used_when_no_meta_is_set(): void {
		$id = $this->make_synced_pet( array( 'weight' => '40 lbs' ) );

		$this->assertSame( '40 lbs', Pet_Hydrator::get( $id, 'full' )['weight'] );
	}

	public function test_default_is_used_when_neither_exists(): void {
		$id = $this->make_manual_pet();

		$entity = Pet_Hydrator::get( $id, 'full' );

		$this->assertSame( '', $entity['weight'], 'string field defaults to empty' );
		$this->assertSame( '', $entity['ok_with_dogs'], 'tristate with no data is empty, not "no"' );
	}

	/**
	 * WordPress cannot distinguish "no such meta" from "deliberately blanked",
	 * so an empty value is treated as absent. The consequence is deliberate:
	 * clearing a field on an imported pet reveals the provider's value again
	 * rather than blanking it, because the provider remains its source of
	 * record.
	 */
	public function test_empty_meta_falls_through_to_the_snapshot(): void {
		$id = $this->make_synced_pet( array( 'weight' => '40 lbs' ) );

		update_post_meta( $id, $this->prefix . 'weight', '' );
		Pet_Hydrator::flush_cache();

		$this->assertSame( '40 lbs', Pet_Hydrator::get( $id, 'full' )['weight'] );
	}

	public function test_a_hand_entered_pet_populates_every_editable_field(): void {
		$id = $this->make_manual_pet();

		$values = array(
			'adoption_fee'      => '175',
			'weight'            => '48 lbs',
			'microchip_id'      => 'CHIP-TEST',
			'ok_with_dogs'      => 'yes',
			'ok_with_cats'      => 'no',
			'housebroken'       => 'yes',
			'adoption_form_url' => 'https://example.org/apply',
		);

		foreach ( $values as $field => $value ) {
			update_post_meta( $id, $this->prefix . $field, $value );
		}
		Pet_Hydrator::flush_cache();

		$entity = Pet_Hydrator::get( $id, 'full' );

		foreach ( $values as $field => $value ) {
			$this->assertSame( $value, $entity[ $field ], "field {$field} should hydrate from manual meta" );
		}
	}

	/**
	 * The computed fields are the real proof: they derive from the editable
	 * ones, so if they populate the whole chain works rather than just storage.
	 */
	public function test_computed_fields_follow_manual_entry(): void {
		$id = $this->make_manual_pet();

		update_post_meta( $id, $this->prefix . 'adoption_fee', '175' );
		update_post_meta( $id, $this->prefix . 'ok_with_dogs', 'yes' );
		update_post_meta( $id, $this->prefix . 'ok_with_cats', 'no' );
		Pet_Hydrator::flush_cache();

		$entity = Pet_Hydrator::get( $id, 'full' );

		$this->assertSame( '$175', $entity['adoption_fee_formatted'] );
		$this->assertTrue( $entity['has_adoption_info'] );
		$this->assertSame( 'Good with dogs', $entity['compatibility'], 'a "no" must not be advertised' );
	}

	public function test_a_draft_pet_does_not_hydrate(): void {
		$id = $this->make_manual_pet( array( 'post_status' => 'draft' ) );

		$this->assertNull( Pet_Hydrator::get( $id, 'full' ) );
	}
}
