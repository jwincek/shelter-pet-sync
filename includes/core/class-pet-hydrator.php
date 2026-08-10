<?php
/**
 * Pet Hydrator — config-driven WP_Post to entity array conversion.
 *
 * Solves the N+1 query problem by batch-priming caches before hydrating.
 * Measured on a 99-pet archive: 3 queries, where hydrating one at a time
 * would issue thousands.
 *
 * Priming has to cover more than the pets themselves. Their own meta and
 * terms were batched from the start, but the featured image, the gallery and
 * bonded partners are separate posts, and reaching for those cost a query
 * each — 202 queries for the same 99 pets before prime_related_caches().
 *
 * Usage:
 *   $pet  = Pet_Hydrator::get( $post_id );
 *   $pets = Pet_Hydrator::hydrate_many( $posts, 'grid' );
 *   $pet  = Pet_Hydrator::hydrate( $post, 'summary' );
 *
 * @package ShelterKit_Pets
 * @since 1.0.0
 */

declare( strict_types = 1 );

namespace Petsync\Core;

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
	 * Drop the per-request hydration caches.
	 *
	 * Hydration is memoised for the length of a request, which is right for a
	 * page render but wrong for any process that writes pet data and then reads
	 * it back: a long-running sync, WP-CLI, or a test. Those need to see their
	 * own writes.
	 *
	 * Leaves the entity config alone — that comes from a JSON file that cannot
	 * change mid-request. Everything else this class memoises MUST be cleared
	 * here; CacheFlushTest fails if a new cache property is added and missed.
	 *
	 * This is the only flush. A clear_cache() used to sit alongside it, clearing
	 * two of the four caches while presenting itself as a full flush — it
	 * predated the ps_id map added for the N+1 fix and was never updated.
	 * Nothing called it, which is why the divergence went unnoticed, and it was
	 * a trap for exactly the long-running processes this method exists for.
	 */
	public static function flush_cache(): void {
		self::$cache          = [];
		self::$api_data_cache = [];
		self::$ps_id_map      = [];
		self::$ps_id_checked  = [];
	}

	/**
	 * Provider record ID => local post ID, for the current request.
	 *
	 * @var array<int, int>
	 */
	private static array $ps_id_map = [];

	/**
	 * Provider record IDs already looked up, hits and misses alike.
	 *
	 * Separate from the map above so a miss is remembered and not re-queried
	 * on every pet that references the same absent partner.
	 *
	 * @var array<int, bool>
	 */
	private static array $ps_id_checked = [];

	/**
	 * Prime the caches for records hydration reaches for but does not own.
	 *
	 * update_postmeta_cache() primes the PETS' meta, which is where the
	 * batch-priming stopped. But the featured image, the gallery and bonded
	 * partners are all separate posts, and touching them pulled a query each:
	 * on a 99-pet archive, 305 queries against a documented ~5.
	 *
	 * Attachments are primed with their meta because image sizes live there —
	 * without it every get_the_post_thumbnail_url() call is a round trip.
	 *
	 * @param int[] $post_ids Pet post IDs whose caches are already primed.
	 */
	private static function prime_related_caches( array $post_ids ): void {
		if ( ! $post_ids ) {
			return;
		}

		$config = self::get_config();
		$prefix = $config['meta_prefix'] ?? '_pet_';

		$attachments = [];
		$partner_ids = [];

		foreach ( $post_ids as $id ) {
			$id = (int) $id;

			$thumbnail = (int) get_post_meta( $id, '_thumbnail_id', true );
			if ( $thumbnail ) {
				$attachments[] = $thumbnail;
			}

			$gallery = get_post_meta( $id, $prefix . 'gallery_ids', true );
			if ( is_array( $gallery ) ) {
				foreach ( $gallery as $attachment_id ) {
					$attachment_id = (int) $attachment_id;
					if ( $attachment_id > 0 ) {
						$attachments[] = $attachment_id;
					}
				}
			}

			// Priming necessarily runs before hydration, so there is no entity
			// to read here. Take the provider's key name from the declaration
			// rather than writing it out — bonded_pet_ids already maps it.
			$bonded   = Provider_Map::key_for( Provider_Map::for_pet( $id ), 'bonded_pet_ids' ) ?? 'grouped_pet_ids';
			$api_data = self::get_api_data( $id );
			foreach ( (array) ( $api_data[ $bonded ] ?? [] ) as $ps_id ) {
				$ps_id = (int) $ps_id;
				if ( $ps_id > 0 ) {
					$partner_ids[] = $ps_id;
				}
			}
		}

		$attachments = array_values( array_unique( $attachments ) );
		if ( $attachments ) {
			// Meta yes, terms no — attachments carry image sizes in meta and
			// nothing here reads their taxonomies.
			_prime_post_caches( $attachments, false, true );
		}

		self::prime_ps_id_map( $partner_ids );
	}

	/**
	 * Resolve provider record IDs to local posts in one query.
	 *
	 * Bonded pairs previously cost a query per partner per pet.
	 *
	 * @param int[] $ps_ids Provider record IDs.
	 */
	private static function prime_ps_id_map( array $ps_ids ): void {
		$ps_ids = array_values( array_unique( array_filter( array_map( 'intval', $ps_ids ) ) ) );
		$ps_ids = array_diff( $ps_ids, array_keys( self::$ps_id_checked ) );

		if ( ! $ps_ids ) {
			return;
		}

		$found = get_posts(
			[
				'post_type'   => 'vcps_pet',
				'post_status' => 'publish',
				'numberposts' => -1,
				'fields'      => 'ids',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- one batched lookup replacing a query per partner.
				'meta_query'  => [
					[
						'key'     => '_pet_ps_id',
						'value'   => array_map( 'strval', $ps_ids ),
						'compare' => 'IN',
					],
				],
			]
		);

		if ( $found ) {
			update_postmeta_cache( $found );

			foreach ( $found as $post_id ) {
				$ps_id = (int) get_post_meta( $post_id, '_pet_ps_id', true );
				if ( $ps_id ) {
					self::$ps_id_map[ $ps_id ] = (int) $post_id;
				}
			}
		}

		// Record misses too, so an absent partner is not re-queried per pet.
		foreach ( $ps_ids as $ps_id ) {
			self::$ps_id_checked[ $ps_id ] = true;
		}
	}

	/**
	 * Local post ID for a provider record ID, or null.
	 *
	 * @param int $ps_id Provider record ID.
	 * @return int|null Post ID.
	 */
	private static function resolve_ps_id( int $ps_id ): ?int {
		if ( ! isset( self::$ps_id_checked[ $ps_id ] ) ) {
			self::prime_ps_id_map( [ $ps_id ] );
		}

		return self::$ps_id_map[ $ps_id ] ?? null;
	}

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
		self::prime_related_caches( [ $post_id ] );

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

		// API fields, resolved in precedence order:
		//
		//   1. post meta   — a value entered by hand (editable_fields only)
		//   2. API snapshot — what the provider last sent
		//   3. the field's declared default
		//
		// Meta first is what lets a pet exist with no provider at all: a
		// hand-authored pet has no snapshot, so without this every one of these
		// fields would hydrate empty. Synced pets are unaffected — nothing
		// writes this meta for them, so they fall straight through to the
		// snapshot exactly as before, which is why this needs no migration.
		//
		// One JSON decode per pet, cached for the request. The get_post_meta
		// calls hit WordPress's per-post meta cache, primed on first access.
		$api_data   = self::get_api_data( $id );
		$api_fields = $config['api_fields'] ?? [];
		$provider   = Provider_Map::for_pet( $id );
		$editable   = $config['editable_fields'] ?? [];
		foreach ( $api_fields as $field_name => $field_config ) {
			if ( $include_fields && ! in_array( $field_name, $include_fields, true ) ) {
				continue;
			}
			// The provider's spelling comes from its map, not from the entity.
			// Null means there is no snapshot value to read: either this
			// provider does not carry the field, or the pet is hand-authored
			// and has no provider at all. Both fall through to meta and then
			// the declared default — the field must still appear in the entity
			// at its default, because dropping the key entirely would make
			// every consumer's `$pet['ok_with_cats']` an undefined-index
			// notice. The default is '', which the compatibility and health
			// blocks skip; it must never become 'unknown', which they render
			// as "Ask us" and so assert an assessment nobody made.
			$api_key = Provider_Map::key_for( $provider, $field_name );

			$raw = null;

			if ( isset( $editable[ $field_name ] ) ) {
				$manual = get_post_meta( $id, $prefix . $field_name, true );
				// An empty string is how WordPress reports "no such meta", so
				// it cannot be distinguished from a deliberately blanked value.
				// Treating empty as absent means clearing a field on a synced
				// pet reveals the provider's value again rather than blanking
				// it — the safer of the two behaviours, since the provider
				// remains the source of record for those pets.
				if ( '' !== $manual && null !== $manual && false !== $manual ) {
					$raw = $manual;
				}
			}

			if ( null === $raw ) {
				$from_api = ( null !== $api_key ) ? $api_data[ $api_key ] ?? null : null;
				$raw      = $from_api ?? $field_config['default'] ?? '';
			}

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

		// 3. Prime the records hydration reaches for but does not own —
		//    featured images, gallery attachments, bonded partners.
		self::prime_related_caches( $ids );

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
			'story_title' => sprintf( /* translators: %s: pet name */ __( 'Meet %s', 'shelterkit-pets' ), $entity['name'] ?? '' ),
			'adoption_title' => sprintf( /* translators: %s: pet name */ __( 'Adopt %s', 'shelterkit-pets' ), $entity['name'] ?? '' ),
			'adoption_fee_formatted' => self::compute_formatted_fee( $entity ),
			'has_adoption_info' => ! empty( $entity['adoption_fee'] ) || ! empty( $entity['adoption_form_url'] ),
			'gallery' => self::compute_gallery( $id, $entity ),
			'gallery_count' => count( self::compute_gallery( $id, $entity ) ),
			'is_new' => self::compute_is_new( $id, $post ),
			'favorited' => in_array( $id, \Petsync_Helpers::get_favorites(), true ),
			'description' => wp_kses_post( wpautop( $post->post_content ) ),
			'videos' => self::compute_videos( $entity ),
			'is_bonded_pair' => self::compute_is_bonded_pair( $entity ),
			'bonded_pair_names' => self::compute_bonded_pair_names( $id, $entity ),
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
		$date_str = '';
		foreach ( Provider_Map::shapes( Provider_Map::for_pet( $id ) )['intake_date']['paths'] ?? [] as $path ) {
			$candidate = self::dig( $api_data, (array) $path );
			if ( is_string( $candidate ) && '' !== $candidate ) {
				$date_str = $candidate;
				break;
			}
		}

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
		return self::image_url( $id );
	}

	/**
	 * Walk a path into a decoded API response.
	 *
	 * An api_key names one flat key, which is all Petstablished needs for its
	 * scalar fields — but photo URLs sit three levels down, and the provider
	 * this plugin was originally built against nested almost everything. So the
	 * shapes are declared as paths in entities.json and resolved here rather
	 * than written into the compute methods.
	 *
	 * @param array<mixed>       $data Decoded response, or a fragment of one.
	 * @param array<int, string|int> $path Segments to walk.
	 * @return mixed Null if any segment is missing.
	 */
	private static function dig( array $data, array $path ): mixed {
		$node = $data;
		foreach ( $path as $segment ) {
			if ( ! is_array( $node ) || ! array_key_exists( $segment, $node ) ) {
				return null;
			}
			$node = $node[ $segment ];
		}
		return $node;
	}

	/**
	 * The pet's image URL at a given size: the WordPress attachment if there is
	 * one, otherwise the provider's first photo.
	 *
	 * Public because Petsync_Helpers::get_image() needs the same answer at a
	 * different size, and two copies of "where does the provider keep photo
	 * URLs" is one copy too many.
	 *
	 * @param int    $id   Post ID.
	 * @param string $size Image size.
	 * @return string URL, or '' when there is none.
	 */
	public static function image_url( int $id, string $size = 'medium_large' ): string {
		$url = get_the_post_thumbnail_url( $id, $size );
		if ( $url ) {
			return $url;
		}

		$shape = Provider_Map::shapes( Provider_Map::for_pet( $id ) )['images'] ?? [];
		if ( empty( $shape['list'] ) || empty( $shape['url'] ) ) {
			return '';
		}

		$first = self::dig( self::get_api_data( $id ), array_merge( $shape['list'], [ 0 ], $shape['url'] ) );

		return is_string( $first ) ? $first : '';
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
			'ok_with_dogs' => __( 'dogs', 'shelterkit-pets' ),
			'ok_with_cats' => __( 'cats', 'shelterkit-pets' ),
			'ok_with_kids' => __( 'kids', 'shelterkit-pets' ),
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
			? sprintf( /* translators: %s: comma-separated compatibility list */ __( 'Good with %s', 'shelterkit-pets' ), implode( ', ', $items ) )
			: '';
	}

	private static function compute_formatted_fee( array $entity ): string {
		$fee = $entity['adoption_fee'] ?? '';
		return $fee ? '$' . number_format( (float) $fee, 0 ) : '';
	}

	private static function compute_gallery( int $id, array $entity = [] ): array {
		// Hand-curated images win, mirroring the precedence the scalar fields
		// use. Without this a pet with no provider gets only its featured
		// image, however many photos the shelter has.
		$manual = self::compute_manual_gallery( $id );
		if ( $manual ) {
			return $manual;
		}

		$shape = Provider_Map::shapes( Provider_Map::for_pet( $id ) )['images'] ?? [];
		if ( empty( $shape['list'] ) || empty( $shape['url'] ) ) {
			return [];
		}

		$images = self::dig( self::get_api_data( $id ), $shape['list'] );
		if ( empty( $images ) || ! is_array( $images ) ) {
			return [];
		}

		$url_path = $shape['url'];
		$alt      = $entity['name'] ?? '';

		return array_map(
			static fn( $img ) => [
				'url' => is_array( $img ) ? ( self::dig( $img, $url_path ) ?? '' ) : '',
				'alt' => $alt,
			],
			$images
		);
	}

	/**
	 * Gallery entries from attachments chosen in the editor.
	 *
	 * Alt text comes from the media library, falling back to the pet's name.
	 * The provider path can only ever use the name for every image, so a
	 * hand-curated gallery is genuinely more accessible than an imported one.
	 *
	 * @param int $id Pet post ID.
	 * @return array Gallery entries, empty when none are set.
	 */
	private static function compute_manual_gallery( int $id ): array {
		$config = self::get_config();
		$prefix = $config['meta_prefix'] ?? '_pet_';
		$ids    = get_post_meta( $id, $prefix . 'gallery_ids', true );

		if ( ! is_array( $ids ) || ! $ids ) {
			return [];
		}

		$gallery = [];

		foreach ( $ids as $attachment_id ) {
			$attachment_id = (int) $attachment_id;
			$url           = wp_get_attachment_image_url( $attachment_id, 'large' );

			// Skip attachments that have since been deleted rather than
			// emitting an <img> with an empty src.
			if ( ! $url ) {
				continue;
			}

			$alt = (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true );

			$gallery[] = [
				'url' => $url,
				'alt' => '' !== trim( $alt ) ? $alt : get_the_title( $id ),
			];
		}

		return $gallery;
	}

	/**
	 * Check if this pet is part of a bonded pair/group.
	 *
	 * Reads from the hydrated entity's bonded_group_id (sourced from API JSON).
	 */
	private static function compute_is_bonded_pair( array $entity ): bool {
		return ! empty( $entity['bonded_group_id'] );
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
	private static function compute_bonded_pair_names( int $id, array $entity ): array {
		if ( empty( $entity['bonded_group_id'] ) ) {
			return [];
		}

		$ps_ids = $entity['bonded_pet_ids'] ?? [];

		// Our own meta key, not the provider's `id`. The record id is already
		// stored under _pet_ps_id by the sync, so there is no reason to reach
		// back into the raw snapshot for it.
		$config    = self::get_config();
		$own_ps_id = (int) get_post_meta( $id, ( $config['meta_prefix'] ?? '_pet_' ) . 'ps_id', true );

		$partners = [];
		if ( is_array( $ps_ids ) ) {
			foreach ( $ps_ids as $ps_id ) {
				$ps_id = (int) $ps_id;
				if ( $ps_id === $own_ps_id ) {
					continue;
				}

				$partner_id = self::resolve_ps_id( $ps_id );

				if ( $partner_id ) {
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
			$siblings_str = $entity['bonded_names'] ?? '';
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
		$has_special = $entity['has_special_needs'] ?? '';
		if ( 'yes' !== strtolower( (string) $has_special ) ) {
			return '';
		}

		$detail = trim( $entity['special_needs_detail'] ?? '' );
		if ( $detail ) {
			return sprintf( /* translators: %s: special needs detail */ __( 'Special Needs: %s', 'shelterkit-pets' ), $detail );
		}

		return __( 'Special Needs', 'shelterkit-pets' );
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
}
