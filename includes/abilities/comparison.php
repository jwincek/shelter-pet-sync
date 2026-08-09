<?php
/**
 * Comparison ability callbacks.
 *
 * @package ShelterKit_Pets
 * @since 1.0.0
 */

declare( strict_types = 1 );

namespace Petsync\Abilities\Comparison;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Petsync\Core\Pet_Hydrator;

/**
 * Update comparison list (add, remove, clear, set).
 *
 * @param array $input { action: string, id?: int, ids?: int[] }
 * @return array
 */
function update( array $input ): array {
	$action   = $input['action'];
	$ids      = \Petsync_Helpers::get_comparison();
	$max      = 4;
	$prev_ids = $ids;

	switch ( $action ) {
		case 'add':
			if ( ! in_array( $input['id'], $ids, true ) && count( $ids ) < $max ) {
				$ids[] = $input['id'];
			}
			break;
		case 'remove':
			$ids = array_values( array_diff( $ids, [ $input['id'] ] ) );
			break;
		case 'clear':
			$ids = [];
			break;
		case 'set':
			$ids = array_slice( $input['ids'] ?? [], 0, $max );
			break;
	}

	\Petsync_Helpers::save_comparison( $ids );

	/**
	 * Fires after the comparison list is updated.
	 *
	 * @since 1.0.0
	 *
	 * @param string $action   The action performed (add, remove, clear, set).
	 * @param int[]  $ids      Updated comparison IDs.
	 * @param int[]  $prev_ids Previous comparison IDs.
	 */
	do_action( 'petsync_comparison_updated', $action, $ids, $prev_ids );

	// Build the response using the known-good $ids rather than
	// re-reading via get_comparison(), which can fall back to a
	// stale $_COOKIE value from the incoming request (setcookie()
	// only updates the response headers, not $_COOKIE).
	$pets = [];
	if ( $ids ) {
		$posts = get_posts(
			[
				'post_type'      => 'vcps_pet',
				'post_status'    => 'publish',
				'post__in'       => $ids,
				'posts_per_page' => count( $ids ),
				'orderby'        => 'post__in',
			]
		);
		$pets  = Pet_Hydrator::hydrate_many( $posts );
	}

	return [
		'ids'      => $ids,
		'pets'     => $pets,
		'count'    => count( $ids ),
		'max'      => $max,
		'shareUrl' => add_query_arg( 'compare', implode( ',', $ids ), home_url( '/pets/' ) ),
	];
}

/**
 * Get current comparison list with hydrated pet data and share URL.
 *
 * @return array
 */
function get_comparison( array $input = [] ): array {
	$ids  = \Petsync_Helpers::get_comparison();
	$pets = [];

	if ( $ids ) {
		$posts = get_posts(
			[
				'post_type'      => 'vcps_pet',
				'post_status'    => 'publish',
				'post__in'       => $ids,
				'posts_per_page' => count( $ids ),
				'orderby'        => 'post__in',
			]
		);
		$pets  = Pet_Hydrator::hydrate_many( $posts );
	}

	return [
		'ids'      => $ids,
		'pets'     => $pets,
		'count'    => count( $ids ),
		'max'      => 4,
		'shareUrl' => add_query_arg( 'compare', implode( ',', $ids ), home_url( '/pets/' ) ),
	];
}
