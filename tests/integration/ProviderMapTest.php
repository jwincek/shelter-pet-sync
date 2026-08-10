<?php
/**
 * The entity says what a pet HAS. A provider map says what one platform CALLS
 * it. This pins the seam between them.
 *
 * Before #40 the entity carried Petstablished's spelling directly — `api_key`
 * on every field, plus `taxonomy_source_map` and `api_shapes` — so "what a pet
 * is" and "what one vendor calls it" were the same document, and a second
 * provider had nowhere to go.
 *
 * @package ShelterKit_Pets
 */

declare( strict_types = 1 );

namespace Petsync\Tests\Integration;

use Petsync\Core\Config;
use Petsync\Core\Pet_Hydrator;
use Petsync\Core\Provider_Map;

final class ProviderMapTest extends PetTestCase {

	public function set_up(): void {
		parent::set_up();
		Provider_Map::flush_cache();
	}

	public function test_it_resolves_a_renamed_field(): void {
		$this->assertSame(
			'is_ok_with_other_dogs',
			Provider_Map::key_for( \Petsync_Sync::PROVIDER, 'ok_with_dogs' )
		);
	}

	/**
	 * Null, not the field name. A map that fell back to the canonical spelling
	 * would silently claim every provider carries every field.
	 */
	public function test_a_field_the_provider_lacks_resolves_to_null(): void {
		foreach ( array( 'yard_required', 'fence_required', 'apartment_ok' ) as $field ) {
			$this->assertNull(
				Provider_Map::key_for( \Petsync_Sync::PROVIDER, $field ),
				"Petstablished does not carry '$field'; claiming it does would retain a key that never arrives"
			);
		}
	}

	/**
	 * THE decision this restructure had to get right.
	 *
	 * A field the provider does not carry must hydrate to '' — never 'unknown'.
	 * pet-compatibility skips '' (`if ( $status === '' ) { continue; }`) and
	 * renders 'unknown' as "Ask us"; pet-health does the same with "Unknown".
	 * Defaulting an absent field to 'unknown' would print "Cats: Ask us" on
	 * every pet from a provider that never asked, inviting the public to phone
	 * about an assessment that does not exist.
	 *
	 * '' means never asked. 'unknown' means asked and inconclusive. Only the
	 * shelter can produce the second.
	 */
	public function test_an_unmapped_field_hydrates_empty_not_unknown(): void {
		$pet = $this->make_manual_pet();
		update_post_meta( $pet, $this->prefix . 'provider', \Petsync_Sync::PROVIDER );
		Pet_Hydrator::flush_cache();

		$hydrated = Pet_Hydrator::get( $pet, 'full' );

		foreach ( array( 'yard_required', 'fence_required', 'apartment_ok' ) as $field ) {
			$this->assertArrayHasKey( $field, $hydrated, "dropping '$field' would make every consumer read an undefined index" );
			$this->assertSame( '', $hydrated[ $field ], "'$field' must read as never-asked, not as an inconclusive assessment" );
		}
	}

	/**
	 * A hand-authored pet has no provider and therefore no map. Every value
	 * comes from post meta, so there is nothing to translate — and nothing may
	 * vanish for want of a mapping.
	 */
	public function test_a_pet_with_no_provider_still_hydrates_every_field(): void {
		$pet = $this->make_manual_pet();
		update_post_meta( $pet, $this->prefix . 'weight', '31 lb' );
		update_post_meta( $pet, $this->prefix . 'ok_with_dogs', 'yes' );
		Pet_Hydrator::flush_cache();

		$this->assertSame( '', Provider_Map::for_pet( $pet ) );
		$this->assertSame( array(), Provider_Map::get( '' ) );

		$hydrated   = Pet_Hydrator::get( $pet, 'full' );
		$api_fields = array_keys( Config::get_path( 'entities', 'entities.vcps_pet.api_fields', array() ) );

		foreach ( $api_fields as $field ) {
			$this->assertArrayHasKey( $field, $hydrated, "field '$field' disappeared for a pet with no provider" );
		}
		$this->assertSame( '31 lb', $hydrated['weight'] );
		$this->assertSame( 'yes', $hydrated['ok_with_dogs'] );
	}

	/**
	 * Every canonical name a map claims must be a real entity field. A typo
	 * there is invisible at runtime: the hydrator never finds the field, and
	 * the result is indistinguishable from a provider that lacks it.
	 */
	public function test_every_mapped_field_is_a_declared_entity_field(): void {
		$entity = Config::get_path( 'entities', 'entities.vcps_pet', array() );
		$valid  = array_merge(
			array_keys( (array) ( $entity['api_fields'] ?? array() ) ),
			array_keys( (array) ( $entity['fields'] ?? array() ) )
		);

		foreach ( Provider_Map::available() as $slug ) {
			foreach ( array_keys( Provider_Map::field_keys( $slug ) ) as $field ) {
				$this->assertContains( $field, $valid, "providers/$slug.json maps '$field', which no entity declares" );
			}
		}
	}

	public function test_every_mapped_taxonomy_is_registered(): void {
		$registered = array_keys( (array) Config::get_item( 'taxonomies', 'taxonomies', array() ) );

		foreach ( Provider_Map::available() as $slug ) {
			foreach ( Provider_Map::taxonomies( $slug ) as $source => $taxonomy ) {
				$this->assertContains(
					$taxonomy,
					$registered,
					"providers/$slug.json sends '$source' to '$taxonomy', which is not registered — wp_set_object_terms() would write nothing"
				);
			}
		}
	}

	/**
	 * The entity must not drift back into carrying one vendor's vocabulary.
	 * That coupling is what #33 removed and what this file exists to hold open.
	 */
	public function test_the_entity_carries_no_provider_spelling(): void {
		$entity = Config::get_path( 'entities', 'entities.vcps_pet', array() );

		$this->assertArrayNotHasKey( 'taxonomy_source_map', $entity );
		$this->assertArrayNotHasKey( 'api_shapes', $entity );

		foreach ( (array) ( $entity['api_fields'] ?? array() ) as $field => $cfg ) {
			$this->assertArrayNotHasKey(
				'api_key',
				(array) $cfg,
				"api_fields.$field carries an api_key — provider spelling belongs in config/providers/"
			);
		}
	}

	/**
	 * A slug reaches Provider_Map from post meta, which is not necessarily
	 * something the plugin wrote.
	 */
	public function test_a_hostile_slug_cannot_escape_the_providers_directory(): void {
		foreach ( array( '../../entities', '../entities', 'petstablished/../../entities', "petstablished\0" ) as $slug ) {
			$this->assertSame( array(), Provider_Map::get( $slug ), "slug '$slug' resolved to a file" );
		}
	}

	public function test_the_sync_provider_has_a_map(): void {
		$this->assertContains(
			\Petsync_Sync::PROVIDER,
			Provider_Map::available(),
			'the provider the sync talks to must have a map, or every synced pet hydrates blank'
		);
	}
}
