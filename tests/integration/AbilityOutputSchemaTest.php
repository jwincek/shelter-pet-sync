<?php
/**
 * Every ability's output must satisfy the schema it advertises.
 *
 * WordPress 7.1 added WP_Ability::validate_output(), which checks an ability's
 * return value against its own `output_schema` and turns a mismatch into an
 * `ability_invalid_output` WP_Error. In 6.9 — the version this plugin declares
 * as its floor — that method did not exist and the schemas were decorative.
 * All twelve abilities here declare one, so twelve contracts that were never
 * enforced became enforced by a WordPress release.
 *
 * The existing ability tests cannot see this. They call the handler functions
 * directly — get_pet( array( 'id' => $id ) ) — which is the fast way to test
 * the logic and bypasses core entirely. Nothing in the suite went through
 * WP_Ability::execute(), so nothing exercised the validation that now runs for
 * every MCP and REST caller.
 *
 * This test takes the caller's path instead.
 *
 * @package ShelterKit_Pets
 */

declare( strict_types = 1 );

namespace Petsync\Tests\Integration;

final class AbilityOutputSchemaTest extends PetTestCase {

	private int $pet;

	public function set_up(): void {
		parent::set_up();

		// Nothing is registered by hand here. WP_Abilities_Registry::get_instance()
		// fires wp_abilities_api_init on first access, and the plugin is hooked to
		// it, so touching the registry registers the abilities through the plugin's
		// own path — which is also what avoids core's "abilities must be registered
		// on wp_abilities_api_init" notice that the rest of the suite works around
		// by never registering at all.

		$this->pet = $this->make_manual_pet( array( 'post_title' => 'Schema Subject' ) );
		wp_set_object_terms( $this->pet, 'Dog', 'pet_animal' );
		wp_set_object_terms( $this->pet, 'available', 'pet_status' );

		// The one ability gated on edit_posts needs a user who has it; the rest
		// are public and are unaffected by being logged in.
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
	}

	/**
	 * A representative input per ability.
	 *
	 * Deliberately exhaustive rather than discovered: an ability with no entry
	 * fails the coverage test below, so adding a thirteenth ability forces a
	 * decision about how to exercise it instead of silently skipping it.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function inputs(): array {
		return array(
			'petsync/get-pet'             => array( 'id' => $this->pet ),
			'petsync/list-pets'           => array(),
			'petsync/filter-pets'         => array(),
			'petsync/batch-get-pets'      => array( 'ids' => array( $this->pet ) ),
			'petsync/get-filter-options'  => array(),
			'petsync/toggle-favorite'     => array( 'id' => $this->pet ),
			'petsync/get-favorites'       => array(),
			'petsync/clear-favorites'     => array(),
			'petsync/update-comparison'   => array(
				'action' => 'add',
				'id'     => $this->pet,
			),
			'petsync/get-comparison'      => array(),
			'petsync/get-adoption-stats'  => array(),
			'petsync/set-pet-gallery'     => array(
				'id'             => $this->pet,
				'attachment_ids' => array(),
			),
		);
	}

	/**
	 * @return string[] Names of every registered petsync ability.
	 */
	private function registered(): array {
		$names = array();

		foreach ( wp_get_abilities() as $ability ) {
			$name = is_object( $ability ) ? $ability->get_name() : (string) $ability;
			if ( str_starts_with( $name, 'petsync/' ) ) {
				$names[] = $name;
			}
		}

		sort( $names );
		return $names;
	}

	public function test_the_abilities_are_registered_at_all(): void {
		$this->assertNotEmpty(
			$this->registered(),
			'no petsync abilities registered — the rest of this test would pass vacuously'
		);
	}

	/**
	 * THE regression guard for the 7.1 upgrade. Executed the way a client
	 * executes it, so core's output validation actually runs.
	 */
	public function test_every_ability_output_satisfies_its_own_schema(): void {
		$inputs  = $this->inputs();
		$checked = 0;

		foreach ( $this->registered() as $name ) {
			$ability = wp_get_ability( $name );
			$this->assertNotNull( $ability, "$name is listed but does not resolve" );

			$result = $ability->execute( $inputs[ $name ] ?? array() );

			if ( is_wp_error( $result ) ) {
				$this->assertNotSame(
					'ability_invalid_output',
					$result->get_error_code(),
					"$name returned output that does not match the output_schema it advertises: "
						. $result->get_error_message()
				);
			}

			++$checked;
		}

		$this->assertGreaterThan( 10, $checked, 'the loop must actually execute the abilities' );
	}

	/**
	 * Output validation only happens when a schema is declared, so an ability
	 * that quietly drops its output_schema would make the test above pass by
	 * checking nothing.
	 */
	public function test_every_ability_still_declares_an_output_schema(): void {
		foreach ( $this->registered() as $name ) {
			$schema = wp_get_ability( $name )->get_output_schema();

			$this->assertNotEmpty(
				$schema,
				"$name declares no output_schema, so core validates nothing for it"
			);
		}
	}

	public function test_every_registered_ability_is_exercised_here(): void {
		$this->assertSame(
			array(),
			array_diff( $this->registered(), array_keys( $this->inputs() ) ),
			'an ability is registered but has no input in this test, so its output schema is unverified'
		);
	}
}
