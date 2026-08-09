<?php
/**
 * Stats ability callbacks.
 *
 * Provides aggregate adoption statistics for block bindings and archive pages.
 *
 * @package ShelterKit_Pets
 * @since 1.0.0
 */

declare( strict_types = 1 );

namespace Petsync\Abilities\Stats;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WP_Query;

/**
 * Get adoption statistics.
 *
 * @param array $input { status?: string }
 * @return array
 */
function get_adoption_stats( array $input = [] ): array {
	$status = $input['status'] ?? 'available';

	// Count by species.
	$species_counts = [];
	$animal_terms   = get_terms(
		[
			'taxonomy'   => 'pet_animal',
			'hide_empty' => true,
		]
	);

	$total_available = 0;

	if ( ! is_wp_error( $animal_terms ) ) {
		foreach ( $animal_terms as $term ) {
			$count = count_pets_by_status_and_term( $status, 'pet_animal', $term->term_id );
			if ( $count > 0 ) {
				$species_counts[ $term->name ] = $count;
				$total_available              += $count;
			}
		}
	}

	// Build formatted species string: "23 Dogs, 8 Cats, 3 Rabbits".
	$parts = [];
	arsort( $species_counts ); // Sort by count descending.
	foreach ( $species_counts as $name => $count ) {
		$parts[] = $count . ' ' . $name;
	}
	$available_by_species = implode( ', ', $parts );

	// Newest pet.
	$newest = get_posts(
		[
			'post_type'      => 'vcps_pet',
			'post_status'    => 'publish',
			'posts_per_page' => 1,
			'orderby'        => 'date',
			'order'          => 'DESC',
			'tax_query'      => [
				[
					'taxonomy' => 'pet_status',
					'field'    => 'slug',
					'terms'    => $status,
				],
			],
		]
	);

	// Total pets (all statuses).
	$total_query = new WP_Query(
		[
			'post_type'      => 'vcps_pet',
			'post_status'    => 'publish',
			'posts_per_page' => 1,
			'fields'         => 'ids',
		]
	);

	// Last sync time.
	$last_sync = get_option( 'petsync_last_sync', '' );

	return [
		'available_count'      => $total_available,
		'available_by_species' => $available_by_species,
		'species_counts'       => $species_counts,
		'newest_pet_name'      => ! empty( $newest ) ? $newest[0]->post_title : '',
		'newest_pet_id'        => ! empty( $newest ) ? $newest[0]->ID : 0,
		'total_pets'           => $total_query->found_posts,
		'last_sync'            => $last_sync,
	];
}

/**
 * Count pets with a given status and taxonomy term.
 *
 * @param string $status   Status slug.
 * @param string $taxonomy Taxonomy name.
 * @param int    $term_id  Term ID.
 * @return int
 */
function count_pets_by_status_and_term( string $status, string $taxonomy, int $term_id ): int {
	$query = new WP_Query(
		[
			'post_type'      => 'vcps_pet',
			'post_status'    => 'publish',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'tax_query'      => [
				'relation' => 'AND',
				[
					'taxonomy' => 'pet_status',
					'field'    => 'slug',
					'terms'    => $status,
				],
				[
					'taxonomy' => $taxonomy,
					'field'    => 'term_id',
					'terms'    => $term_id,
				],
			],
		]
	);

	return $query->found_posts;
}
