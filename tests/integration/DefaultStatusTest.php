<?php
/**
 * A new pet must reach the archive.
 *
 * The listing grid filters on the `available` status term, so a pet without
 * one renders correctly on its own page and is invisible on the archive —
 * missing rather than visibly broken, which is the hardest kind of bug to
 * notice.
 *
 * register_taxonomy's `default_term` covers most creation paths but not the
 * one the block editor uses: it sends an empty term array, which WordPress
 * counts as terms having been supplied, so the default is skipped. That is
 * why there is also a wp_after_insert_post backstop, and why this file tests
 * each path separately.
 *
 * @package Petstablished_Sync
 */

declare( strict_types = 1 );

namespace Petstablished\Tests\Integration;

use WP_REST_Request;

final class DefaultStatusTest extends PetTestCase {

	/**
	 * @param int $id Pet post ID.
	 * @return string[] Status slugs.
	 */
	private function status_of( int $id ): array {
		$terms = wp_get_object_terms( $id, 'pet_status', array( 'fields' => 'slugs' ) );

		return is_wp_error( $terms ) ? array() : $terms;
	}

	private function on_archive( int $id ): bool {
		$found = get_posts(
			array(
				'post_type'   => 'vcps_pet',
				'post_status' => 'publish',
				'numberposts' => -1,
				'fields'      => 'ids',
				'tax_query'   => array(
					array(
						'taxonomy' => 'pet_status',
						'field'    => 'slug',
						'terms'    => array( 'available' ),
					),
				),
			)
		);

		return in_array( $id, $found, true );
	}

	public function test_the_taxonomy_declares_a_default(): void {
		$taxonomy = get_taxonomy( 'pet_status' );

		$this->assertIsArray( $taxonomy->default_term );
		$this->assertSame( 'available', $taxonomy->default_term['slug'] );
	}

	public function test_wp_insert_post_gets_the_default(): void {
		$id = wp_insert_post(
			array(
				'post_type'   => 'vcps_pet',
				'post_title'  => 'Insert Path',
				'post_status' => 'publish',
			)
		);

		$this->assertSame( array( 'available' ), $this->status_of( $id ) );
		$this->assertTrue( $this->on_archive( $id ) );
	}

	public function test_rest_create_gets_the_default(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$request = new WP_REST_Request( 'POST', '/wp/v2/vcps_pet' );
		$request->set_body_params(
			array(
				'title'  => 'REST Path',
				'status' => 'publish',
			)
		);
		$response = rest_do_request( $request );
		$id       = $response->get_data()['id'] ?? 0;

		$this->assertSame( 201, $response->get_status() );
		$this->assertSame( array( 'available' ), $this->status_of( $id ) );
	}

	/**
	 * The path that default_term alone does NOT cover, and the one the block
	 * editor actually takes.
	 */
	public function test_rest_create_with_an_empty_status_array_still_gets_the_default(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$request = new WP_REST_Request( 'POST', '/wp/v2/vcps_pet' );
		$request->set_body_params(
			array(
				'title'      => 'Empty Array Path',
				'status'     => 'publish',
				'pet_status' => array(),
			)
		);
		$response = rest_do_request( $request );
		$id       = $response->get_data()['id'] ?? 0;

		$this->assertSame( 201, $response->get_status() );
		$this->assertSame(
			array( 'available' ),
			$this->status_of( $id ),
			'an empty term array counts as terms supplied, so default_term is skipped — the backstop must cover it'
		);
	}

	public function test_an_explicit_status_is_respected(): void {
		$id = wp_insert_post(
			array(
				'post_type'   => 'vcps_pet',
				'post_title'  => 'Adopted Already',
				'post_status' => 'publish',
			)
		);
		wp_set_object_terms( $id, 'adopted', 'pet_status' );

		$this->assertSame( array( 'adopted' ), $this->status_of( $id ) );
		$this->assertFalse( $this->on_archive( $id ), 'an adopted pet must not appear on the archive' );
	}

	/**
	 * The backstop is creation-only. On a later save an empty taxonomy means
	 * the editor cleared it deliberately, and re-adding would fight them.
	 */
	public function test_clearing_the_status_later_stays_cleared(): void {
		$id = wp_insert_post(
			array(
				'post_type'   => 'vcps_pet',
				'post_title'  => 'Cleared',
				'post_status' => 'publish',
			)
		);
		$this->assertSame( array( 'available' ), $this->status_of( $id ) );

		wp_set_object_terms( $id, array(), 'pet_status' );
		wp_update_post(
			array(
				'ID'         => $id,
				'post_title' => 'Cleared, edited',
			)
		);

		$this->assertSame( array(), $this->status_of( $id ) );
	}
}
