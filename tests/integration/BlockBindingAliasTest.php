<?php
/**
 * Block-binding keys are stored data.
 *
 * A binding serialises its field name into the post:
 *
 *   <!-- wp:paragraph {"metadata":{"bindings":{"content":{
 *        "source":"petsync/pet-data","args":{"key":"special_needs"}}}}} -->
 *
 * so renaming a canonical field silently empties every block already bound to
 * it. Nothing throws; the block just renders nothing, on someone else's site,
 * weeks later. These pin the alias that makes that impossible.
 *
 * @package ShelterKit_Pets
 */

declare( strict_types = 1 );

namespace Petsync\Tests\Integration;

use Petsync_Blocks;
use ReflectionMethod;

final class BlockBindingAliasTest extends PetTestCase {

	/**
	 * @param string $key Binding key as written in post content.
	 * @return string Resolved canonical field name.
	 */
	private function resolve( string $key ): string {
		$m = new ReflectionMethod( Petsync_Blocks::class, 'resolve_legacy_binding_key' );

		return $m->invoke( null, $key );
	}

	public function test_a_legacy_binding_key_resolves_to_the_current_field(): void {
		$this->assertSame(
			'has_special_needs',
			$this->resolve( 'special_needs' ),
			'content bound before the #42 rename must keep resolving'
		);
	}

	public function test_current_keys_pass_through_untouched(): void {
		foreach ( array( 'has_special_needs', 'special_needs_detail', 'name', 'status' ) as $key ) {
			$this->assertSame( $key, $this->resolve( $key ) );
		}
	}

	public function test_an_unknown_key_is_returned_as_given(): void {
		$this->assertSame( 'not_a_field', $this->resolve( 'not_a_field' ) );
	}

	/**
	 * The alias table's keys are, by definition, names that exist nowhere else
	 * in the codebase — which is exactly what makes them vulnerable to the next
	 * blanket search-and-replace. This asserts the mapping is a real
	 * indirection and not a no-op, which is the shape it degrades into when a
	 * rename script rewrites both sides.
	 */
	public function test_no_alias_maps_a_key_to_itself(): void {
		$m = new ReflectionMethod( Petsync_Blocks::class, 'resolve_legacy_binding_key' );

		foreach ( array( 'special_needs' ) as $legacy ) {
			$this->assertNotSame(
				$legacy,
				$m->invoke( null, $legacy ),
				"the alias for '$legacy' has collapsed into a no-op"
			);
		}
	}

	/**
	 * The renamed field still has to be a real field, or the alias points at
	 * nothing and the binding renders empty anyway.
	 */
	public function test_the_alias_target_is_a_declared_field(): void {
		$entity = \Petsync\Core\Config::get_path( 'entities', 'entities.vcps_pet', array() );
		$fields = array_merge(
			array_keys( $entity['fields'] ?? array() ),
			array_keys( $entity['api_fields'] ?? array() ),
			array_keys( $entity['computed'] ?? array() )
		);

		$this->assertContains( 'has_special_needs', $fields );
		$this->assertNotContains( 'special_needs', $fields, 'the old name must be gone from the entity' );
	}
}
