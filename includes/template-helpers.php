<?php
/**
 * Template helper functions for block render callbacks.
 *
 * These are thin convenience wrappers that eliminate boilerplate
 * repeated across every block's render.php. They are intentionally
 * global-namespaced for ergonomic use in templates.
 *
 * @package Shelter_Pets
 * @since 1.0.0
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Retrieve a hydrated pet entity by post ID.
 *
 * Uses the Abilities API when available (preferred path), falling
 * back to the Pet_Hydrator for environments where abilities have
 * not yet initialized (e.g. early template rendering, unit tests).
 *
 * Returns null when the pet cannot be found or the ability errors.
 *
 * @since 1.0.0
 *
 * @param int    $post_id Post ID.
 * @param string $profile Hydration profile: 'full', 'summary', 'grid'.
 * @return array|null Hydrated pet entity or null.
 */
function petsync_get_pet( int $post_id, string $profile = 'full' ): ?array {
	// Abilities API path — validates permissions, fires hooks.
	if ( function_exists( 'wp_get_ability' ) ) {
		$ability = wp_get_ability( 'petsync/get-pet' );
		if ( $ability ) {
			$result = $ability->execute( [ 'id' => $post_id ] );
			if ( ! is_wp_error( $result ) ) {
				return $result;
			}
		}
	}

	// Fallback — direct hydration.
	return \Petsync\Core\Pet_Hydrator::get( $post_id, $profile );
}
