<?php
/**
 * pet_attribute terms are what the archive's compatibility filter queries.
 *
 * The front end filters with a tax_query, not a meta_query, so a pet whose
 * field says "good with dogs" but which carries no `good-with-dogs` term is
 * invisible to that filter while its own detail page advertises the opposite.
 *
 * Two ways that used to be possible, because the term and the field were
 * derived independently:
 *
 *   - the sync read RAW provider data behind an is_string() guard, so any
 *     provider sending real JSON booleans applied no terms at all;
 *   - nothing derived terms for hand-entered pets, because the sync was the
 *     only caller.
 *
 * @package ShelterKit_Pets
 */

declare( strict_types = 1 );

namespace Petsync\Tests\Integration;

use Petsync\Core\CPT_Registry;
use Petsync\Core\Pet_Hydrator;

final class AttributeTermsTest extends PetTestCase {

	/**
	 * @param int $id Pet ID.
	 * @return string[] pet_attribute term slugs.
	 */
	private function terms_of( int $id ): array {
		$t = wp_get_object_terms( $id, 'pet_attribute', array( 'fields' => 'slugs' ) );

		return is_wp_error( $t ) ? array() : $t;
	}

	/**
	 * The bug this change exists to fix: a shelter with no provider fills in the
	 * compatibility controls and the pet never reaches the filtered archive.
	 */
	public function test_a_hand_entered_pet_gets_its_attribute_terms(): void {
		$id = $this->make_manual_pet();
		update_post_meta( $id, $this->prefix . 'ok_with_dogs', 'yes' );

		CPT_Registry::sync_attribute_terms( $id );

		$this->assertContains( 'good-with-dogs', $this->terms_of( $id ) );
	}

	/**
	 * 'no' and 'unknown' are non-empty strings, so an emptiness test would label
	 * a pet compatible with everything it is known NOT to suit. This is the
	 * shape of a bug this codebase has already shipped once.
	 */
	public function test_only_an_explicit_yes_earns_a_term(): void {
		foreach ( array( 'no', 'unknown', '' ) as $value ) {
			$id = $this->make_manual_pet();
			update_post_meta( $id, $this->prefix . 'ok_with_cats', $value );

			CPT_Registry::sync_attribute_terms( $id );

			$this->assertNotContains(
				'good-with-cats',
				$this->terms_of( $id ),
				"a value of '$value' must not read as compatible"
			);
		}
	}

	/**
	 * The reason for deriving from the hydrated entity rather than raw provider
	 * data: resolve_tristate() already absorbs booleans, so a provider sending
	 * true/false is handled for free. The old path guarded on is_string() and
	 * silently applied nothing.
	 */
	public function test_a_boolean_source_value_still_earns_a_term(): void {
		$id = $this->make_manual_pet();
		update_post_meta( $id, $this->prefix . 'housebroken', true );

		CPT_Registry::sync_attribute_terms( $id );

		$this->assertContains( 'housebroken', $this->terms_of( $id ) );
	}

	public function test_terms_are_replaced_not_appended(): void {
		$id = $this->make_manual_pet();
		update_post_meta( $id, $this->prefix . 'declawed', 'yes' );
		CPT_Registry::sync_attribute_terms( $id );
		$this->assertContains( 'declawed', $this->terms_of( $id ) );

		update_post_meta( $id, $this->prefix . 'declawed', 'no' );
		CPT_Registry::sync_attribute_terms( $id );

		$this->assertNotContains( 'declawed', $this->terms_of( $id ), 'a retracted claim must drop its term' );
	}

	/**
	 * The field and the term are now one derivation, so they cannot disagree.
	 * This asserts that across every mapped field at once.
	 */
	public function test_no_field_can_claim_what_the_taxonomy_denies(): void {
		$entity = \Petsync\Core\Config::get_path( 'entities', 'entities.vcps_pet', array() );
		$map    = $entity['attribute_terms'] ?? array();
		$this->assertNotEmpty( $map );

		$id = $this->make_manual_pet();
		foreach ( array_keys( $map ) as $i => $field ) {
			update_post_meta( $id, $this->prefix . $field, 0 === $i % 2 ? 'yes' : 'no' );
		}

		CPT_Registry::sync_attribute_terms( $id );

		Pet_Hydrator::flush_cache();
		$pet   = Pet_Hydrator::get( $id, 'full' );
		$terms = $this->terms_of( $id );

		foreach ( $map as $field => $slug ) {
			$claims = 'yes' === strtolower( (string) ( $pet[ $field ] ?? '' ) );
			$this->assertSame(
				$claims,
				in_array( $slug, $terms, true ),
				"field '$field' and term '$slug' disagree"
			);
		}
	}

	/**
	 * attribute_terms is keyed on our field names, not the provider's. Keying it
	 * on api_keys would reintroduce the coupling this replaced.
	 */
	public function test_the_map_is_keyed_on_canonical_fields(): void {
		$entity = \Petsync\Core\Config::get_path( 'entities', 'entities.vcps_pet', array() );
		$fields = array_keys( $entity['api_fields'] ?? array() );

		foreach ( array_keys( $entity['attribute_terms'] ?? array() ) as $key ) {
			$this->assertContains( $key, $fields, "'$key' is not a declared entity field" );
		}
	}
}
