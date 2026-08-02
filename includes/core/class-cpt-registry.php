<?php
/**
 * CPT Registry — auto-registers post types, taxonomies, and meta from config.
 *
 * Follows the Modern WordPress Plugin Development Guide §5.3.
 * Reads from config/post-types.json, config/taxonomies.json, and
 * config/entities.json to auto-register everything at the `init` hook.
 *
 * This replaces the hardcoded Petsync_CPT class.
 *
 * @package Shelter_Pets
 * @since   1.0.0
 */

declare( strict_types = 1 );

namespace Petsync\Core;

class CPT_Registry {

	/**
	 * Initialize the registry — hooks into WordPress `init`.
	 */
	public static function init(): void {
		add_action( 'init', [ self::class, 'register_post_types' ] );
		add_action( 'init', [ self::class, 'register_taxonomies' ] );
		add_action( 'init', [ self::class, 'register_meta' ], 11 );
		add_action( 'wp_after_insert_post', [ self::class, 'apply_default_terms' ], 10, 3 );
	}

	/**
	 * Register all post types from config/post-types.json.
	 */
	public static function register_post_types(): void {
		$post_types = Config::get_item( 'post-types', 'post_types', [] );

		foreach ( $post_types as $slug => $config ) {
			register_post_type(
				$slug,
				[
					'labels'        => self::build_labels( $config['labels'] ),
					'public'        => $config['public'] ?? false,
					'show_ui'       => $config['show_ui'] ?? true,
					'show_in_menu'  => $config['show_in_menu'] ?? true,
					'show_in_rest'  => $config['show_in_rest'] ?? true,
					'has_archive'   => $config['has_archive'] ?? false,
					'rewrite'       => $config['rewrite'] ?? false,
					'menu_icon'     => $config['menu_icon'] ?? 'dashicons-admin-post',
					'menu_position' => $config['menu_position'] ?? null,
					'supports'      => $config['supports'] ?? [ 'title', 'editor' ],
					'hierarchical'  => $config['hierarchical'] ?? false,
				]
			);
		}
	}

	/**
	 * Register all taxonomies from config/taxonomies.json.
	 */
	public static function register_taxonomies(): void {
		$taxonomies = Config::get_item( 'taxonomies', 'taxonomies', [] );

		foreach ( $taxonomies as $slug => $config ) {
			$args = [
				'labels'            => self::build_labels( $config['labels'] ),
				'public'            => $config['public'] ?? true,
				'show_ui'           => $config['show_ui'] ?? true,
				'show_in_rest'      => $config['show_in_rest'] ?? true,
				'hierarchical'      => $config['hierarchical'] ?? false,
				'rewrite'           => $config['rewrite'] ?? [ 'slug' => $slug ],
				'show_admin_column' => $config['show_admin_column'] ?? false,
			];

			// A term WordPress assigns when a post is saved with none chosen.
			//
			// Without this a hand-created pet has no pet_status, and the
			// listing grid filters on `available` — so it renders correctly on
			// its own page while being invisible on the archive. Pets imported
			// from a provider never hit this, because the sync writes a status
			// from the API.
			$default_slug = $config['default_term'] ?? null;
			if ( $default_slug ) {
				foreach ( $config['default_terms'] ?? [] as $term ) {
					if ( ( $term['slug'] ?? null ) === $default_slug ) {
						$args['default_term'] = [
							'name' => $term['name'],
							'slug' => $term['slug'],
						];
						break;
					}
				}
			}

			register_taxonomy( $slug, $config['post_types'] ?? [], $args );

			// Create default terms if specified.
			if ( ! empty( $config['default_terms'] ) ) {
				foreach ( $config['default_terms'] as $term ) {
					if ( ! term_exists( $term['slug'], $slug ) ) {
						wp_insert_term( $term['name'], $slug, [ 'slug' => $term['slug'] ] );
					}
				}
			}
		}
	}

	/**
	 * Assign a configured default term to a newly created post that has none.
	 *
	 * `default_term` on register_taxonomy covers most paths, but not the one
	 * the block editor uses: sending an empty term array counts as supplying
	 * terms, so WordPress skips the default. The result is a pet with no
	 * status, which renders fine on its own page and is invisible on the
	 * archive, because the listing grid filters on `available`.
	 *
	 * Creation only. On later saves an empty taxonomy is taken to mean the
	 * editor cleared it deliberately, and re-adding the term would fight them.
	 *
	 * Runs on wp_after_insert_post because that fires after the REST
	 * controller has handled terms — save_post is too early to see them.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 * @param bool    $update  Whether this is an update rather than a creation.
	 */
	public static function apply_default_terms( int $post_id, $post, bool $update ): void {
		if ( $update || ! $post instanceof \WP_Post ) {
			return;
		}

		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		$taxonomies = Config::get_item( 'taxonomies', 'taxonomies', [] );

		foreach ( $taxonomies as $slug => $config ) {
			$default = $config['default_term'] ?? null;

			if ( ! $default || ! in_array( $post->post_type, (array) ( $config['post_types'] ?? [] ), true ) ) {
				continue;
			}

			$existing = wp_get_object_terms( $post_id, $slug, [ 'fields' => 'ids' ] );

			if ( is_wp_error( $existing ) || ! empty( $existing ) ) {
				continue;
			}

			wp_set_object_terms( $post_id, $default, $slug );
		}
	}

	/**
	 * Register post meta from config/entities.json fields.
	 *
	 * Uses the entity's field definitions to register meta with correct
	 * types, sanitization, and REST visibility.
	 */
	public static function register_meta(): void {
		$entities = Config::get_item( 'entities', 'entities', [] );

		foreach ( $entities as $entity_key => $config ) {
			$post_type = $config['post_type'] ?? $entity_key;
			$prefix    = $config['meta_prefix'] ?? '_';

			$registered = [];

			foreach ( $config['fields'] ?? [] as $field => $field_config ) {
				$declared = $field_config['type'] ?? 'string';
				$schema   = self::get_rest_schema( $declared );
				$in_rest  = $field_config['show_in_rest'] ?? true;

				register_post_meta(
					$post_type,
					$prefix . $field,
					[
						'type'              => self::map_type( $declared ),
						'description'       => $field_config['description'] ?? '',
						'single'            => true,
						// A schema-bearing type must pass its schema through, or
						// REST rejects the value and the save fails silently —
						// which in the editor looks like a control that does nothing.
						'show_in_rest'      => ( $in_rest && $schema ) ? [ 'schema' => $schema ] : $in_rest,
						'sanitize_callback' => self::get_sanitizer( $declared ),
						'auth_callback'     => fn() => current_user_can( 'edit_posts' ),
					]
				);

				$registered[ $field ] = true;
			}

			// Editable api_fields — the manual-entry counterpart of the provider
			// mapping. Registering these gives a pet with no provider somewhere
			// to store the values a sync would otherwise have supplied.
			// Pet_Hydrator prefers them over the API snapshot, so synced pets
			// are unaffected until someone actually writes one. Type and
			// sanitizer come from the api_fields declaration, so the field's
			// shape has a single source of truth.
			$api_fields = $config['api_fields'] ?? [];

			foreach ( $config['editable_fields'] ?? [] as $field => $editable ) {
				// Already registered above as an entity field — gallery_ids is
				// storage in its own right rather than a provider mapping.
				if ( isset( $registered[ $field ] ) ) {
					continue;
				}

				$api_config = $api_fields[ $field ] ?? null;

				// Declared editable but absent from api_fields: the hydrator
				// would never read it back, so registering the key would create
				// a write-only field. The config validator flags this too.
				if ( null === $api_config ) {
					continue;
				}

				$declared_type = $api_config['type'] ?? 'string';

				register_post_meta(
					$post_type,
					$prefix . $field,
					[
						'type'              => self::map_type( $declared_type ),
						'description'       => $editable['label'] ?? $field,
						'single'            => true,
						'show_in_rest'      => true,
						'sanitize_callback' => self::get_sanitizer( $declared_type ),
						'auth_callback'     => fn() => current_user_can( 'edit_posts' ),
					]
				);
			}
		}
	}

	/**
	 * Build standard WordPress labels from a simple singular/plural config.
	 */
	private static function build_labels( array $config ): array {
		$singular = $config['singular'];
		$plural   = $config['plural'];

		return [
			'name'               => $plural,
			'singular_name'      => $singular,
			'menu_name'          => $config['menu_name'] ?? $plural,
			'all_items'          => "All $plural",
			'add_new'            => 'Add New',
			'add_new_item'       => "Add New $singular",
			'edit_item'          => "Edit $singular",
			'new_item'           => "New $singular",
			'view_item'          => "View $singular",
			'search_items'       => "Search $plural",
			'not_found'          => "No $plural found",
			'not_found_in_trash' => "No $plural found in Trash",
		];
	}

	/**
	 * Map entity field type to WordPress meta type.
	 */
	private static function map_type( string $type ): string {
		return match ( $type ) {
			'integer'    => 'integer',
			'number'     => 'number',
			'boolean'    => 'boolean',
			'array', 'json_array', 'attachment_ids' => 'array',
			'object'     => 'object',
			default      => 'string',
		};
	}

	/**
	 * REST schema for a field type, where the type needs one.
	 *
	 * Array meta is rejected by the REST API without an explicit item schema —
	 * the value silently fails to save, which in the editor looks like a
	 * control that does nothing.
	 *
	 * @param string $type Declared field type.
	 * @return array|null Schema, or null when the default handling is right.
	 */
	private static function get_rest_schema( string $type ): ?array {
		return match ( $type ) {
			'attachment_ids' => [
				'type'  => 'array',
				'items' => [ 'type' => 'integer' ],
			],
			default => null,
		};
	}

	/**
	 * Get sanitizer callback for a field type.
	 */
	private static function get_sanitizer( string $type ): callable {
		return match ( $type ) {
			'integer'    => 'absint',
			// Closures, not the internal functions directly: WordPress calls a
			// sanitize_callback with three arguments, and a PHP internal throws
			// an ArgumentCountError when handed extras.
			'number'     => static fn( $v ) => (float) $v,
			'boolean'    => 'rest_sanitize_boolean',
			'email'      => 'sanitize_email',
			'url'        => 'esc_url_raw',
			// An array run through sanitize_text_field collapses to the string
			// "Array". Attachment IDs are coerced individually and anything
			// that is not a positive integer is dropped.
			//
			// intval rather than absint: absint takes the absolute value, so
			// -5 would silently become attachment 5 — a real, unrelated image
			// rather than a rejected input.
			'attachment_ids' => static function ( $value ) {
				if ( ! is_array( $value ) ) {
					return [];
				}
				return array_values(
					array_filter(
						array_map( 'intval', $value ),
						static fn( $id ) => $id > 0
					)
				);
			},
			default      => 'sanitize_text_field',
		};
	}
}
