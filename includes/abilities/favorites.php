<?php
/**
 * Favorites ability callbacks.
 *
 * @package Shelter_Pets
 * @since 1.0.0
 */

declare( strict_types = 1 );

namespace Petsync\Abilities\Favorites;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Petsync\Core\Pet_Hydrator;

/**
 * Toggle a pet in/out of favorites.
 *
 * @param array $input { id: int }
 * @return array
 */
function toggle( array $input ): array {
	$id        = $input['id'];
	$favorites = \Petsync_Helpers::get_favorites();
	$key       = array_search( $id, $favorites, true );

	if ( false !== $key ) {
		unset( $favorites[ $key ] );
		$favorited = false;
	} else {
		$favorites[] = $id;
		$favorited   = true;
	}

	$favorites = array_values( $favorites );
	\Petsync_Helpers::save_favorites( $favorites );

	/**
	 * Fires after a pet is toggled in/out of favorites.
	 *
	 * @since 1.0.0
	 *
	 * @param int   $id        Pet post ID.
	 * @param bool  $favorited Whether the pet was added (true) or removed (false).
	 * @param int[] $favorites Updated favorites list.
	 */
	do_action( 'petsync_favorite_toggled', $id, $favorited, $favorites );

	return [
		'favorited' => $favorited,
		'favorites' => $favorites,
	];
}

/**
 * Clear all favorites in one operation.
 *
 * @param array $input { ids: int[] } — the IDs to clear (for verification).
 * @return array
 */
function clear_all( array $input = [] ): array {
	\Petsync_Helpers::save_favorites( [] );

	/**
	 * Fires after all favorites are cleared.
	 *
	 * @since 1.0.0
	 */
	do_action( 'petsync_favorites_cleared' );

	return [ 'favorites' => [] ];
}

/**
 * Get current user's favorites with hydrated pet data.
 *
 * @return array
 */
function get_favorites( array $input = [] ): array {
	$favorites = \Petsync_Helpers::get_favorites();
	$pets      = [];

	if ( $favorites ) {
		$posts = get_posts(
			[
				'post_type'      => 'vcps_pet',
				'post_status'    => 'publish',
				'post__in'       => $favorites,
				'posts_per_page' => count( $favorites ),
				'orderby'        => 'post__in',
			]
		);
		$pets  = Pet_Hydrator::hydrate_many( $posts, 'summary' );
	}

	return [
		'favorites' => $favorites,
		'pets'      => $pets,
	];
}
