<?php
/**
 * CPT Registry — auto-registers post types, taxonomies, and meta from config.
 *
 * Follows the Modern WordPress Plugin Development Guide §5.3.
 * Reads from config/post-types.json, config/taxonomies.json, and
 * config/entities.json to auto-register everything at the `init` hook.
 *
 * This replaces the hardcoded Petstablished_CPT class.
 *
 * @package Petstablished_Sync
 * @since   1.0.0
 */

declare( strict_types = 1 );

namespace Petstablished\Core;

class CPT_Registry {

	/**
	 * Initialize the registry — hooks into WordPress `init`.
	 */
	public static function init(): void {
		add_action( 'init', [ self::class, 'register_post_types' ] );
		add_action( 'init', [ self::class, 'register_taxonomies' ] );
		add_action( 'init', [ self::class, 'register_meta' ], 11 );
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
			register_taxonomy(
				$slug,
				$config['post_types'] ?? [],
				[
					'labels'            => self::build_labels( $config['labels'] ),
					'public'            => $config['public'] ?? true,
					'show_ui'           => $config['show_ui'] ?? true,
					'show_in_rest'      => $config['show_in_rest'] ?? true,
					'hierarchical'      => $config['hierarchical'] ?? false,
					'rewrite'           => $config['rewrite'] ?? [ 'slug' => $slug ],
					'show_admin_column' => $config['show_admin_column'] ?? false,
				]
			);

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

			foreach ( $config['fields'] ?? [] as $field => $field_config ) {
				$type = self::map_type( $field_config['type'] ?? 'string' );

				register_post_meta(
					$post_type,
					$prefix . $field,
					[
						'type'              => $type,
						'description'       => $field_config['description'] ?? '',
						'single'            => true,
						'show_in_rest'      => $field_config['show_in_rest'] ?? true,
						'sanitize_callback' => self::get_sanitizer( $field_config['type'] ?? 'string' ),
						'auth_callback'     => fn() => current_user_can( 'edit_posts' ),
					]
				);
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
			'array', 'json_array' => 'array',
			'object'     => 'object',
			default      => 'string',
		};
	}

	/**
	 * Get sanitizer callback for a field type.
	 */
	private static function get_sanitizer( string $type ): callable {
		return match ( $type ) {
			'integer'    => 'absint',
			'number'     => 'floatval',
			'boolean'    => 'rest_sanitize_boolean',
			'email'      => 'sanitize_email',
			'url'        => 'esc_url_raw',
			default      => 'sanitize_text_field',
		};
	}
}
