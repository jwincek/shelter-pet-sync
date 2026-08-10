<?php
/**
 * Pet ability callbacks.
 *
 * Pure functions for pet data retrieval abilities.
 * Each function receives validated input and returns data or WP_Error.
 * Built on the Query Builder and exposes do_action hooks for extensibility.
 *
 * @package ShelterKit_Pets
 * @since 1.0.0
 */

declare( strict_types = 1 );

namespace Petsync\Abilities\Pets;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Petsync\Core\Pet_Hydrator;
use Petsync\Core\Query;
use WP_Error;

/**
 * Compatibility filter mapping: camelCase input key → pet_attribute term slug.
 */
const COMPAT_MAP = [
	'goodWithDogs'   => 'good-with-dogs',
	'goodWithCats'   => 'good-with-cats',
	'goodWithKids'   => 'good-with-kids',
	'shotsCurrent'   => 'shots-current',
	'spayedNeutered' => 'spayed-neutered',
	'housebroken'    => 'housebroken',
	'specialNeeds'   => 'special-needs',
	'hypoallergenic' => 'hypoallergenic',
	'declawed'       => 'declawed',
];

/**
 * Get a single pet by ID.
 *
 * @param array $input { id: int }
 * @return array|WP_Error
 */
function get( array $input ): array|WP_Error {
	$post = get_post( $input['id'] );

	if ( ! $post || 'vcps_pet' !== $post->post_type || 'publish' !== $post->post_status ) {
		return new WP_Error( 'not_found', __( 'Pet not found.', 'shelterkit-pets' ), [ 'status' => 404 ] );
	}

	$pet = Pet_Hydrator::hydrate( $post );

	/**
	 * Fires after a single pet is retrieved via the get-pet ability.
	 *
	 * @since 1.0.0
	 *
	 * @param array    $pet  Hydrated pet data.
	 * @param \WP_Post $post The post object.
	 */
	do_action( 'petsync_pet_retrieved', $pet, $post );

	return $pet;
}

/**
 * List pets with filters and pagination.
 *
 * @param array $input Filters and pagination options.
 * @return array
 */
function list_pets( array $input = [] ): array {
	$per_page = (int) ( $input['per_page'] ?? 12 );
	$page     = (int) ( $input['page'] ?? 1 );
	$cursor   = $input['cursor'] ?? null;

	$query = build_base_query( $input );

	// Custom ordering (default: date DESC).
	$orderby = $input['orderby'] ?? null;
	$order   = $input['order'] ?? null;
	if ( $orderby ) {
		$query->orderBy( $orderby, $order ?? 'DESC' );
	}

	// Exclude specific post IDs.
	$exclude = $input['exclude'] ?? [];
	if ( ! empty( $exclude ) ) {
		$query->withArgs( [ 'post__not_in' => array_map( 'intval', (array) $exclude ) ] );
	}

	// Cursor-based pagination (for infinite scroll).
	if ( $cursor ) {
		$cursor_data = \Petsync_Helpers::decode_cursor( $cursor );
		if ( $cursor_data ) {
			$query->withArgs(
				[
					'date_query'     => [
						[
							'before'    => $cursor_data['date'],
							'inclusive' => false,
						],
					],
					'posts_per_page' => $per_page + 1,
					'paged'          => 1,
				]
			);
		}

		$args  = $query->toArgs( 1, $per_page + 1 );
		$posts = ( new \WP_Query( $args ) )->posts;

		$has_more = count( $posts ) > $per_page;
		if ( $has_more ) {
			array_pop( $posts );
		}

		$pets = Pet_Hydrator::hydrate_many( $posts, 'summary' );

		$result = [
			'pets'    => $pets,
			'total'   => count( $pets ),
			'hasMore' => $has_more,
		];

		if ( $has_more && ! empty( $posts ) ) {
			$last                 = end( $posts );
			$result['nextCursor'] = \Petsync_Helpers::encode_cursor( $last->ID, $last->post_date );
		}

		return $result;
	}

	// Standard pagination.
	$result = $query->paginate( $page, $per_page, 'summary' );

	/**
	 * Fires after a paginated pet list is retrieved.
	 *
	 * @since 1.0.0
	 *
	 * @param array $result Paginated result with items, total, total_pages, page.
	 * @param array $input  The filter input.
	 */
	do_action( 'petsync_pets_listed', $result, $input );

	return [
		'pets'       => $result['items'],
		'total'      => $result['total'],
		'page'       => $result['page'],
		'totalPages' => $result['total_pages'],
	];
}

/**
 * Filter pets with live counts per taxonomy value.
 *
 * Returns paginated results plus counts for each filter option
 * relative to the *other* active filters (so users see how many
 * results each option would produce).
 *
 * @param array $input Filters, search, compatibility, pagination.
 * @return array
 */
function filter_pets( array $input = [] ): array {
	$per_page = (int) ( $input['per_page'] ?? 12 );
	$page     = (int) ( $input['page'] ?? 1 );

	$query = build_base_query( $input );

	// Custom ordering (default: date DESC).
	$orderby = $input['orderby'] ?? null;
	$order   = $input['order'] ?? null;
	if ( $orderby ) {
		$query->orderBy( $orderby, $order ?? 'DESC' );
	}

	// Favorites-only filter.
	if ( ! empty( $input['showFavoritesOnly'] ) ) {
		$favorites = \Petsync_Helpers::get_favorites();
		if ( empty( $favorites ) ) {
			return [
				'pets'       => [],
				'total'      => 0,
				'page'       => $page,
				'totalPages' => 0,
				'counts'     => calculate_filter_counts( $input ),
			];
		}
		$query->whereIn( $favorites );
	}

	// Get ALL matching IDs first (for accurate total + count calculation).
	$all_ids = $query->ids();
	$total   = count( $all_ids );

	// Paginate from the ID set.
	$offset   = ( $page - 1 ) * $per_page;
	$page_ids = array_slice( $all_ids, $offset, $per_page );

	// Hydrate the page.
	$pets = [];
	if ( ! empty( $page_ids ) ) {
		$page_query = Query::for( 'vcps_pet' )
			->whereIn( $page_ids )
			->withArgs( [ 'orderby' => 'post__in' ] );
		$pets       = $page_query->get( 'grid' );
	}

	// Calculate filter counts relative to other active filters.
	$counts = calculate_filter_counts( $input, $all_ids );

	/**
	 * Fires after filtered pets are retrieved.
	 *
	 * @since 1.0.0
	 *
	 * @param int   $total Total matching pets.
	 * @param array $input The filter input.
	 */
	do_action( 'petsync_pets_filtered', $total, $input );

	return [
		'pets'       => $pets,
		'total'      => $total,
		'page'       => $page,
		'totalPages' => (int) ceil( $total / $per_page ),
		'counts'     => $counts,
	];
}

/**
 * Batch get pets by IDs.
 *
 * @param array $input { ids: int[] }
 * @return array
 */
function batch_get( array $input ): array {
	// intval rather than absint: absint takes the ABSOLUTE value, so an id of
	// -17 would silently resolve to pet 17 — a real, unrelated animal — instead
	// of being rejected. Same reasoning as the gallery ability and the
	// kennel-card print sheet, both of which had this fixed already.
	$ids = array_values(
		array_filter(
			array_map( 'intval', $input['ids'] ?? array() ),
			static fn( int $id ): bool => $id > 0
		)
	);

	$query = Query::for( 'vcps_pet' )
		->whereIn( $ids )
		->withArgs( [ 'orderby' => 'post__in' ] );

	$pets  = $query->get();
	$found = array_column( $pets, 'id' );

	return [
		'pets'    => $pets,
		'missing' => array_values( array_diff( $ids, $found ) ),
	];
}

/**
 * Get filter options from taxonomies.
 *
 * @return array
 */
function get_filter_options( array $input = [] ): array {
	return \Petsync_Helpers::get_filter_options();
}

// ─── Internal Helpers ─────────────────────────────────────────────────

/**
 * Build a base Query from filter input.
 *
 * Applies taxonomy filters, compatibility meta filters, and search.
 * Does NOT apply pagination — callers add that.
 *
 * @param array $input Filter input.
 * @return Query
 */
function build_base_query( array $input ): Query {
	return Query::for( 'vcps_pet' )
		->status( $input['status'] ?? null )
		->where( 'animal', $input['animal'] ?? null )
		->where( 'breed', $input['breed'] ?? null )
		->where( 'age', $input['age'] ?? null )
		->where( 'sex', $input['sex'] ?? null )
		->where( 'size', $input['size'] ?? null )
		->where( 'color', $input['color'] ?? null )
		->where( 'coat', $input['coat'] ?? null )
		->search( $input['search'] ?? null )
		->whereAttributesFromInput( COMPAT_MAP, $input );
}

/**
 * Calculate filter counts for each taxonomy value and attribute filter,
 * given the other active filters.
 *
 * @param array $input   The active filters.
 * @param int[] $all_ids Pre-computed IDs matching all active filters (optional).
 * @return array
 */
function calculate_filter_counts( array $input, array $all_ids = [] ): array {
	$counts     = [];
	$taxonomies = \Petsync_Helpers::TAXONOMIES;

	foreach ( $taxonomies as $key => $taxonomy ) {
		$terms = get_terms(
			[
				'taxonomy'   => $taxonomy,
				'hide_empty' => true,
			]
		);
		if ( is_wp_error( $terms ) ) {
			$counts[ $key ] = [];
			continue;
		}

		// Build query WITHOUT this taxonomy filter to get cross-counts.
		$other_input = $input;
		unset( $other_input[ $key ] );

		$cross_query = build_base_query( $other_input );

		// Favorites filter.
		if ( ! empty( $input['showFavoritesOnly'] ) ) {
			$favorites = \Petsync_Helpers::get_favorites();
			if ( ! empty( $favorites ) ) {
				$cross_query->whereIn( $favorites );
			}
		}

		$other_ids = $cross_query->ids();

		$term_counts = [];
		foreach ( $terms as $term ) {
			if ( empty( $other_ids ) ) {
				$count = 0;
			} else {
				$term_post_ids = get_objects_in_term( $term->term_id, $taxonomy );
				$count         = count( array_intersect( $other_ids, $term_post_ids ) );
			}

			if ( $count > 0 ) {
				$term_counts[] = [
					'value' => $term->slug,
					'label' => $term->name,
					'count' => $count,
				];
			}
		}

		$counts[ $key ] = $term_counts;
	}

	// Attribute boolean counts — now using pet_attribute taxonomy terms.
	// Count how many of the current result set have each attribute.
	// Format as single-element arrays to match the taxonomy count structure.
	if ( ! empty( $all_ids ) ) {
		$attribute_taxonomy = 'pet_attribute';
		$attr_terms         = get_terms(
			[
				'taxonomy'   => $attribute_taxonomy,
				'hide_empty' => false,
			]
		);

		if ( ! is_wp_error( $attr_terms ) ) {
			// Build a slug → camelCase lookup from COMPAT_MAP (reversed).
			$slug_to_camel = array_flip( COMPAT_MAP );

			foreach ( $attr_terms as $term ) {
				$camel_key = $slug_to_camel[ $term->slug ] ?? null;
				if ( ! $camel_key ) {
					continue;
				}

				$term_post_ids        = get_objects_in_term( $term->term_id, $attribute_taxonomy );
				$count                = count( array_intersect( $all_ids, $term_post_ids ) );
				$counts[ $camel_key ] = [
					[
						'value' => $term->slug,
						'label' => $term->name,
						'count' => $count,
					],
				];
			}
		}
	}

	return $counts;
}
