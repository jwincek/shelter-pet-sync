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

	// === Pet Data Formatting ===

	public static function get_image( int $id, string $size = 'medium_large' ): string {
		// Delegates rather than duplicating: where the provider keeps photo URLs
		// is declared once, in entities.json api_shapes, and resolved once, in
		// Pet_Hydrator. This method existed as a near-copy that differed only in
		// taking a size.
		return \Petsync\Core\Pet_Hydrator::image_url( $id, $size );
	}

	// === Taxonomy Queries ===

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
}
