<?php
/**
 * Favorites and comparison are deliberately public, and must stay safe anyway.
 *
 * These abilities write visitor state through a REST route whose permission
 * callback returns true, which is the shape a CSRF review flags. The reasoning
 * for why that is safe lives in Petsync_REST::check_permission; this pins the
 * behaviour it depends on, so a future change that quietly makes anonymous
 * writes touch user data fails here rather than in a security report.
 *
 * @package Shelter_Pets
 */

declare( strict_types = 1 );

namespace Petsync\Tests\Integration;

final class VisitorStateSecurityTest extends PetTestCase {

	public function set_up(): void {
		parent::set_up();

		// These tests call ability CALLBACKS directly, so only the files need
		// loading. Registering the abilities themselves would trip core's
		// "abilities must be registered on wp_abilities_api_init" notice.
		foreach ( array( 'favorites', 'comparison', 'gallery' ) as $group ) {
			require_once PETSYNC_DIR . "includes/abilities/{$group}.php";
		}
	}

	/**
	 * The load-bearing fact: user meta is written only for a real user.
	 * Anonymous callers get a cookie in their own browser instead, which is
	 * why a forged cross-site request has nothing to reach.
	 */
	public function test_anonymous_favorites_never_touch_user_meta(): void {
		$user = self::factory()->user->create();
		$pet  = $this->make_manual_pet();

		// Give the user an existing list, then act as a logged-out visitor.
		update_user_meta( $user, '_pet_favorites', array( 12345 ) );
		wp_set_current_user( 0 );

		\Petsync\Abilities\Favorites\toggle( array( 'id' => $pet ) );

		$this->assertSame(
			array( 12345 ),
			get_user_meta( $user, '_pet_favorites', true ),
			'an anonymous call must not modify any user\'s stored favorites'
		);
	}

	public function test_anonymous_comparison_never_touches_user_meta(): void {
		$user = self::factory()->user->create();
		$pet  = $this->make_manual_pet();

		update_user_meta( $user, '_pet_comparison', array( 999 ) );
		wp_set_current_user( 0 );

		\Petsync\Abilities\Comparison\update( array(
			'id'     => $pet,
			'action' => 'add',
		) );

		$this->assertSame(
			array( 999 ),
			get_user_meta( $user, '_pet_comparison', true )
		);
	}

	/**
	 * Content-writing abilities are a different tier and must stay that way.
	 */
	public function test_writing_a_gallery_requires_a_capability(): void {
		$pet = $this->make_manual_pet();

		wp_set_current_user( 0 );
		$result = \Petsync\Abilities\Gallery\set_gallery( array(
			'id'             => $pet,
			'attachment_ids' => array(),
		) );

		$this->assertInstanceOf( \WP_Error::class, $result, 'an anonymous caller must not edit pet content' );
		$this->assertSame( 'cannot_edit_pet', $result->get_error_code() );
	}

	public function test_the_gallery_ability_declares_an_editorial_permission(): void {
		$abilities = \Petsync\Core\Config::get_item( 'abilities', 'abilities', array() );

		$this->assertSame(
			'edit_posts',
			$abilities['petsync/set-pet-gallery']['permission'] ?? null,
			'content writes must not be declared public'
		);
	}

	/**
	 * Every ability that writes must declare itself non-readonly, so the
	 * annotation cannot drift away from what the callback actually does.
	 */
	public function test_writing_abilities_are_not_annotated_readonly(): void {
		$abilities = \Petsync\Core\Config::get_item( 'abilities', 'abilities', array() );

		$writers = array(
			'petsync/toggle-favorite',
			'petsync/clear-favorites',
			'petsync/update-comparison',
			'petsync/set-pet-gallery',
		);

		foreach ( $writers as $name ) {
			$this->assertArrayHasKey( $name, $abilities, "$name should be declared" );
			$this->assertFalse(
				$abilities[ $name ]['meta']['annotations']['readonly'] ?? true,
				"$name writes, so it must not be annotated readonly"
			);
		}
	}
}
