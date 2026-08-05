<?php
/**
 * Kennel card rendering.
 *
 * The card's design is a template part, so the risk is not in any markup here
 * — it is in the seam: resolving the part, establishing post context so the
 * bindings resolve, and putting the global $post back afterwards.
 *
 * @package Shelter_Pets
 */

declare( strict_types = 1 );

namespace Petsync\Tests\Integration;

use Petsync_Kennel_Cards;
use ReflectionMethod;

final class KennelCardsTest extends PetTestCase {

	private Petsync_Kennel_Cards $cards;

	public function set_up(): void {
		parent::set_up();
		$this->cards = new Petsync_Kennel_Cards();
	}

	/**
	 * @param string $method Private method name.
	 * @param mixed  ...$args Arguments.
	 * @return mixed
	 */
	private function call( string $method, ...$args ) {
		return ( new ReflectionMethod( Petsync_Kennel_Cards::class, $method ) )
			->invoke( $this->cards, ...$args );
	}

	/**
	 * The card carries the information Petfinder's did, since that is the
	 * format the shelter asked to keep: identity, the attribute rows, health,
	 * a photo, and where to find the listing.
	 */
	public function test_the_card_carries_the_expected_information(): void {
		$id = $this->make_synced_pet(
			array(
				'name'          => 'Switzel',
				'is_spayed'     => 'Yes',
				'primary_breed' => 'Mixed Breed',
			)
		);
		wp_set_object_terms( $id, 'mixed-breed', 'pet_breed' );
		wp_set_object_terms( $id, 'senior', 'pet_age' );
		wp_set_object_terms( $id, 'male', 'pet_sex' );

		$html = strtolower( $this->call( 'render_card', $id ) );

		$this->assertStringContainsString( 'switzel', $html, 'name' );
		$this->assertStringContainsString( 'breed', $html, 'attribute rows' );
		$this->assertStringContainsString( 'spayed', $html, 'health' );
		$this->assertStringContainsString( get_permalink( $id ), $html, 'where to find the listing' );
	}

	public function test_the_card_design_resolves(): void {
		$template = $this->call( 'get_card_template' );

		$this->assertNotNull( $template, 'the kennel-card part must be discoverable or nothing can print' );
		$this->assertSame( 'kennel-card', $template->slug );
		$this->assertNotEmpty( trim( (string) $template->content ) );
	}

	public function test_a_card_renders_the_pets_bound_fields(): void {
		$id = $this->make_synced_pet(
			array(
				'name'                  => 'Boris',
				'adoption_fee'          => '150',
				'is_ok_with_other_dogs' => 'Yes',
			)
		);
		wp_set_object_terms( $id, 'dog', 'pet_animal' );
		// The attribute rows read taxonomy terms, which the real sync writes
		// separately from the snapshot.
		wp_set_object_terms( $id, 'Pit Bull Terrier', 'pet_breed' );

		$html = $this->call( 'render_card', $id );

		$this->assertNotEmpty( $html );
		// Assert on the name and on pet data generally, not on one field's
		// presence — the design is a template part the shelter edits, so any
		// particular field is their choice rather than a contract.
		$this->assertStringContainsString( 'Boris', $html, 'the name binding must resolve' );
		$this->assertStringContainsString( 'Pit Bull Terrier', $html, 'pet data must reach the card' );
	}

	public function test_a_hand_entered_pet_renders_too(): void {
		$id = $this->make_manual_pet( array( 'post_title' => 'Clover' ) );
		update_post_meta( $id, $this->prefix . 'spayed_neutered', 'yes' );
		wp_set_object_terms( $id, 'beagle', 'pet_breed' );

		$html = $this->call( 'render_card', $id );

		$this->assertStringContainsString( 'Clover', $html );
		$this->assertStringContainsString( 'beagle', strtolower( $html ), 'manual entry must reach the card, not just synced data' );
	}

	/**
	 * A pet with nothing filled in must still produce a card rather than
	 * warnings or a broken layout.
	 */
	public function test_a_sparse_pet_degrades_gracefully(): void {
		$id = $this->make_manual_pet( array( 'post_title' => 'Sparse' ) );

		$html = $this->call( 'render_card', $id );

		$this->assertStringContainsString( 'Sparse', $html );
		$this->assertStringNotContainsString( 'Warning', $html );
	}

	/**
	 * Rendering sets up post data. Leaving it set would corrupt whatever the
	 * admin screen renders next.
	 */
	public function test_the_global_post_is_restored(): void {
		$other = $this->make_manual_pet( array( 'post_title' => 'Context Owner' ) );
		$card  = $this->make_manual_pet( array( 'post_title' => 'Printed' ) );

		// Establishing the caller's context is the whole point of this test.
		$GLOBALS['post'] = get_post( $other ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- fixture.

		$this->call( 'render_card', $card );

		$this->assertSame(
			$other,
			$GLOBALS['post']->ID ?? null,
			'render_card must put the previous global $post back'
		);
	}

	/**
	 * The same, but with a real main query in play — which is the situation on
	 * any front-end render and the one where a careless restore is clobbered by
	 * wp_reset_postdata() re-reading $wp_query.
	 */
	public function test_the_global_post_is_restored_even_with_a_main_query(): void {
		$viewed  = $this->make_manual_pet( array( 'post_title' => 'Being Viewed' ) );
		$printed = $this->make_manual_pet( array( 'post_title' => 'Being Printed' ) );

		$this->go_to( get_permalink( $viewed ) );
		$this->assertSame( $viewed, $GLOBALS['post']->ID ?? null, 'fixture: the viewed pet should be in context' );

		$this->call( 'render_card', $printed );

		$this->assertSame(
			$viewed,
			$GLOBALS['post']->ID ?? null,
			'rendering a card must not leave another pet in context'
		);
	}

	/**
	 * The case that actually distinguishes a correct restore from a careless
	 * one: the global $post and $wp_query->post are DIFFERENT, as they are
	 * inside any secondary loop. Restoring the global and then calling
	 * wp_reset_postdata() re-reads $wp_query and throws the restore away.
	 */
	public function test_the_restore_survives_a_secondary_loop_context(): void {
		$main  = $this->make_manual_pet( array( 'post_title' => 'Main Query Pet' ) );
		$inner = $this->make_manual_pet( array( 'post_title' => 'Inner Loop Pet' ) );
		$card  = $this->make_manual_pet( array( 'post_title' => 'Card Pet' ) );

		$this->go_to( get_permalink( $main ) );

		// Simulate being partway through a secondary loop.
		$GLOBALS['post'] = get_post( $inner ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- fixture.

		$this->call( 'render_card', $card );

		$this->assertSame(
			$inner,
			$GLOBALS['post']->ID ?? null,
			'the caller was in a secondary loop; render_card must hand that context back, not the main query’s'
		);
	}

	public function test_the_design_edit_url_points_at_the_card_part(): void {
		$url = $this->call( 'get_design_edit_url' );

		$this->assertStringContainsString( 'site-editor.php', $url );
		$this->assertStringContainsString( 'postType=wp_template_part', $url );
		$this->assertStringContainsString( 'canvas=edit', $url );
		$this->assertStringContainsString( rawurlencode( 'kennel-card' ), rawurlencode( $url ) );
	}

	public function test_the_picker_only_offers_published_pets(): void {
		$published = $this->make_manual_pet();
		$draft     = $this->make_manual_pet( array( 'post_status' => 'draft' ) );
		$other     = self::factory()->post->create(
			array(
				'post_type'   => 'post',
				'post_status' => 'publish',
			)
		);

		$ids = wp_list_pluck( $this->call( 'get_printable_pets', 'all' ), 'ID' );

		$this->assertContains( $published, $ids );
		$this->assertNotContains( $draft, $ids );
		$this->assertNotContains( $other, $ids );
	}

	/**
	 * A kennel card is for an animal presently in a kennel, so the picker
	 * defaults to the same status the archive shows rather than every pet the
	 * shelter has ever had.
	 */
	public function test_the_picker_defaults_to_available_pets(): void {
		$available = $this->make_manual_pet( array( 'post_title' => 'Still Here' ) );
		$adopted   = $this->make_manual_pet( array( 'post_title' => 'Went Home' ) );
		wp_set_object_terms( $adopted, 'adopted', 'pet_status' );

		$ids = wp_list_pluck( $this->call( 'get_printable_pets' ), 'ID' );

		$this->assertContains( $available, $ids );
		$this->assertNotContains( $adopted, $ids, 'an adopted pet does not need a kennel card' );
	}

	public function test_the_picker_can_be_filtered_to_another_status(): void {
		$available = $this->make_manual_pet();
		$adopted   = $this->make_manual_pet();
		wp_set_object_terms( $adopted, 'adopted', 'pet_status' );

		$ids = wp_list_pluck( $this->call( 'get_printable_pets', 'adopted' ), 'ID' );

		$this->assertContains( $adopted, $ids );
		$this->assertNotContains( $available, $ids );
	}

	public function test_status_options_include_an_all_choice(): void {
		$this->make_manual_pet();

		$options = $this->call( 'get_status_options' );

		$this->assertArrayHasKey( 'all', $options );
		$this->assertArrayHasKey( 'available', $options, 'statuses in use should be offered' );
	}

	/**
	 * The print sheet renders whatever pet IDs the query string names, guarding
	 * the post type but not the post status — so a pet the sync has drafted (a
	 * withdrawn or adopted listing) renders like any other.
	 *
	 * That is only safe while everyone who can reach the page may already read
	 * unpublished pets. This asserts the property rather than the capability
	 * string, so it keeps holding if the constant is renamed and starts failing
	 * the moment the page is opened back up to Contributors or Authors.
	 */
	public function test_only_roles_that_may_read_unpublished_pets_can_print_cards(): void {
		$capability = ( new \ReflectionClass( Petsync_Kennel_Cards::class ) )
			->getConstant( 'CAPABILITY' );

		$this->assertIsString( $capability );

		foreach ( array( 'administrator', 'editor' ) as $name ) {
			$role = get_role( $name );
			$this->assertTrue(
				(bool) $role?->has_cap( $capability ),
				"$name should be able to print kennel cards"
			);
			$this->assertTrue(
				(bool) $role?->has_cap( 'read_private_posts' ),
				"$name can print cards, so must also be entitled to read unpublished pets"
			);
		}

		foreach ( array( 'contributor', 'author', 'subscriber' ) as $name ) {
			$role = get_role( $name );
			$this->assertFalse(
				(bool) $role?->has_cap( $capability ),
				"$name cannot read unpublished pets, so must not be able to print them"
			);
		}
	}
}
