<?php
/**
 * ShelterKit Pets Blocks - Block Bindings + Interactivity API
 *
 * Handles block bindings source registration, interactivity state,
 * and no-build block registration.
 *
 * Architecture (v2.1.0):
 * - Global store (petstablished): Favorites, comparison, pets cache
 * - Block stores (petsync/gallery, etc.): Block-specific UI state
 * - viewScriptModule in block.json: Automatic loading when block renders
 *
 * @package ShelterKit_Pets
 * @since 1.0.0
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Petsync_Blocks {

	public const NAMESPACE = 'petsync';

	private const BLOCKS = array(
		'pet-card',
		'pet-listing-grid',
		'pet-details',
		'pet-gallery',
		'pet-compare-bar',
		'pet-comparison',
		'pet-slider',
		'pet-filters',
		'pet-favorites-toggle',
		'pet-favorites-modal',
		// Inner blocks for pet-details (InnerBlocks + Block Bindings)
		'pet-actions',
		'pet-attributes',
		'pet-compatibility',
		'pet-health',
		'pet-adoption-cta',
		'adoption-action',
		'adoption-fee',
		'pet-tagline',
		'pet-breadcrumb',
		'back-to-top',
		'pet-toast',
	);

	public function __construct() {
		add_action( 'init', array( $this, 'register_block_bindings' ) );
		add_action( 'init', array( $this, 'register_blocks' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_interactivity' ) );
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_editor_assets' ) );
	}

	// === Block Bindings ===

	public function register_block_bindings(): void {
		// Pet entity data — reads from hydrated pet fields.
		register_block_bindings_source(
			'petsync/pet-data',
			array(
				'label'              => __( 'Pet Data', 'shelterkit-pets' ),
				'get_value_callback' => array( $this, 'get_binding_value' ),
				'uses_context'       => array( 'postId', 'postType' ),
			)
		);

		// Adoption statistics — aggregate data for archive/landing pages.
		register_block_bindings_source(
			'petsync/adoption-stats',
			array(
				'label'              => __( 'Adoption Statistics', 'shelterkit-pets' ),
				'get_value_callback' => array( $this, 'get_stats_binding_value' ),
				'uses_context'       => array(),
			)
		);
	}

	/**
	 * Map a binding key that a previous version of the plugin wrote into post
	 * content onto its current canonical field name.
	 *
	 * A block binding serialises its field name INTO the post:
	 *
	 *   <!-- wp:paragraph {"metadata":{"bindings":{"content":{
	 *        "source":"petsync/pet-data","args":{"key":"special_needs"}}}}} -->
	 *
	 * So a binding key is stored data in the same way a block name or an enum
	 * value is, and renaming a canonical field silently empties every block
	 * already bound to it. A meta migration does not help — the stale name is
	 * in the user's content, not in a meta row.
	 *
	 * Aliasing rather than rewriting content: rewriting posts to repair our own
	 * rename is a far worse trade than carrying a few lines forever.
	 *
	 * Cumulative. Never remove an entry. The keys here are, by definition,
	 * names that no longer exist anywhere else in the codebase — so a
	 * search-and-replace during a future rename will not match them, and must
	 * not be allowed to.
	 *
	 * @param string $key Key as written in post content.
	 * @return string Current canonical field name.
	 */
	private static function resolve_legacy_binding_key( string $key ): string {
		$aliases = array(
			// Renamed 2026-08-09 (#42). Ours collided in spelling with the
			// provider's `special_needs`, which maps to our
			// `special_needs_detail` — the same word meaning different things
			// on either side of the boundary.
			'special_needs' => 'has_special_needs',
		);

		return $aliases[ $key ] ?? $key;
	}

	public function get_binding_value( array $args, WP_Block $block ): ?string {
		$post_id = $block->context['postId'] ?? get_the_ID();

		if ( ! $post_id || 'vcps_pet' !== get_post_type( $post_id ) ) {
			return $this->get_placeholder( $args['key'] ?? '' );
		}

		$key = self::resolve_legacy_binding_key( $args['key'] ?? '' );

		// Route through the get-pet ability so bindings and REST share
		// the same code path, respecting permission callbacks and hooks.
		// The ability uses Pet_Hydrator internally with per-request caching,
		// so repeated calls for the same post ID are effectively free.
		$ability = function_exists( 'wp_get_ability' )
			? wp_get_ability( 'petsync/get-pet' )
			: null;

		if ( $ability ) {
			$pet = $ability->execute( [ 'id' => (int) $post_id ] );

			if ( is_wp_error( $pet ) ) {
				return $this->get_placeholder( $key );
			}
		} else {
			// Fallback if Abilities API not available (e.g. WP < 6.9).
			$pet = \Petsync\Core\Pet_Hydrator::get( $post_id );
			if ( ! $pet ) {
				return $this->get_placeholder( $key );
			}
		}

		// Direct field lookup from hydrated entity.
		$value = $pet[ $key ] ?? null;

		// Format for display.
		if ( is_bool( $value ) ) {
			return $value ? __( 'Yes', 'shelterkit-pets' ) : __( 'No', 'shelterkit-pets' );
		}

		return is_scalar( $value ) ? (string) $value : null;
	}

	private function get_placeholder( string $key ): string {
		$placeholders = array(
			'status' => __( '[Status]', 'shelterkit-pets' ),
			'animal' => __( '[Animal]', 'shelterkit-pets' ),
			'breed'  => __( '[Breed]', 'shelterkit-pets' ),
			'age'    => __( '[Age]', 'shelterkit-pets' ),
			'sex'    => __( '[Sex]', 'shelterkit-pets' ),
			'size'   => __( '[Size]', 'shelterkit-pets' ),
		);
		return $placeholders[ $key ] ?? '[' . ucfirst( str_replace( '_', ' ', $key ) ) . ']';
	}

	/**
	 * Get adoption stats value for block bindings.
	 *
	 * Routes through the Abilities API so bindings and REST share
	 * the same code path, following the shelter plugin pattern.
	 *
	 * Supports keys:
	 *   available_count, available_by_species, species_count,
	 *   newest_pet_name, total_pets, last_sync
	 *
	 * @param array    $args  Binding args including 'key'.
	 * @param WP_Block $block Block instance.
	 * @return string|null
	 */
	public function get_stats_binding_value( array $args, WP_Block $block ): ?string {
		$key    = $args['key'] ?? '';
		$status = $args['status'] ?? 'available';

		if ( ! $key ) {
			return null;
		}

		// Route through Abilities API if available.
		$ability = wp_get_ability( 'petsync/get-adoption-stats' );
		if ( $ability ) {
			// Cache the stats result per-request to avoid duplicate calls
			// when multiple stats bindings are on the same page.
			static $stats_cache = [];
			$cache_key          = $status;

			if ( ! isset( $stats_cache[ $cache_key ] ) ) {
				$result                    = $ability->execute( [ 'status' => $status ] );
				$stats_cache[ $cache_key ] = is_wp_error( $result ) ? [] : $result;
			}

			$stats = $stats_cache[ $cache_key ];

			if ( isset( $stats[ $key ] ) ) {
				$value = $stats[ $key ];

				// Format arrays/objects for display.
				if ( 'species_counts' === $key && is_array( $value ) ) {
					// For a specific species, check for 'species' arg.
					$species = $args['species'] ?? null;
					if ( $species ) {
						return isset( $value[ $species ] ) ? (string) $value[ $species ] : '0';
					}
					// Otherwise format as "Dogs: 23, Cats: 8".
					$parts = [];
					foreach ( $value as $name => $count ) {
						$parts[] = $name . ': ' . $count;
					}
					return implode( ', ', $parts );
				}

				return is_scalar( $value ) ? (string) $value : null;
			}
		}

		// Placeholder for editor preview.
		$placeholders = [
			'available_count'      => '42',
			'available_by_species' => '23 Dogs, 12 Cats, 7 Rabbits',
			'species_counts'       => '23',
			'newest_pet_name'      => 'Buddy',
			'total_pets'           => '58',
			'last_sync'            => '',
		];
		return $placeholders[ $key ] ?? '[' . ucfirst( str_replace( '_', ' ', $key ) ) . ']';
	}

	// === Block Registration ===

	public function register_blocks(): void {
		$blocks_dir = PETSYNC_DIR . 'blocks/';

		foreach ( self::BLOCKS as $block_name ) {
			$block_path = $blocks_dir . $block_name;

			if ( ! file_exists( $block_path . '/block.json' ) ) {
				continue;
			}

			// Register with render callback for server-side rendering.
			register_block_type( $block_path );
		}
	}

	// === Interactivity API ===

	public function enqueue_interactivity(): void {
		if ( ! $this->should_enqueue() ) {
			return;
		}

		// Load centralized store registration.
		require_once PETSYNC_DIR . 'includes/blocks/register-stores.php';

		\Petsync\Blocks\register_script_modules();
		\Petsync\Blocks\register_stores();

		// Enqueue minimal styles.
		wp_enqueue_style(
			self::NAMESPACE . '-frontend',
			PETSYNC_URL . 'assets/css/frontend.css',
			array(),
			PETSYNC_VERSION
		);
	}

	private function should_enqueue(): bool {
		// Always enqueue on pet-related archives/singles.
		if ( is_singular( 'vcps_pet' ) || is_post_type_archive( 'vcps_pet' ) ) {
			return true;
		}

		// Check pet taxonomy archives.
		foreach ( Petsync_Helpers::TAXONOMIES as $taxonomy ) {
			if ( is_tax( $taxonomy ) ) {
				return true;
			}
		}

		// Check if viewing a comparison URL.
		if ( isset( $_GET['compare'] ) ) {
			return true;
		}

		// Check for pet blocks in current post content.
		$post = get_post();
		if ( $post && has_block( 'petsync/', $post ) ) {
			return true;
		}

		// For FSE themes: check if any registered pet block will render.
		// This hooks earlier than template rendering, so we check broadly.
		if ( wp_is_block_theme() ) {
			// On front page, home, or any page that might have our blocks in templates.
			if ( is_front_page() || is_home() || is_page() ) {
				return true;
			}
		}

		// Fallback: check if this is the front page (static or otherwise).
		if ( is_front_page() ) {
			return true;
		}

		// 404 pages may have pet blocks in the template (e.g. slider).
		if ( is_404() ) {
			return true;
		}

		return false;
	}

	// === Editor Assets ===

	public function enqueue_editor_assets(): void {
		// Main blocks registration script.
		wp_enqueue_script(
			self::NAMESPACE . '-blocks-editor',
			PETSYNC_URL . 'assets/js/blocks-editor.js',
			array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n', 'wp-server-side-render', 'wp-data', 'wp-core-data' ),
			PETSYNC_VERSION,
			true
		);

		// Slider block editor controls.
		wp_enqueue_script(
			self::NAMESPACE . '-slider-editor',
			PETSYNC_URL . 'blocks/pet-slider/editor.js',
			array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n', 'wp-hooks', 'wp-compose', 'wp-data' ),
			PETSYNC_VERSION,
			true
		);

		// Listing grid block editor controls.
		wp_enqueue_script(
			self::NAMESPACE . '-listing-grid-editor',
			PETSYNC_URL . 'blocks/pet-listing-grid/editor.js',
			array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-i18n', 'wp-hooks', 'wp-compose' ),
			PETSYNC_VERSION,
			true
		);

		// Editor styles.
		wp_enqueue_style(
			self::NAMESPACE . '-editor',
			PETSYNC_URL . 'assets/css/editor.css',
			array(),
			PETSYNC_VERSION
		);

		// Binding helper sidebar script.
		wp_enqueue_script(
			self::NAMESPACE . '-binding-helper',
			PETSYNC_URL . 'assets/js/editor.js',
			array( 'wp-plugins', 'wp-edit-post', 'wp-element', 'wp-components', 'wp-data' ),
			PETSYNC_VERSION,
			true
		);

		wp_localize_script(
			self::NAMESPACE . '-binding-helper',
			'petstablishedEditor',
			array(
				'bindingKeys' => $this->get_binding_keys(),
			)
		);
	}

	private function get_binding_keys(): array {
		$keys = array();

		// Taxonomy keys.
		foreach ( array_keys( Petsync_Helpers::TAXONOMIES ) as $key ) {
			$keys[] = array(
				'key'    => $key,
				'type'   => 'taxonomy',
				'source' => 'petsync/pet-data',
				'desc'   => sprintf( /* translators: %s: pet field name */ __( 'Pet %s', 'shelterkit-pets' ), ucfirst( $key ) ),
			);
		}

		// API-sourced display fields (read from stored JSON).
		$api_fields = array(
			'weight'               => 'Weight',
			'adoption_fee'         => 'Adoption Fee (raw)',
			'numerical_age'        => 'Age (human readable)',
			'youtube_url'          => 'YouTube URL',
			'adoption_form_url'    => 'Adoption Application URL',
			'microchip_id'         => 'Microchip ID',
			'special_needs_detail' => 'Special Needs Description',
			'coat_pattern'         => 'Coat Pattern',
			'secondary_color'      => 'Secondary Color',
			'tertiary_color'       => 'Tertiary Color',
			'ok_with_dogs'         => 'Good with Dogs (tristate)',
			'ok_with_cats'         => 'Good with Cats (tristate)',
			'ok_with_kids'         => 'Good with Kids (tristate)',
			'shots_current'        => 'Shots Current (tristate)',
			'spayed_neutered'      => 'Spayed/Neutered (tristate)',
			'housebroken'          => 'Housebroken (tristate)',
			'has_special_needs'        => 'Has Special Needs (tristate)',
			'hypoallergenic'       => 'Hypoallergenic (tristate)',
			'declawed'             => 'Declawed (tristate)',
		);

		foreach ( $api_fields as $key => $desc ) {
			$keys[] = array(
				'key'    => $key,
				'type'   => 'api_field',
				'source' => 'petsync/pet-data',
				'desc'   => $desc,
			);
		}

		// Computed keys.
		$computed = array(
			'name'                   => 'Pet Name',
			'image'                  => 'Pet Photo URL',
			'url'                    => 'Pet Details URL',
			'tagline'                => 'Quick Facts Tagline',
			'compatibility'          => 'Compatibility Summary',
			'description'            => 'Pet Description/Story',
			'story_title'            => '"Meet [Name]" Title',
			'adoption_title'         => '"Adopt [Name]" Title',
			'adoption_fee_formatted' => 'Formatted Adoption Fee',
			'has_adoption_info'      => 'Has Adoption Info (boolean)',
			'archive_url'            => 'Pet Archive URL',
			'gallery_count'          => 'Gallery Photo Count',
			'is_new'                 => 'New Pet Badge (last 7 days)',
			'favorited'              => 'Is Favorited (boolean)',
			'is_bonded_pair'         => 'Is Bonded Pair (boolean)',
			'bonded_pair_names'      => 'Bonded Pair Partner Names',
			'special_needs_summary'  => 'Special Needs Summary Label',
		);

		foreach ( $computed as $key => $desc ) {
			$keys[] = array(
				'key'    => $key,
				'type'   => 'computed',
				'source' => 'petsync/pet-data',
				'desc'   => $desc,
			);
		}

		// Adoption stats keys (petsync/adoption-stats source).
		$stats = array(
			'available_count'      => 'Total Available Pets',
			'available_by_species' => 'Available by Species (formatted)',
			'species_counts'       => 'Species Count (use with species arg)',
			'newest_pet_name'      => 'Newest Pet Name',
			'total_pets'           => 'Total Pets (all statuses)',
			'last_sync'            => 'Last Sync Timestamp',
		);

		foreach ( $stats as $key => $desc ) {
			$keys[] = array(
				'key'    => $key,
				'type'   => 'stats',
				'source' => 'petsync/adoption-stats',
				'desc'   => $desc,
			);
		}

		return $keys;
	}
}
