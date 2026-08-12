<?php
/**
 * Standing a real pet in while the kennel card is designed.
 *
 * The card is a template part, so editing it means editing a design with no
 * subject: every bound field and pet block renders its nothing-to-show branch
 * and the card comes out blank.
 *
 * The natural fix — read the template-part context server-side — is impossible.
 * Core's block-renderer endpoint accepts only `post_id`, cast to (int), and
 * passes no block context at all, and a template part's id is a string like
 * `theme//kennel-card` which casts to 0. The route is the only signal there is.
 *
 * That makes the negative cases the important ones: a shim keyed on a route
 * must fire in exactly one situation and never leak a pet into anything else.
 *
 * @package ShelterKit_Pets
 */

declare( strict_types = 1 );

namespace Petsync\Tests\Integration;

use Petsync\Core\Editor_Preview;
use WP_REST_Request;

final class EditorPreviewTest extends PetTestCase {

	public function set_up(): void {
		parent::set_up();

		delete_option( Editor_Preview::OPTION );
		Editor_Preview::register();

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
	}

	public function tear_down(): void {
		delete_option( Editor_Preview::OPTION );
		parent::tear_down();
	}

	private function make_available_pet( string $title ): int {
		$id = $this->make_manual_pet( array( 'post_title' => $title ) );
		wp_set_object_terms( $id, 'Available', 'pet_status' );
		return $id;
	}

	/**
	 * @return mixed The rendered value, or the WP_Error code.
	 */
	private function render( string $block, array $params = array() ) {
		$request = new WP_REST_Request( 'GET', "/wp/v2/block-renderer/$block" );
		$request->set_param( 'context', 'edit' );
		$request->set_param( 'attributes', array() );

		foreach ( $params as $key => $value ) {
			$request->set_param( $key, $value );
		}

		$response = rest_do_request( $request );

		return $response->is_error()
			? ( $response->get_data()['code'] ?? 'error' )
			: trim( wp_strip_all_tags( (string) ( $response->get_data()['rendered'] ?? '' ) ) );
	}

	// ─── It fires where it should ───────────────────────────────────────────

	public function test_a_pet_block_preview_with_no_post_gets_a_pet(): void {
		$pet = $this->make_available_pet( 'Marigold' );
		update_post_meta( $pet, $this->prefix . 'adoption_fee', '65' );
		update_option( Editor_Preview::OPTION, $pet );

		$this->assertStringContainsString(
			'65',
			(string) $this->render( 'petsync/adoption-fee' ),
			'the card design must preview against a real pet, or it renders blank'
		);
	}

	public function test_the_chosen_pet_wins_over_the_default(): void {
		$this->make_available_pet( 'Aaron' ); // Would sort first.
		$chosen = $this->make_available_pet( 'Zelda' );
		update_option( Editor_Preview::OPTION, $chosen );

		$this->assertSame( $chosen, Editor_Preview::preview_pet()->ID );
	}

	public function test_it_falls_back_to_the_first_available_pet(): void {
		$this->make_available_pet( 'Zelda' );
		$first = $this->make_available_pet( 'Aaron' );

		$this->assertSame(
			$first,
			Editor_Preview::preview_pet()->ID,
			'the previewed pet should be the one you would print first'
		);
	}

	/**
	 * A pet that is adopted and unpublished must not keep driving the preview,
	 * or the design silently follows an animal that is no longer listed.
	 */
	public function test_a_stored_pet_that_is_no_longer_published_is_ignored(): void {
		$available = $this->make_available_pet( 'Aaron' );
		$gone      = $this->make_available_pet( 'Zelda' );
		update_option( Editor_Preview::OPTION, $gone );
		wp_update_post(
			array(
				'ID'          => $gone,
				'post_status' => 'draft',
			)
		);

		$this->assertSame( $available, Editor_Preview::preview_pet()->ID );
	}

	/**
	 * A shelter with nothing available should still be able to design the card.
	 */
	public function test_it_falls_back_to_any_published_pet_when_none_are_available(): void {
		$pet = $this->make_manual_pet( array( 'post_title' => 'Only Pet' ) );
		wp_set_object_terms( $pet, 'Adopted', 'pet_status' );

		$this->assertSame( $pet, Editor_Preview::preview_pet()->ID );
	}

	public function test_no_pets_at_all_is_not_an_error(): void {
		foreach ( get_posts(
			array(
				'post_type'   => 'vcps_pet',
				'numberposts' => -1,
				'fields'      => 'ids',
				'post_status' => 'any',
			)
		) as $id ) {
			wp_delete_post( $id, true );
		}

		$this->assertNull( Editor_Preview::preview_pet() );
	}

	// ─── It does NOT fire where it should not ───────────────────────────────

	/**
	 * The post editor sends the post it is editing. Standing a different pet in
	 * there would show the wrong animal on a real page's preview.
	 */
	public function test_a_request_carrying_a_post_id_is_left_alone(): void {
		$preview = $this->make_available_pet( 'Preview Pet' );
		update_post_meta( $preview, $this->prefix . 'adoption_fee', '65' );
		update_option( Editor_Preview::OPTION, $preview );

		$real = $this->make_available_pet( 'Real Pet' );
		update_post_meta( $real, $this->prefix . 'adoption_fee', '250' );

		$rendered = (string) $this->render( 'petsync/adoption-fee', array( 'post_id' => $real ) );

		$this->assertStringContainsString( '250', $rendered );
		$this->assertStringNotContainsString( '65', $rendered, 'the preview pet must not override a real post' );
	}

	/**
	 * Scoped to petsync/* so no core or third-party block preview is affected.
	 */
	public function test_a_non_pet_block_preview_does_not_get_a_pet(): void {
		$pet = $this->make_available_pet( 'Marigold' );
		update_option( Editor_Preview::OPTION, $pet );

		$before = $GLOBALS['post'] ?? null;
		$this->render( 'core/paragraph' );

		$this->assertSame( $before, $GLOBALS['post'] ?? null, 'a core block preview must not have a pet stood in for it' );
	}

	/**
	 * The global post is restored, or a pet leaks into whatever the request
	 * does next.
	 */
	public function test_the_global_post_is_restored_afterwards(): void {
		$pet = $this->make_available_pet( 'Marigold' );
		update_option( Editor_Preview::OPTION, $pet );

		$page            = self::factory()->post->create( array( 'post_type' => 'page' ) );
		$GLOBALS['post'] = get_post( $page ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- arranging the test.
		$this->render( 'petsync/adoption-fee' );

		$this->assertSame( $page, ( $GLOBALS['post'] ?? null )?->ID, 'the caller\'s post must survive the preview' );
	}

	/**
	 * Nothing about this may reach the front end. The shim keys on a REST route
	 * that only the editor calls, but the consequence of being wrong — a random
	 * pet appearing on a real page — is bad enough to pin explicitly.
	 */
	public function test_front_end_rendering_is_untouched(): void {
		$pet = $this->make_available_pet( 'Marigold' );
		update_post_meta( $pet, $this->prefix . 'adoption_fee', '65' );
		update_option( Editor_Preview::OPTION, $pet );

		$before = $GLOBALS['post'] ?? null;
		$html   = do_blocks( '<!-- wp:petsync/adoption-fee /-->' );

		$this->assertStringNotContainsString( '65', wp_strip_all_tags( $html ), 'no pet may be stood in outside a block-renderer request' );
		$this->assertSame( $before, $GLOBALS['post'] ?? null );
	}

	// ─── The editor-side binding source ─────────────────────────────────────

	/**
	 * @return array<string, mixed>
	 */
	private function binding_data(): array {
		$method = new \ReflectionMethod( \Petsync_Blocks::class, 'get_preview_binding_data' );

		return (array) $method->invoke( new \Petsync_Blocks() );
	}

	/**
	 * The three keys the shipped kennel card binds. If any stops resolving the
	 * card design goes blank again, which is the whole bug.
	 */
	public function test_the_bound_card_fields_are_localised_for_the_editor(): void {
		$pet = $this->make_available_pet( 'Marigold' );
		update_option( Editor_Preview::OPTION, $pet );

		$fields = $this->binding_data()['fields'];

		foreach ( array( 'name', 'image', 'url' ) as $key ) {
			$this->assertArrayHasKey( $key, $fields, "the card binds '$key' but it is not localised" );
		}
		$this->assertSame( 'Marigold', $fields['name'] );
	}

	/**
	 * A binding writes into a block attribute, so a non-scalar has nowhere to
	 * go — and shipping the arrays would put a pet's whole gallery into an
	 * inline script for no benefit.
	 */
	public function test_only_scalars_are_localised(): void {
		$pet = $this->make_available_pet( 'Marigold' );
		update_option( Editor_Preview::OPTION, $pet );

		foreach ( $this->binding_data()['fields'] as $key => $value ) {
			$this->assertIsString( $value, "'$key' was localised as a non-string" );
		}
	}

	public function test_booleans_are_localised_as_words_not_as_1_and_empty(): void {
		$pet = $this->make_available_pet( 'Marigold' );
		update_option( Editor_Preview::OPTION, $pet );

		$fields = $this->binding_data()['fields'];

		$this->assertArrayHasKey( 'is_bonded_pair', $fields );
		$this->assertContains(
			$fields['is_bonded_pair'],
			array( __( 'Yes', 'shelterkit-pets' ), __( 'No', 'shelterkit-pets' ) ),
			'a raw false would render as an empty heading rather than as "No"'
		);
	}

	public function test_no_pet_yields_an_empty_payload_rather_than_an_error(): void {
		foreach ( get_posts(
			array(
				'post_type'   => 'vcps_pet',
				'numberposts' => -1,
				'fields'      => 'ids',
				'post_status' => 'any',
			)
		) as $id ) {
			wp_delete_post( $id, true );
		}

		$data = $this->binding_data();

		$this->assertSame( 0, $data['id'] );
		$this->assertSame( array(), $data['fields'] );
	}

	/**
	 * The JS source name must match the PHP one. They are registered in
	 * different languages against the same string, and a mismatch fails
	 * silently — the editor simply never resolves the binding, which looks
	 * exactly like the bug this was written to fix.
	 */
	public function test_the_js_source_name_matches_the_php_one(): void {
		$js = file_get_contents( PETSYNC_DIR . 'assets/js/bindings-editor.js' );

		$this->assertNotFalse( $js );
		$this->assertStringContainsString(
			"name: 'petsync/pet-data'",
			$js,
			'the editor-side source must register the same name as register_block_bindings_source()'
		);
	}

	/**
	 * The script has to actually reach the editor, with its data attached.
	 */
	public function test_the_binding_script_is_enqueued_with_its_data(): void {
		$pet = $this->make_available_pet( 'Marigold' );
		update_option( Editor_Preview::OPTION, $pet );

		( new \Petsync_Blocks() )->enqueue_editor_assets();

		$handle = 'petsync-bindings-editor';
		$this->assertTrue( wp_script_is( $handle, 'enqueued' ), 'the binding source script never reaches the editor' );

		$inline = wp_scripts()->get_data( $handle, 'before' );
		$this->assertStringContainsString( 'petsyncPreviewPet', implode( ' ', (array) $inline ) );
		$this->assertStringContainsString( 'Marigold', implode( ' ', (array) $inline ) );
	}
}
