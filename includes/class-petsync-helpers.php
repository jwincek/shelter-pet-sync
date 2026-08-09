<?php
/**
 * ShelterKit Pets Shared Helpers
 *
 * Single source of truth for data formatting, storage, and utilities.
 *
 * @package ShelterKit_Pets
 * @since 1.0.0
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Petsync_Helpers {

	/** Taxonomy mapping. */
	public const TAXONOMIES = array(
		'status' => 'pet_status',
		'animal' => 'pet_animal',
		'breed'  => 'pet_breed',
		'age'    => 'pet_age',
		'sex'    => 'pet_sex',
		'size'   => 'pet_size',
		'color'  => 'pet_color',
		'coat'   => 'pet_coat',
	);

	/** Registered meta fields (only essential sync keys). */
	public const META_FIELDS = array(
		'ps_id',
		'api_response',
		'api_hash',
	);

	// === Pet Data Formatting ===

	/**
	 * Get the decoded API response data for a pet.
	 *
	 * Delegates to the hydrator's cached decoder.
	 *
	 * @param int $id Post ID.
	 * @return array Decoded API response.
	 */
	public static function get_api_data( int $id ): array {
		return \Petsync\Core\Pet_Hydrator::get_api_data( $id );
	}

	// === Pet Data Formatting ===

	public static function format_pet( WP_Post $post, bool $summary = false ): array {
		$id   = $post->ID;
		$data = array(
			'id'        => $id,
			'name'      => $post->post_title,
			'status'    => self::get_term( $id, 'status' ),
			'animal'    => self::get_term( $id, 'animal' ),
			'breed'     => self::get_term( $id, 'breed' ),
			'age'       => self::get_term( $id, 'age' ),
			'sex'       => self::get_term( $id, 'sex' ),
			'size'      => self::get_term( $id, 'size' ),
			'image'     => self::get_image( $id ),
			'url'       => get_permalink( $id ),
			'favorited' => in_array( $id, self::get_favorites(), true ),
		);

		if ( ! $summary ) {
			$data['description'] = $post->post_content;
			$data['color']       = self::get_term( $id, 'color' );
			$data['coat']        = self::get_term( $id, 'coat' );
			$data['meta']        = self::get_meta( $id );
			$data['gallery']     = self::get_gallery( $id );
		}

		return $data;
	}

	public static function get_term( int $id, string $key ): string {
		$taxonomy = self::TAXONOMIES[ $key ] ?? null;
		if ( ! $taxonomy ) {
			return '';
		}
		$terms = get_the_terms( $id, $taxonomy );
		return ( $terms && ! is_wp_error( $terms ) ) ? $terms[0]->name : '';
	}

	/**
	 * Get taxonomy label by full taxonomy name.
	 * Alias for get_term but accepts full taxonomy name like 'pet_breed'.
	 */
	public static function get_taxonomy_label( int $id, string $taxonomy ): string {
		// If it's a full taxonomy name, use it directly.
		if ( strpos( $taxonomy, 'pet_' ) === 0 ) {
			$terms = get_the_terms( $id, $taxonomy );
			return ( $terms && ! is_wp_error( $terms ) ) ? $terms[0]->name : '';
		}
		// Otherwise treat as a key.
		return self::get_term( $id, $taxonomy );
	}

	public static function get_term_slug( int $id, string $key ): string {
		$taxonomy = self::TAXONOMIES[ $key ] ?? null;
		if ( ! $taxonomy ) {
			return '';
		}
		$terms = get_the_terms( $id, $taxonomy );
		return ( $terms && ! is_wp_error( $terms ) ) ? $terms[0]->slug : '';
	}

	public static function get_image( int $id, string $size = 'medium_large' ): string {
		$url = get_the_post_thumbnail_url( $id, $size );
		if ( $url ) {
			return $url;
		}
		$api_data = self::get_api_data( $id );
		return $api_data['images'][0]['image']['url'] ?? '';
	}

	/**
	 * Get meta-like data for a pet, sourced from the stored API JSON.
	 *
	 * Returns a compatible array matching the legacy format so existing
	 * consumers (blocks, bindings) continue to work.
	 */
	public static function get_meta( int $id ): array {
		$api_data = self::get_api_data( $id );

		// Map from our internal field names to API keys.
		$api_map = array(
			'weight'               => 'weight',
			'adoption_fee'         => 'adoption_fee',
			'shots_current'        => 'shots_up_to_date',
			'spayed_neutered'      => 'is_spayed',
			'housebroken'          => 'is_housebroken',
			'ok_with_dogs'         => 'is_ok_with_other_dogs',
			'ok_with_cats'         => 'is_ok_with_other_cats',
			'ok_with_kids'         => 'is_ok_with_other_kids',
			'special_needs'        => 'has_special_need',
			'special_needs_detail' => 'special_needs',
			'hypoallergenic'       => 'is_hypoallergenic',
			'declawed'             => 'declawed',
			'adoption_form_url'    => 'public_url',
		);

		$meta = array();
		foreach ( $api_map as $field => $api_key ) {
			$meta[ $field ] = $api_data[ $api_key ] ?? '';
		}

		return $meta;
	}

	/**
	 * Get a boolean value from the stored API data.
	 *
	 * Handles various truthy values: 'yes', 'Yes', '1', 'true', true, 1.
	 * Reads from the stored API JSON response rather than individual meta.
	 *
	 * @param int    $id    Post ID.
	 * @param string $field Internal field name (e.g. 'special_needs').
	 * @return bool
	 */
	public static function get_meta_bool( int $id, string $field ): bool {
		// Map internal field names to API keys.
		static $field_to_api = array(
			'ok_with_dogs'    => 'is_ok_with_other_dogs',
			'ok_with_cats'    => 'is_ok_with_other_cats',
			'ok_with_kids'    => 'is_ok_with_other_kids',
			'shots_current'   => 'shots_up_to_date',
			'spayed_neutered' => 'is_spayed',
			'housebroken'     => 'is_housebroken',
			'special_needs'   => 'has_special_need',
			'hypoallergenic'  => 'is_hypoallergenic',
			'declawed'        => 'declawed',
		);

		$api_data = self::get_api_data( $id );
		$api_key  = $field_to_api[ $field ] ?? $field;
		$value    = $api_data[ $api_key ] ?? '';

		if ( is_bool( $value ) ) {
			return $value;
		}

		if ( is_string( $value ) ) {
			return in_array( strtolower( $value ), array( 'yes', '1', 'true' ), true );
		}

		return (bool) $value;
	}

	public static function get_gallery( int $id ): array {
		$api_data = self::get_api_data( $id );
		$images   = $api_data['images'] ?? [];
		if ( empty( $images ) || ! is_array( $images ) ) {
			return array();
		}
		return array_map(
			function ( $img ) use ( $api_data ) {
				return array(
					'url' => $img['image']['url'] ?? '',
					'alt' => $api_data['name'] ?? '',
				);
			},
			$images
		);
	}

	// === Taxonomy Queries ===

	public static function build_tax_query( array $filters ): array {
		$tax_query = array();

		foreach ( self::TAXONOMIES as $key => $taxonomy ) {
			if ( ! empty( $filters[ $key ] ) ) {
				$tax_query[] = array(
					'taxonomy' => $taxonomy,
					'field'    => 'slug',
					'terms'    => sanitize_text_field( $filters[ $key ] ),
				);
			}
		}

		if ( count( $tax_query ) > 1 ) {
			$tax_query['relation'] = 'AND';
		}

		return $tax_query;
	}

	public static function get_filter_options(): array {
		$options = array();

		foreach ( self::TAXONOMIES as $key => $taxonomy ) {
			$terms           = get_terms(
				array(
					'taxonomy'   => $taxonomy,
					'hide_empty' => true,
				)
			);
			$options[ $key ] = array();

			if ( ! is_wp_error( $terms ) ) {
				foreach ( $terms as $term ) {
					$options[ $key ][] = array(
						'value' => $term->slug,
						'label' => $term->name,
						'count' => $term->count,
					);
				}
			}
		}

		return $options;
	}

	// === Cursor Pagination ===

	public static function encode_cursor( int $id, string $date ): string {
		$data = wp_json_encode( compact( 'id', 'date' ) );
		return base64_encode( $data . '|' . wp_hash( $data ) );
	}

	public static function decode_cursor( string $cursor ): ?array {
		$decoded = base64_decode( $cursor, true );
		if ( ! $decoded || ! str_contains( $decoded, '|' ) ) {
			return null;
		}

		list( $data, $hash ) = explode( '|', $decoded, 2 );
		if ( ! hash_equals( wp_hash( $data ), $hash ) ) {
			return null;
		}

		$parsed = json_decode( $data, true );
		return isset( $parsed['id'], $parsed['date'] ) ? $parsed : null;
	}

	// === Favorites Storage ===

	public static function get_favorites(): array {
		if ( is_user_logged_in() ) {
			$data = get_user_meta( get_current_user_id(), '_pet_favorites', true );
		} else {
			$data = isset( $_COOKIE['pet_favorites'] ) ? json_decode( wp_unslash( $_COOKIE['pet_favorites'] ), true ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON-decoded, then absint-mapped below.
		}
		return is_array( $data ) ? array_map( 'absint', $data ) : array();
	}

	public static function save_favorites( array $ids ): void {
		$ids = array_map( 'absint', array_unique( $ids ) );

		if ( is_user_logged_in() ) {
			update_user_meta( get_current_user_id(), '_pet_favorites', $ids );
		}

		// Always set cookie for cross-session persistence.
		//
		// Guarded on headers_sent(): the call cannot succeed once output has
		// begun, and unguarded it emits a PHP warning into the response — which
		// happens for real whenever a theme or another plugin prints early, not
		// only under test.
		if ( ! headers_sent() ) {
			$expires = time() + ( 30 * DAY_IN_SECONDS );
			setcookie( 'pet_favorites', wp_json_encode( $ids ), $expires, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), false );
		}
	}

	// === Comparison Storage ===

	public static function get_comparison(): array {
		// Priority: URL > User Meta > Cookie.
		if ( isset( $_GET['compare'] ) ) {
			$ids = array_map( 'absint', explode( ',', sanitize_text_field( wp_unslash( $_GET['compare'] ) ) ) );
			return self::validate_pet_ids( $ids );
		}

		if ( is_user_logged_in() ) {
			$data = get_user_meta( get_current_user_id(), '_pet_comparison', true );
			if ( is_array( $data ) && ! empty( $data ) ) {
				return array_map( 'absint', $data );
			}
		}

		if ( isset( $_COOKIE['pet_comparison'] ) ) {
			$data = json_decode( wp_unslash( $_COOKIE['pet_comparison'] ), true ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- JSON-decoded, then absint-mapped below.
			return is_array( $data ) ? array_map( 'absint', $data ) : array();
		}

		return array();
	}

	public static function save_comparison( array $ids ): void {
		$ids = array_map( 'absint', array_unique( $ids ) );
		$ids = self::validate_pet_ids( $ids );

		if ( is_user_logged_in() ) {
			update_user_meta( get_current_user_id(), '_pet_comparison', $ids );
		}

		// See the note on the favorites cookie above.
		if ( ! headers_sent() ) {
			$expires = time() + ( 30 * DAY_IN_SECONDS );
			setcookie( 'pet_comparison', wp_json_encode( $ids ), $expires, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), false );
		}
	}

	public static function validate_pet_ids( array $ids ): array {
		if ( empty( $ids ) ) {
			return array();
		}

		$valid = get_posts(
			array(
				'post_type'      => 'vcps_pet',
				'post_status'    => 'publish',
				'post__in'       => $ids,
				'posts_per_page' => count( $ids ),
				'fields'         => 'ids',
			)
		);

		// Preserve original order.
		return array_values( array_intersect( $ids, $valid ) );
	}

	// === Interactivity State ===

	public static function get_initial_state(): array {
		$favorites  = self::get_favorites();
		$comparison = self::get_comparison();

		// Note: showFavoritesOnly is per-block context, not global state.
		// Each pet-listing-grid block has its own filters context.
		return array(
			'favorites'     => $favorites,
			'comparison'    => $comparison,
			'comparisonMax' => 4,
			'isLoading'     => false,
			'notification'  => null,
			'apiConfig'     => array(
				'restUrl' => rest_url(),
				'nonce'   => wp_create_nonce( 'wp_rest' ),
			),
			'gallery'       => array(
				'isOpen'       => false,
				'images'       => array(),
				'currentIndex' => 0,
			),
		);
	}

	public static function get_compatibility_summary( int $id ): string {
		$api_data = self::get_api_data( $id );
		$items    = array();

		$checks = array(
			'is_ok_with_other_dogs' => __( 'dogs', 'shelterkit-pets' ),
			'is_ok_with_other_cats' => __( 'cats', 'shelterkit-pets' ),
			'is_ok_with_other_kids' => __( 'kids', 'shelterkit-pets' ),
		);

		$truthy = array( 'yes', '1', 'true' );
		foreach ( $checks as $api_key => $label ) {
			$value = strtolower( (string) ( $api_data[ $api_key ] ?? '' ) );
			if ( in_array( $value, $truthy, true ) ) {
				$items[] = $label;
			}
		}

		return $items ? sprintf( /* translators: %s: comma-separated compatibility list */ __( 'Good with %s', 'shelterkit-pets' ), implode( ', ', $items ) ) : '';
	}
}
