<?php
/**
 * Pet Hydrator — config-driven WP_Post to entity array conversion.
 *
 * Solves the N+1 query problem by batch-priming caches before hydrating.
 * A single call to hydrate_many() for 100 pets produces ~5 database queries
 * instead of ~2,000.
 *
 * Usage:
 *   $pet  = Pet_Hydrator::get( $post_id );
 *   $pets = Pet_Hydrator::hydrate_many( $posts, 'grid' );
 *   $pet  = Pet_Hydrator::hydrate( $post, 'summary' );
 *
 * @package Petstablished_Sync
 * @since 1.0.0
 */

declare( strict_types = 1 );

namespace Petstablished\Core;

use WP_Post;

class Pet_Hydrator {

	/**
	 * Per-request cache of hydrated entities keyed by post ID.
	 *
	 * This ensures that multiple binding callbacks for the same pet
	 * on a single page render share one hydration pass.
	 *
	 * @var array<int, array>
	 */
	private static array $cache = [];

	/**
	 * Entity config loaded from entities.json.
	 *
	 * @var array|null
	 */
	private static ?array $entity_config = null;

	/**
	 * Get a hydrated pet by post ID.
	 *
	 * Returns from per-request cache if available.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $profile Hydration profile: 'full', 'summary', 'grid'.
	 * @return array|null Hydrated entity or null if not found.
	 */
	public static function get( int $post_id, string $profile = 'full' ): ?array {
		$cache_key = $post_id . ':' . $profile;

		if ( isset( self::$cache[ $cache_key ] ) ) {
			return self::$cache[ $cache_key ];
		}

		$post = get_post( $post_id );
		if ( ! $post || 'vcps_pet' !== $post->post_type || 'publish' !== $post->post_status ) {
			return null;
		}

		// Prime caches for this single post.
		update_postmeta_cache( [ $post_id ] );
		update_object_term_cache( [ $post_id ], 'vcps_pet' );

		$entity                    = self::hydrate( $post, $profile );
		self::$cache[ $cache_key ] = $entity;

		return $entity;
	}

	/**
	 * Hydrate a single WP_Post into an entity array.
	 *
	 * Assumes caches have been primed. Call hydrate_many() or get() instead
	 * of calling this directly in a loop.
	 *
	 * @param WP_Post $post    The post to hydrate.
	 * @param string  $profile Hydration profile: 'full', 'summary', 'grid'.
	 * @return array Hydrated entity.
	 */
	public static function hydrate( WP_Post $post, string $profile = 'full' ): array {
		$config = self::get_config();
		$id     = $post->ID;
		$prefix = $config['meta_prefix'] ?? '_pet_';

		// Core fields.
		$entity = [
			'id'   => $id,
			'name' => $post->post_title,
		];

		// Determine which fields to include based on profile.
		$include_fields = self::get_profile_fields( $profile, $config );

		// Taxonomy fields — all cached after prime.
		$taxonomies = $config['taxonomies'] ?? [];
		foreach ( $taxonomies as $key => $tax_config ) {
			if ( $include_fields && ! in_array( $key, $include_fields, true ) ) {
				continue;
			}
			$taxonomy = $tax_config['taxonomy'];
			$terms    = get_the_terms( $id, $taxonomy );
			$term     = ( $terms && ! is_wp_error( $terms ) ) ? $terms[0] : null;

			$entity[ $key ] = $term ? $term->name : '';

			// Always include slugs alongside names — needed for filtering
			// and already used by listing grid (camelCase convention).
			$entity[ $key . 'Slug' ] = $term ? $term->slug : '';
		}

		// API fields — read from stored API JSON snapshot.
		// One JSON decode per pet, cached for the request.
		$api_data   = self::get_api_data( $id );
		$api_fields = $config['api_fields'] ?? [];
		foreach ( $api_fields as $field_name => $field_config ) {
			if ( $include_fields && ! in_array( $field_name, $include_fields, true ) ) {
				continue;
			}
			$api_key = $field_config['api_key'] ?? null;
			if ( null === $api_key ) {
				continue; // Computed api_field (like primary_image_url), skip.
			}
			$raw                   = $api_data[ $api_key ] ?? $field_config['default'] ?? '';
			$entity[ $field_name ] = self::cast_api_value( $raw, $field_config );
		}

		// The registered `fields` (ps_id, api_response, api_hash) are internal
		// storage plumbing — the change-detection hash and the raw API snapshot.
		// They are deliberately NOT surfaced in the entity, so neither the raw
		// Petstablished response nor internal IDs can leak through hydration
		// (e.g. via the public-permission get-pet ability).

		// Computed fields.
		$computed = $config['computed'] ?? [];
		foreach ( $computed as $field_name => $comp_config ) {
			if ( $include_fields && ! in_array( $field_name, $include_fields, true ) ) {
				continue;
			}
			$entity[ $field_name ] = self::compute_field( $id, $post, $entity, $field_name, $comp_config );
		}

		return $entity;
	}

	/**
	 * Decode and cache the stored API response for a pet.
	 *
	 * Returns the decoded array from _pet_api_response, or an empty array
	 * if no snapshot is stored (e.g., legacy data from before snapshots).
	 * Cached per-request so multiple field reads don't re-decode.
	 *
	 * @param int $id Post ID.
	 * @return array Decoded API response.
	 */
	private static array $api_data_cache = [];

	public static function get_api_data( int $id ): array {
		if ( isset( self::$api_data_cache[ $id ] ) ) {
			return self::$api_data_cache[ $id ];
		}

		$json                        = get_post_meta( $id, '_pet_api_response', true );
		$data                        = $json ? ( json_decode( $json, true ) ?: [] ) : [];
		self::$api_data_cache[ $id ] = $data;

		return $data;
	}

	/**
	 * Cast a value from the API response to the type defined in api_fields config.
	 *
	 * @param mixed $raw    Raw value from API JSON.
	 * @param array $config Field config from entities.json api_fields.
	 * @return mixed Cast value.
	 */
	private static function cast_api_value( mixed $raw, array $config ): mixed {
		$type = $config['type'] ?? 'string';

		return match ( $type ) {
			'tristate' => self::resolve_tristate( $raw ),
			'array'    => is_array( $raw ) ? $raw : [],
			'images'   => self::cast_images( $raw ),
			default    => is_string( $raw ) ? $raw : (string) ( $raw ?? $config['default'] ?? '' ),
		};
	}

	/**
	 * Normalize a tristate value to a canonical string.
	 *
	 * Petstablished sends these as mixed types — 'Yes', 'No', 'Not Sure',
	 * booleans, numeric strings. This normalizes them to exactly one of:
	 *   - 'yes'     — confirmed positive
	 *   - 'no'      — confirmed negative
	 *   - 'unknown' — data exists but is inconclusive (e.g. 'Not Sure')
	 *   - ''        — no data recorded (empty/null)
	 *
	 * Blocks can rely on this canonical shape without re-implementing
	 * normalization logic.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $value Raw tristate value from API or meta.
	 * @return string One of 'yes', 'no', 'unknown', or '' (no data).
	 */
	public static function resolve_tristate( mixed $value ): string {
		if ( $value === '' || $value === null ) {
			return '';
		}
		if ( is_bool( $value ) ) {
			return $value ? 'yes' : 'no';
		}
		$lower = strtolower( trim( (string) $value ) );
		if ( in_array( $lower, [ 'yes', 'true', '1' ], true ) ) {
			return 'yes';
		}
		if ( in_array( $lower, [ 'no', 'false', '0' ], true ) ) {
			return 'no';
		}
		return 'unknown';
	}

	/**
	 * Normalize images array from API format to our internal format.
	 */
	private static function cast_images( mixed $raw ): array {
		if ( ! is_array( $raw ) || empty( $raw ) ) {
			return [];
		}
		return array_map(
			fn( $img ) => [
				'url' => $img['image']['url'] ?? '',
				'alt' => '',
			],
			$raw
		);
	}

	/**
	 * Hydrate multiple posts with batch cache priming.
	 *
	 * This is the primary entry point for list views. It primes all caches
	 * in two queries, then hydrates each post from cache.
	 *
	 * @param WP_Post[] $posts   Array of WP_Post objects.
	 * @param string    $profile Hydration profile: 'full', 'summary', 'grid'.
	 * @return array[] Array of hydrated entities.
	 */
	public static function hydrate_many( array $posts, string $profile = 'full' ): array {
		if ( empty( $posts ) ) {
			return [];
		}

		$ids = wp_list_pluck( $posts, 'ID' );

		// === Batch cache priming ===
		// 1. Prime all post meta in one query.
		update_postmeta_cache( $ids );

		// 2. Prime all taxonomy term lookups in one query.
		update_object_term_cache( $ids, 'vcps_pet' );

		// Hydrate each post — all get_post_meta() and get_the_terms()
		// calls now hit the WP object cache, zero database queries.
		$entities = [];
		foreach ( $posts as $post ) {
			$entity                    = self::hydrate( $post, $profile );
			$cache_key                 = $post->ID . ':' . $profile;
			self::$cache[ $cache_key ] = $entity;
			$entities[]                = $entity;
		}

		return $entities;
	}

	/**
	 * Get the field list for a given profile.
	 *
	 * @param string $profile Profile name.
	 * @param array  $config  Entity config.
	 * @return array|null Field list, or null for 'full' (include everything).
	 */
	private static function get_profile_fields( string $profile, array $config ): ?array {
		return match ( $profile ) {
			'summary' => $config['summary_fields'] ?? null,
			'grid'    => $config['grid_fields'] ?? null,
			'full'    => null, // Include everything.
			default   => null,
		};
	}

	/**
	 * Compute a derived field value.
	 *
	 * @param int     $id      Post ID.
	 * @param WP_Post $post    Post object.
	 * @param array   $entity  Entity data built so far.
	 * @param string  $name    Computed field name.
	 * @param array   $config  Computed field config.
	 * @return mixed Computed value.
	 */
	private static function compute_field( int $id, WP_Post $post, array $entity, string $name, array $config ): mixed {
		return match ( $name ) {
			'image' => self::compute_image( $id ),
			'thumb' => self::compute_thumb( $id ),
			'url' => get_permalink( $id ),
			'tagline' => self::compute_tagline( $entity ),
			'compatibility' => self::compute_compatibility( $entity ),
			'story_title' => sprintf( /* translators: %s: pet name */ __( 'Meet %s', 'shelter-pet-sync' ), $entity['name'] ?? '' ),
			'adoption_title' => sprintf( /* translators: %s: pet name */ __( 'Adopt %s', 'shelter-pet-sync' ), $entity['name'] ?? '' ),
			'adoption_fee_formatted' => self::compute_formatted_fee( $entity ),
			'has_adoption_info' => ! empty( $entity['adoption_fee'] ) || ! empty( $entity['adoption_form_url'] ),
			'gallery' => self::compute_gallery( $id ),
			'gallery_count' => count( self::compute_gallery( $id ) ),
			'is_new' => self::compute_is_new( $id, $post ),
			'favorited' => in_array( $id, \Petstablished_Helpers::get_favorites(), true ),
			'description' => wp_kses_post( wpautop( $post->post_content ) ),
			'videos' => self::compute_videos( $entity ),
			'is_bonded_pair' => self::compute_is_bonded_pair( $id ),
			'bonded_pair_names' => self::compute_bonded_pair_names( $id ),
			'special_needs_summary' => self::compute_special_needs_summary( $entity ),
			default => null,
		};
	}

	/**
	 * Determine if a pet is "new" based on the API intake date.
	 *
	 * Uses the API's `date_aquired` (intake date) or `created_at` field
	 * from the stored API response snapshot. Falls back to the WordPress
	 * post_date if no API date is available.
	 *
	 * @param int     $id   Post ID.
	 * @param WP_Post $post Post object.
	 * @return bool Whether the pet is considered new (within 14 days).
	 */
	private static function compute_is_new( int $id, \WP_Post $post ): bool {
		$days_threshold = 14;
		$cutoff         = strtotime( "-{$days_threshold} days" );

		$api_data = self::get_api_data( $id );
		$date_str = $api_data['date_aquired'] ?? $api_data['created_at'] ?? '';

		if ( $date_str ) {
			$ts = strtotime( $date_str );
			if ( $ts ) {
				return $ts > $cutoff;
			}
		}

		// Fall back to WordPress post_date.
		return strtotime( $post->post_date ) > $cutoff;
	}

	private static function compute_image( int $id ): string {
		$url = get_the_post_thumbnail_url( $id, 'medium_large' );
		if ( $url ) {
			return $url;
		}
		// Fall back to first image from API response.
		$api_data = self::get_api_data( $id );
		return $api_data['images'][0]['image']['url'] ?? '';
	}

	/**
	 * Get the pet's thumbnail image URL.
	 *
	 * @param int $id Post ID.
	 * @return string Thumbnail URL or empty string.
	 */
	private static function compute_thumb( int $id ): string {
		$url = get_the_post_thumbnail_url( $id, 'thumbnail' );
		if ( $url ) {
			return $url;
		}
		// Fall back to the full image (better than nothing).
		return self::compute_image( $id );
	}

	private static function compute_tagline( array $entity ): string {
		$parts = array_filter(
			[
				$entity['animal'] ?? '',
				$entity['breed'] ?? '',
				$entity['age'] ?? '',
				$entity['sex'] ?? '',
				$entity['size'] ?? '',
			]
		);
		return implode( ' · ', $parts );
	}

	private static function compute_compatibility( array $entity ): string {
		$items  = [];
		$checks = [
			'ok_with_dogs' => __( 'dogs', 'shelter-pet-sync' ),
			'ok_with_cats' => __( 'cats', 'shelter-pet-sync' ),
			'ok_with_kids' => __( 'kids', 'shelter-pet-sync' ),
		];

		foreach ( $checks as $key => $label ) {
			// Must be exactly 'yes'. These are tristates resolving to
			// 'yes' | 'no' | 'unknown' | '', and an emptiness test counts 'no'
			// and 'unknown' as compatible because both are non-empty strings —
			// which advertised pets as good with dogs, cats or children when
			// the shelter had recorded the opposite.
			if ( 'yes' === strtolower( (string) ( $entity[ $key ] ?? '' ) ) ) {
				$items[] = $label;
			}
		}

		return $items
			? sprintf( /* translators: %s: comma-separated compatibility list */ __( 'Good with %s', 'shelter-pet-sync' ), implode( ', ', $items ) )
			: '';
	}

	private static function compute_formatted_fee( array $entity ): string {
		$fee = $entity['adoption_fee'] ?? '';
		return $fee ? '$' . number_format( (float) $fee, 0 ) : '';
	}

	private static function compute_gallery( int $id ): array {
		$api_data = self::get_api_data( $id );
		$images   = $api_data['images'] ?? [];
		if ( empty( $images ) || ! is_array( $images ) ) {
			return [];
		}
		return array_map(
			fn( $img ) => [
				'url' => $img['image']['url'] ?? '',
				'alt' => $api_data['name'] ?? '',
			],
			$images
		);
	}

	/**
	 * Check if this pet is part of a bonded pair/group.
	 *
	 * Reads from the hydrated entity's bonded_group_id (sourced from API JSON).
	 */
	private static function compute_is_bonded_pair( int $id ): bool {
		$api_data = self::get_api_data( $id );
		$group_id = $api_data['group_id'] ?? null;
		return ! empty( $group_id );
	}

	/**
	 * Resolve bonded pair partner names.
	 *
	 * Strategy:
	 * 1. Read grouped_pet_ids from API JSON (array of Petstablished IDs).
	 * 2. Look up local posts by _pet_ps_id to resolve names.
	 * 3. Exclude the current pet from the list.
	 * 4. Fall back to siblings_names if no local matches found.
	 *
	 * Returns an array of [ 'id' => local_post_id|null, 'name' => string ].
	 */
	private static function compute_bonded_pair_names( int $id ): array {
		$api_data = self::get_api_data( $id );
		$group_id = $api_data['group_id'] ?? null;
		if ( empty( $group_id ) ) {
			return [];
		}

		$ps_ids    = $api_data['grouped_pet_ids'] ?? [];
		$own_ps_id = (int) ( $api_data['id'] ?? 0 );

		$partners = [];
		if ( is_array( $ps_ids ) ) {
			foreach ( $ps_ids as $ps_id ) {
				$ps_id = (int) $ps_id;
				if ( $ps_id === $own_ps_id ) {
					continue;
				}

				$local = get_posts(
					[
						'post_type'   => 'vcps_pet',
						'post_status' => 'publish',
						'meta_key'    => '_pet_ps_id',
						'meta_value'  => (string) $ps_id,
						'numberposts' => 1,
						'fields'      => 'ids',
					]
				);

				if ( ! empty( $local ) ) {
					$partner_id = $local[0];
					$partners[] = [
						'id'   => $partner_id,
						'name' => get_the_title( $partner_id ),
						'url'  => get_permalink( $partner_id ),
					];
				}
			}
		}

		// Fall back to siblings_names from API.
		if ( empty( $partners ) ) {
			$siblings_str = $api_data['siblings_names'] ?? '';
			if ( $siblings_str ) {
				$parts = array_map( 'trim', explode( ',', $siblings_str ) );
				foreach ( $parts as $part ) {
					if ( ! $part ) {
						continue;
					}
					$clean_name = preg_replace( '/\s+PS\d+$/', '', $part );
					$partners[] = [
						'id'   => null,
						'name' => $clean_name,
						'url'  => '',
					];
				}
			}
		}

		return $partners;
	}

	/**
	 * Build a human-readable special needs summary.
	 *
	 * Combines the boolean flag with the detail text.
	 * Returns empty string if the pet has no special needs.
	 */
	private static function compute_special_needs_summary( array $entity ): string {
		$has_special = $entity['special_needs'] ?? '';
		if ( 'yes' !== strtolower( (string) $has_special ) ) {
			return '';
		}

		$detail = trim( $entity['special_needs_detail'] ?? '' );
		if ( $detail ) {
			return sprintf( /* translators: %s: special needs detail */ __( 'Special Needs: %s', 'shelter-pet-sync' ), $detail );
		}

		return __( 'Special Needs', 'shelter-pet-sync' );
	}

	/**
	 * Extract YouTube video IDs from the youtube_url and youtube_urls fields.
	 *
	 * Merges both sources, deduplicates, and returns an array of video IDs.
	 * Handles various YouTube URL formats:
	 *   - https://www.youtube.com/watch?v=VIDEO_ID
	 *   - https://youtu.be/VIDEO_ID
	 *   - https://www.youtube.com/embed/VIDEO_ID
	 *
	 * @param array $entity Hydrated entity (must contain youtube_url / youtube_urls).
	 * @return array Array of unique YouTube video ID strings.
	 */
	private static function compute_videos( array $entity ): array {
		$urls = array();

		// Single URL field.
		$single = trim( $entity['youtube_url'] ?? '' );
		if ( $single ) {
			$urls[] = $single;
		}

		// Array of URLs (up to 3 slots from the API).
		$multiple = $entity['youtube_urls'] ?? [];
		if ( is_array( $multiple ) ) {
			foreach ( $multiple as $url ) {
				$url = trim( (string) $url );
				if ( $url ) {
					$urls[] = $url;
				}
			}
		}

		// Extract video IDs and deduplicate.
		$ids = array();
		foreach ( $urls as $url ) {
			$id = self::extract_youtube_id( $url );
			if ( $id && ! in_array( $id, $ids, true ) ) {
				$ids[] = $id;
			}
		}

		return $ids;
	}

	/**
	 * Extract a YouTube video ID from various URL formats.
	 *
	 * @param string $url YouTube URL or video ID.
	 * @return string|null Video ID or null if not parseable.
	 */
	private static function extract_youtube_id( string $url ): ?string {
		// Already a bare video ID (11 characters, alphanumeric + dash/underscore).
		if ( preg_match( '/^[a-zA-Z0-9_-]{11}$/', $url ) ) {
			return $url;
		}

		// youtu.be/VIDEO_ID
		if ( preg_match( '#youtu\.be/([a-zA-Z0-9_-]{11})#', $url, $m ) ) {
			return $m[1];
		}

		// youtube.com/watch?v=VIDEO_ID or youtube.com/embed/VIDEO_ID
		if ( preg_match( '#youtube\.com/(?:watch\?.*v=|embed/)([a-zA-Z0-9_-]{11})#', $url, $m ) ) {
			return $m[1];
		}

		return null;
	}

	/**
	 * Get entity config from entities.json.
	 *
	 * @return array
	 */
	private static function get_config(): array {
		if ( null === self::$entity_config ) {
			self::$entity_config = Config::get_path( 'entities', 'entities.vcps_pet', [] );
		}
		return self::$entity_config;
	}

	/**
	 * Clear per-request cache.
	 *
	 * @param int|null $post_id Specific post ID to clear, or null for all.
	 */
	public static function clear_cache( ?int $post_id = null ): void {
		if ( null === $post_id ) {
			self::$cache          = [];
			self::$api_data_cache = [];
		} else {
			foreach ( array_keys( self::$cache ) as $key ) {
				if ( str_starts_with( $key, $post_id . ':' ) ) {
					unset( self::$cache[ $key ] );
				}
			}
			unset( self::$api_data_cache[ $post_id ] );
		}
	}
}
