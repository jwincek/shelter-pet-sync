<?php
/**
 * petsync/batch-get-pets.
 *
 * Nothing in the plugin calls this ability. That is fine and expected — the
 * Abilities API is a public surface for agents and other plugins, and not every
 * ability needs a first-party consumer. What is not fine is that it also had no
 * test: an ability with no caller and no test is a published API nobody has
 * checked, and #39 proposes handing exactly this one to MCP clients as a
 * public read-only tool.
 *
 * @package ShelterKit_Pets
 */

declare( strict_types = 1 );

namespace Petsync\Tests\Integration;

use function Petsync\Abilities\Pets\batch_get;

final class BatchGetPetsTest extends PetTestCase {

	public function set_up(): void {
		parent::set_up();

		// Calls the ability CALLBACK directly, so only the file needs loading.
		// Registering the abilities here would trip core's "abilities must be
		// registered on wp_abilities_api_init" notice — same reasoning as
		// VisitorStateSecurityTest.
		require_once PETSYNC_DIR . 'includes/abilities/pets.php';
	}

	public function test_it_returns_the_requested_pets(): void {
		$a = $this->make_manual_pet();
		$b = $this->make_manual_pet();

		$result = batch_get( array( 'ids' => array( $a, $b ) ) );

		$this->assertSame( array( $a, $b ), array_column( $result['pets'], 'id' ) );
		$this->assertSame( array(), $result['missing'] );
	}

	/**
	 * The handler asks for orderby => post__in, so the caller's order is part of
	 * the contract rather than an accident of the query.
	 */
	public function test_it_preserves_the_requested_order(): void {
		$a = $this->make_manual_pet();
		$b = $this->make_manual_pet();
		$c = $this->make_manual_pet();

		$result = batch_get( array( 'ids' => array( $c, $a, $b ) ) );

		$this->assertSame( array( $c, $a, $b ), array_column( $result['pets'], 'id' ) );
	}

	public function test_unknown_ids_are_reported_as_missing(): void {
		$a       = $this->make_manual_pet();
		$ghost   = $a + 999999;
		$result  = batch_get( array( 'ids' => array( $a, $ghost ) ) );

		$this->assertSame( array( $a ), array_column( $result['pets'], 'id' ) );
		$this->assertSame( array( $ghost ), $result['missing'] );
	}

	/**
	 * A post that exists but is not a pet must not be returned as one.
	 */
	public function test_a_non_pet_post_is_missing_not_returned(): void {
		$page = self::factory()->post->create( array( 'post_type' => 'page' ) );

		$result = batch_get( array( 'ids' => array( $page ) ) );

		$this->assertSame( array(), $result['pets'] );
		$this->assertSame( array( $page ), $result['missing'] );
	}

	public function test_an_empty_request_returns_empty_arrays(): void {
		$result = batch_get( array( 'ids' => array() ) );

		$this->assertSame( array(), $result['pets'] );
		$this->assertSame( array(), $result['missing'] );
	}

	/**
	 * A negative ID must be rejected, not resolved.
	 *
	 * absint() takes the ABSOLUTE value, so absint( -17325 ) is 17325 — a real,
	 * unrelated pet. This codebase has already fixed that twice, in the gallery
	 * ability and the kennel-card print sheet, both of which carry a comment
	 * saying why intval() is used instead.
	 */
	public function test_a_negative_id_does_not_resolve_to_a_real_pet(): void {
		$pet = $this->make_manual_pet();

		$result = batch_get( array( 'ids' => array( -$pet ) ) );

		$this->assertSame(
			array(),
			array_column( $result['pets'], 'id' ),
			'a negative id must not be flipped into a real pet'
		);
	}

	/**
	 * The declared output_schema is the contract an agent reads. Nothing else
	 * asserts the handler honours it.
	 */
	public function test_the_return_matches_the_declared_output_schema(): void {
		$abilities = \Petsync\Core\Config::get_item( 'abilities', 'abilities', array() );
		$props     = $abilities['petsync/batch-get-pets']['output_schema']['properties'] ?? array();

		$this->assertNotEmpty( $props, 'the ability must declare an output schema' );

		$result = batch_get( array( 'ids' => array( $this->make_manual_pet() ) ) );

		foreach ( array_keys( $props ) as $key ) {
			$this->assertArrayHasKey( $key, $result, "output_schema declares '$key' but the handler omits it" );
		}
		$this->assertSame( array_keys( $props ), array_keys( $result ), 'no undeclared keys' );
		$this->assertIsArray( $result['pets'] );
		$this->assertIsArray( $result['missing'] );
	}
}
