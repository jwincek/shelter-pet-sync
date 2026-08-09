<?php
/**
 * Fluent Query Builder for pet entities.
 *
 * Config-driven query construction following the guide's §5.2 pattern.
 * Reads meta prefix and taxonomy mappings from entities.json so query
 * building code stays thin and declarative.
 *
 * Usage:
 *     $result = Query::for( 'vcps_pet' )
 *         ->status( 'available' )
 *         ->where( 'animal', 'dog' )
 *         ->whereCompat( 'ok_with_dogs', true )
 *         ->search( 'buddy' )
 *         ->orderBy( 'date', 'DESC' )
 *         ->paginate( 1, 12 );
 *
 * @package ShelterKit_Pets
 * @since   1.0.0
 */

// phpcs:disable WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid -- fluent query-builder API uses camelCase method names by design.

declare( strict_types = 1 );

namespace Petsync\Core;

use WP_Query;

class Query {

	private string $post_type;
	private string $meta_prefix = '_';
	private array $taxonomies   = [];
	private array $tax_query    = [];
	private array $meta_query   = [];
	private string $orderby     = 'date';
	private string $order       = 'DESC';
	private ?string $search     = null;
	private ?array $post__in    = null;
	private array $extra_args   = [];

	/**
	 * Start a new query for a post type.
	 *
	 * Reads meta prefix and taxonomy mappings from entity config.
	 */
	public static function for( string $post_type ): self {
		$query            = new self();
		$query->post_type = $post_type;

		$entity_config = Config::get_item( 'entities', 'entities', [] );
		$config        = $entity_config[ $post_type ] ?? [];

		$query->meta_prefix = $config['meta_prefix'] ?? '_';

		// Build taxonomy lookup: slug key → taxonomy name.
		foreach ( $config['taxonomies'] ?? [] as $key => $tax_config ) {
			$query->taxonomies[ $key ] = $tax_config['taxonomy'];
		}

		return $query;
	}

	/**
	 * Filter by taxonomy term (slug).
	 *
	 * Automatically resolves the taxonomy name from entity config.
	 * Null/empty values are silently ignored.
	 */
	public function where( string $field, mixed $value ): self {
		if ( $value === null || $value === '' ) {
			return $this;
		}

		// If this field is a registered taxonomy, use tax_query.
		if ( isset( $this->taxonomies[ $field ] ) ) {
			$this->tax_query[] = [
				'taxonomy' => $this->taxonomies[ $field ],
				'field'    => 'slug',
				'terms'    => $value,
			];
			return $this;
		}

		// Otherwise treat as meta query.
		$this->meta_query[] = [
			'key'   => $this->meta_prefix . $field,
			'value' => $value,
		];
		return $this;
	}

	/**
	 * Filter by pet_status taxonomy specifically.
	 *
	 * Convenience method since almost every query needs a status filter.
	 */
	public function status( ?string $status ): self {
		if ( ! $status ) {
			return $this;
		}
		return $this->where( 'status', $status );
	}

	/**
	 * Filter by a boolean attribute via the pet_attribute taxonomy.
	 *
	 * @param string $term_slug The attribute term slug (e.g. 'good-with-dogs').
	 * @param mixed  $value     If truthy, add the filter. If falsy, skip.
	 */
	public function whereAttribute( string $term_slug, mixed $value ): self {
		if ( ! $value ) {
			return $this;
		}

		$entity_config = Config::get_item( 'entities', 'entities', [] );
		$attribute_tax = $entity_config[ $this->post_type ]['attribute_taxonomy'] ?? 'pet_attribute';

		$this->tax_query[] = [
			'taxonomy' => $attribute_tax,
			'field'    => 'slug',
			'terms'    => $term_slug,
		];
		return $this;
	}

	/**
	 * Apply multiple attribute filters from a map.
	 *
	 * @param array $compat_map [ camelCaseInputKey => term_slug, ... ]
	 * @param array $input      User input (keys matching compat_map keys).
	 */
	public function whereAttributesFromInput( array $compat_map, array $input ): self {
		foreach ( $compat_map as $input_key => $term_slug ) {
			if ( ! empty( $input[ $input_key ] ) ) {
				$this->whereAttribute( $term_slug, true );
			}
		}
		return $this;
	}

	/**
	 * Full-text search (WP_Query 's' parameter).
	 */
	public function search( ?string $term ): self {
		if ( $term ) {
			$this->search = $term;
		}
		return $this;
	}

	/**
	 * Restrict to specific post IDs.
	 */
	public function whereIn( array $ids ): self {
		$this->post__in = $ids;
		return $this;
	}

	/**
	 * Set ordering.
	 */
	public function orderBy( string $field = 'date', string $direction = 'DESC' ): self {
		$this->orderby = $field;
		$this->order   = strtoupper( $direction );
		return $this;
	}

	/**
	 * Add arbitrary WP_Query args (escape hatch for edge cases).
	 */
	public function withArgs( array $args ): self {
		$this->extra_args = array_merge( $this->extra_args, $args );
		return $this;
	}

	/**
	 * Execute with pagination, returning hydrated entities.
	 */
	public function paginate( int $page = 1, int $per_page = 12, string $profile = 'default' ): array {
		$args = $this->build_args( $page, $per_page );

		$this->maybe_add_search_filter( $args );
		$wp_query = new WP_Query( $args );
		$this->maybe_remove_search_filter();

		$items = Pet_Hydrator::hydrate_many( $wp_query->posts, $profile );

		return [
			'items'       => $items,
			'total'       => $wp_query->found_posts,
			'total_pages' => $wp_query->max_num_pages,
			'page'        => $page,
			'per_page'    => $per_page,
		];
	}

	/**
	 * Get all results (no pagination), returning hydrated entities.
	 */
	public function get( string $profile = 'default' ): array {
		$args = $this->build_args( 1, -1 );

		$this->maybe_add_search_filter( $args );
		$wp_query = new WP_Query( $args );
		$this->maybe_remove_search_filter();

		return Pet_Hydrator::hydrate_many( $wp_query->posts, $profile );
	}

	/**
	 * Get just the post IDs (no hydration).
	 */
	public function ids(): array {
		$args                  = $this->build_args( 1, -1 );
		$args['fields']        = 'ids';
		$args['no_found_rows'] = true;

		$this->maybe_add_search_filter( $args );
		$result = ( new WP_Query( $args ) )->posts;
		$this->maybe_remove_search_filter();

		return $result;
	}

	/**
	 * Get count only.
	 */
	public function count(): int {
		$args           = $this->build_args( 1, 1 );
		$args['fields'] = 'ids';

		$this->maybe_add_search_filter( $args );
		$result = ( new WP_Query( $args ) )->found_posts;
		$this->maybe_remove_search_filter();

		return $result;
	}

	/**
	 * Temporarily stores search filter callback for add/remove.
	 */
	private ?\Closure $search_filter = null;

	/**
	 * Add a posts_clauses filter for title + breed search.
	 */
	private function maybe_add_search_filter( array $args ): void {
		if ( empty( $args['_pet_search_term'] ) ) {
			return;
		}

		$term      = $args['_pet_search_term'];
		$breed_ids = $args['_pet_search_breed_ids'] ?? [];

		$this->search_filter = function ( $clauses, $query ) use ( $term, $breed_ids ) {
			global $wpdb;

			$like = '%' . $wpdb->esc_like( $term ) . '%';

			if ( ! empty( $breed_ids ) ) {
				$id_list           = implode( ',', array_map( 'intval', $breed_ids ) );
				$clauses['where'] .= $wpdb->prepare(
					" AND ({$wpdb->posts}.post_title LIKE %s OR {$wpdb->posts}.ID IN ({$id_list}))", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $id_list is an intval()-mapped, imploded ID list.
					$like
				);
			} else {
				$clauses['where'] .= $wpdb->prepare(
					" AND {$wpdb->posts}.post_title LIKE %s",
					$like
				);
			}

			return $clauses;
		};

		add_filter( 'posts_clauses', $this->search_filter, 10, 2 );
	}

	/**
	 * Remove the search filter after query execution.
	 */
	private function maybe_remove_search_filter(): void {
		if ( $this->search_filter ) {
			remove_filter( 'posts_clauses', $this->search_filter, 10 );
			$this->search_filter = null;
		}
	}

	/**
	 * Get first result.
	 */
	public function first( string $profile = 'default' ): ?array {
		$result = $this->paginate( 1, 1, $profile );
		return $result['items'][0] ?? null;
	}

	/**
	 * Get the raw WP_Query args (for inspection or manual use).
	 */
	public function toArgs( int $page = 1, int $per_page = 12 ): array {
		return $this->build_args( $page, $per_page );
	}

	/**
	 * Clone this query but remove a specific taxonomy filter.
	 *
	 * Used for cross-count calculation: "how many results if I remove
	 * the breed filter and add term X instead?"
	 */
	public function without( string $field ): self {
		$clone = clone $this;

		if ( isset( $this->taxonomies[ $field ] ) ) {
			$taxonomy         = $this->taxonomies[ $field ];
			$clone->tax_query = array_filter(
				$clone->tax_query,
				fn( $clause ) => ( $clause['taxonomy'] ?? '' ) !== $taxonomy
			);
		} else {
			$meta_key          = $this->meta_prefix . $field;
			$clone->meta_query = array_filter(
				$clone->meta_query,
				fn( $clause ) => ( $clause['key'] ?? '' ) !== $meta_key
			);
		}

		return $clone;
	}

	/**
	 * Build WP_Query arguments.
	 */
	private function build_args( int $page, int $per_page ): array {
		$args = [
			'post_type'      => $this->post_type,
			'post_status'    => 'publish',
			'posts_per_page' => $per_page,
			'paged'          => $page,
			'orderby'        => $this->orderby,
			'order'          => $this->order,
		];

		if ( ! empty( $this->tax_query ) ) {
			$tax_query = $this->tax_query;
			if ( count( $tax_query ) > 1 ) {
				$tax_query['relation'] = 'AND';
			}
			$args['tax_query'] = $tax_query;
		}

		if ( ! empty( $this->meta_query ) ) {
			$meta_query = $this->meta_query;
			if ( count( $meta_query ) > 1 ) {
				$meta_query['relation'] = 'AND';
			}
			$args['meta_query'] = $meta_query;
		}

		if ( $this->search ) {
			// Search pet name (title) and breed taxonomy only.
			// WP's default 's' also searches content/excerpt which is undesirable.
			$search_term = $this->search;

			// Find breed term IDs matching the search.
			$breed_taxonomy = $this->taxonomies['breed'] ?? 'pet_breed';
			$matching_terms = get_terms(
				[
					'taxonomy'   => $breed_taxonomy,
					'name__like' => $search_term,
					'fields'     => 'ids',
					'hide_empty' => true,
				]
			);
			$breed_post_ids = [];
			if ( ! is_wp_error( $matching_terms ) && ! empty( $matching_terms ) ) {
				$breed_post_ids = get_objects_in_term( $matching_terms, $breed_taxonomy );
			}

			if ( ! empty( $breed_post_ids ) ) {
				// Match title OR breed: use 's' for title with a filter to
				// restrict to title-only, and merge breed matches via post__in
				// using a custom search filter.
				$args['_pet_search_term']      = $search_term;
				$args['_pet_search_breed_ids'] = $breed_post_ids;
			} else {
				// No breed matches — title-only search.
				$args['_pet_search_term'] = $search_term;
			}
		}

		if ( $this->post__in !== null ) {
			$args['post__in'] = $this->post__in;
			if ( empty( $this->post__in ) ) {
				// WP_Query with empty post__in returns all posts — prevent that.
				$args['post__in'] = [ 0 ];
			}
		}

		// Merge any extra args.
		return array_merge( $args, $this->extra_args );
	}
}
