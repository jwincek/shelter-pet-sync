<?php
/**
 * Petstablished Block Variations
 *
 * Registers block variations for core blocks pre-configured with pet data bindings.
 * These "Pet Name", "Pet Breed", etc. variations can be inserted anywhere the
 * petsync/pet-data binding source is available.
 *
 * @package Petstablished_Sync
 * @since 1.0.0
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Petstablished_Variations {

	/**
	 * Variation definitions organized by core block type.
	 */
	private const VARIATIONS = array(
		// === Headings ===
		'core/heading'   => array(
			array(
				'name'        => 'pet-name',
				'title'       => 'Pet Name',
				'description' => 'Displays the pet\'s name as a heading.',
				'icon'        => 'pets',
				'keywords'    => array( 'pet', 'name', 'title' ),
				'binding_key' => 'name',
				'attributes'  => array( 'level' => 1 ),
			),
			array(
				'name'        => 'pet-story-title',
				'title'       => 'Pet Story Title',
				'description' => 'Displays "Meet [Pet Name]" as a heading.',
				'icon'        => 'format-quote',
				'keywords'    => array( 'pet', 'story', 'meet', 'title' ),
				'binding_key' => 'story_title',
				'attributes'  => array( 'level' => 2 ),
			),
			array(
				'name'        => 'pet-adoption-title',
				'title'       => 'Pet Adoption Title',
				'description' => 'Displays "Adopt [Pet Name]" as a heading.',
				'icon'        => 'heart',
				'keywords'    => array( 'pet', 'adopt', 'title' ),
				'binding_key' => 'adoption_title',
				'attributes'  => array( 'level' => 2 ),
			),
		),

		// === Paragraphs (Taxonomy) ===
		'core/paragraph' => array(
			array(
				'name'        => 'pet-status',
				'title'       => 'Pet Status',
				'description' => 'Displays the pet\'s adoption status.',
				'icon'        => 'tag',
				'keywords'    => array( 'pet', 'status', 'available', 'adopted' ),
				'binding_key' => 'status',
			),
			array(
				'name'        => 'pet-animal',
				'title'       => 'Pet Animal Type',
				'description' => 'Displays the animal type (Dog, Cat, etc.).',
				'icon'        => 'pets',
				'keywords'    => array( 'pet', 'animal', 'type', 'species' ),
				'binding_key' => 'animal',
			),
			array(
				'name'        => 'pet-breed',
				'title'       => 'Pet Breed',
				'description' => 'Displays the pet\'s breed.',
				'icon'        => 'pets',
				'keywords'    => array( 'pet', 'breed' ),
				'binding_key' => 'breed',
			),
			array(
				'name'        => 'pet-age',
				'title'       => 'Pet Age',
				'description' => 'Displays the pet\'s age category.',
				'icon'        => 'calendar',
				'keywords'    => array( 'pet', 'age', 'years', 'old' ),
				'binding_key' => 'age',
			),
			array(
				'name'        => 'pet-sex',
				'title'       => 'Pet Sex',
				'description' => 'Displays the pet\'s sex (Male/Female).',
				'icon'        => 'admin-users',
				'keywords'    => array( 'pet', 'sex', 'gender', 'male', 'female' ),
				'binding_key' => 'sex',
			),
			array(
				'name'        => 'pet-size',
				'title'       => 'Pet Size',
				'description' => 'Displays the pet\'s size category.',
				'icon'        => 'image-crop',
				'keywords'    => array( 'pet', 'size', 'small', 'medium', 'large' ),
				'binding_key' => 'size',
			),
			array(
				'name'        => 'pet-color',
				'title'       => 'Pet Color',
				'description' => 'Displays the pet\'s color.',
				'icon'        => 'art',
				'keywords'    => array( 'pet', 'color', 'colour' ),
				'binding_key' => 'color',
			),
			array(
				'name'        => 'pet-coat',
				'title'       => 'Pet Coat',
				'description' => 'Displays the pet\'s coat type.',
				'icon'        => 'admin-appearance',
				'keywords'    => array( 'pet', 'coat', 'fur', 'hair' ),
				'binding_key' => 'coat',
			),
			// Computed
			array(
				'name'        => 'pet-tagline',
				'title'       => 'Pet Tagline',
				'description' => 'Displays quick facts: Animal · Breed · Age · Sex · Size.',
				'icon'        => 'editor-quote',
				'keywords'    => array( 'pet', 'tagline', 'summary', 'quick', 'facts' ),
				'binding_key' => 'tagline',
			),
			array(
				'name'        => 'pet-compatibility',
				'title'       => 'Pet Compatibility',
				'description' => 'Displays "Good with dogs, cats, kids" summary.',
				'icon'        => 'groups',
				'keywords'    => array( 'pet', 'compatibility', 'good', 'with' ),
				'binding_key' => 'compatibility',
			),
			// Meta
			array(
				'name'        => 'pet-weight',
				'title'       => 'Pet Weight',
				'description' => 'Displays the pet\'s weight.',
				'icon'        => 'dashboard',
				'keywords'    => array( 'pet', 'weight', 'pounds', 'lbs' ),
				'binding_key' => 'weight',
			),
			array(
				'name'        => 'pet-adoption-fee',
				'title'       => 'Pet Adoption Fee',
				'description' => 'Displays the formatted adoption fee.',
				'icon'        => 'money-alt',
				'keywords'    => array( 'pet', 'fee', 'adoption', 'price', 'cost' ),
				'binding_key' => 'adoption_fee_formatted',
			),
			array(
				'name'        => 'pet-description',
				'title'       => 'Pet Description',
				'description' => 'Displays the pet\'s full story/description.',
				'icon'        => 'editor-paragraph',
				'keywords'    => array( 'pet', 'description', 'story', 'bio', 'about' ),
				'binding_key' => 'description',
			),
			// Boolean fields
			array(
				'name'        => 'pet-shots-current',
				'title'       => 'Pet Shots Current',
				'description' => 'Displays whether vaccinations are up to date.',
				'icon'        => 'yes-alt',
				'keywords'    => array( 'pet', 'shots', 'vaccinations', 'medical', 'health' ),
				'binding_key' => 'shots_current',
			),
			array(
				'name'        => 'pet-spayed-neutered',
				'title'       => 'Pet Spayed/Neutered',
				'description' => 'Displays whether the pet has been spayed or neutered.',
				'icon'        => 'yes-alt',
				'keywords'    => array( 'pet', 'spayed', 'neutered', 'fixed', 'medical' ),
				'binding_key' => 'spayed_neutered',
			),
			array(
				'name'        => 'pet-housebroken',
				'title'       => 'Pet Housebroken',
				'description' => 'Displays whether the pet is housetrained.',
				'icon'        => 'admin-home',
				'keywords'    => array( 'pet', 'housebroken', 'housetrained', 'trained' ),
				'binding_key' => 'housebroken',
			),
			array(
				'name'        => 'pet-good-with-dogs',
				'title'       => 'Pet Good With Dogs',
				'description' => 'Displays whether the pet is good with other dogs.',
				'icon'        => 'groups',
				'keywords'    => array( 'pet', 'dogs', 'good', 'compatible', 'friendly' ),
				'binding_key' => 'ok_with_dogs',
			),
			array(
				'name'        => 'pet-good-with-cats',
				'title'       => 'Pet Good With Cats',
				'description' => 'Displays whether the pet is good with cats.',
				'icon'        => 'groups',
				'keywords'    => array( 'pet', 'cats', 'good', 'compatible', 'friendly' ),
				'binding_key' => 'ok_with_cats',
			),
			array(
				'name'        => 'pet-good-with-kids',
				'title'       => 'Pet Good With Kids',
				'description' => 'Displays whether the pet is good with children.',
				'icon'        => 'groups',
				'keywords'    => array( 'pet', 'kids', 'children', 'good', 'compatible', 'family' ),
				'binding_key' => 'ok_with_kids',
			),
			array(
				'name'        => 'pet-special-needs',
				'title'       => 'Pet Special Needs',
				'description' => 'Displays whether the pet has special needs.',
				'icon'        => 'heart',
				'keywords'    => array( 'pet', 'special', 'needs', 'medical', 'disability' ),
				'binding_key' => 'special_needs',
			),
			// Additional computed
			array(
				'name'        => 'pet-gallery-count',
				'title'       => 'Pet Gallery Count',
				'description' => 'Displays how many gallery photos the pet has.',
				'icon'        => 'format-gallery',
				'keywords'    => array( 'pet', 'gallery', 'photos', 'count', 'images' ),
				'binding_key' => 'gallery_count',
			),
		),

		// === Images ===
		'core/image'     => array(
			array(
				'name'        => 'pet-photo',
				'title'       => 'Pet Photo',
				'description' => 'Displays the pet\'s primary photo.',
				'icon'        => 'format-image',
				'keywords'    => array( 'pet', 'photo', 'image', 'picture' ),
				'binding_key' => 'image',
				'alt_key'     => 'name',
				'attributes'  => array( 'sizeSlug' => 'large' ),
			),
			array(
				'name'         => 'pet-thumbnail',
				'title'        => 'Pet Thumbnail',
				'description'  => 'Displays the pet\'s photo as a thumbnail.',
				'icon'         => 'format-image',
				'keywords'     => array( 'pet', 'thumbnail', 'small', 'thumb' ),
				'binding_key'  => 'image',
				'binding_args' => array( 'size' => 'thumbnail' ),
				'alt_key'      => 'name',
				'attributes'   => array( 'sizeSlug' => 'thumbnail' ),
			),
		),

		// === Buttons ===
		'core/button'    => array(
			array(
				'name'         => 'pet-view-details',
				'title'        => 'View Pet Details',
				'description'  => 'Link button to the pet\'s detail page.',
				'icon'         => 'visibility',
				'keywords'     => array( 'pet', 'view', 'details', 'more', 'link' ),
				'binding_key'  => 'url',
				'binding_attr' => 'url',
				'attributes'   => array(
					'text'      => 'View Details',
					'className' => 'is-style-outline',
				),
			),
			array(
				'name'         => 'pet-adopt-button',
				'title'        => 'Adopt Button',
				'description'  => 'Link button to the adoption application form.',
				'icon'         => 'heart',
				'keywords'     => array( 'pet', 'adopt', 'apply', 'application', 'button' ),
				'binding_key'  => 'adoption_form_url',
				'binding_attr' => 'url',
				'attributes'   => array(
					'text'       => 'Start Adoption',
					'className'  => 'is-style-fill',
					'linkTarget' => '_blank',
					'rel'        => 'noopener',
				),
			),
		),
	);

	/**
	 * Adoption stats variations using the petsync/adoption-stats source.
	 * These work on any page, not just pet single/archive.
	 */
	private const STATS_VARIATIONS = array(
		'core/heading'   => array(
			array(
				'name'        => 'pet-available-count-heading',
				'title'       => 'Available Pets Count',
				'description' => 'Displays the number of available pets as a heading.',
				'icon'        => 'chart-bar',
				'keywords'    => array( 'pet', 'available', 'count', 'total', 'stats' ),
				'binding_key' => 'available_count',
				'attributes'  => array( 'level' => 2 ),
			),
		),
		'core/paragraph' => array(
			array(
				'name'        => 'pet-available-by-species',
				'title'       => 'Available By Species',
				'description' => 'Displays available pets by species (e.g. "23 Dogs, 8 Cats").',
				'icon'        => 'chart-bar',
				'keywords'    => array( 'pet', 'available', 'species', 'dogs', 'cats', 'stats' ),
				'binding_key' => 'available_by_species',
			),
			array(
				'name'        => 'pet-newest-name',
				'title'       => 'Newest Pet Name',
				'description' => 'Displays the name of the most recently added pet.',
				'icon'        => 'star-filled',
				'keywords'    => array( 'pet', 'newest', 'recent', 'latest', 'new' ),
				'binding_key' => 'newest_pet_name',
			),
			array(
				'name'        => 'pet-total-count',
				'title'       => 'Total Pets Count',
				'description' => 'Displays the total number of pets across all statuses.',
				'icon'        => 'chart-bar',
				'keywords'    => array( 'pet', 'total', 'count', 'all', 'stats' ),
				'binding_key' => 'total_pets',
			),
		),
	);

	/**
	 * Compound variations using core/group with multiple bound inner blocks.
	 */
	private const GROUP_VARIATIONS = array(
		array(
			'name'        => 'pet-header',
			'title'       => 'Pet Header',
			'description' => 'Pet name with status badge.',
			'icon'        => 'heading',
			'keywords'    => array( 'pet', 'header', 'title', 'name', 'status' ),
			'innerBlocks' => array(
				array( 'core/heading', array( 'level' => 1 ), 'name' ),
				array( 'core/paragraph', array( 'className' => 'pet-status-badge' ), 'status' ),
			),
			'attributes'  => array(
				'layout' => array(
					'type'              => 'flex',
					'flexWrap'          => 'wrap',
					'justifyContent'    => 'space-between',
					'verticalAlignment' => 'center',
				),
			),
		),
		array(
			'name'        => 'pet-quick-facts',
			'title'       => 'Pet Quick Facts',
			'description' => 'Breed, age, sex displayed inline.',
			'icon'        => 'list-view',
			'keywords'    => array( 'pet', 'facts', 'quick', 'breed', 'age', 'sex' ),
			'innerBlocks' => array(
				array( 'core/paragraph', array( 'className' => 'pet-fact' ), 'breed' ),
				array( 'core/paragraph', array( 'className' => 'pet-fact' ), 'age' ),
				array( 'core/paragraph', array( 'className' => 'pet-fact' ), 'sex' ),
			),
			'attributes'  => array(
				'className' => 'pet-quick-facts',
				'layout'    => array(
					'type'     => 'flex',
					'flexWrap' => 'wrap',
				),
			),
		),
		array(
			'name'        => 'pet-card-content',
			'title'       => 'Pet Card Content',
			'description' => 'Name, tagline, and view button for cards.',
			'icon'        => 'id-alt',
			'keywords'    => array( 'pet', 'card', 'content', 'name', 'tagline' ),
			'innerBlocks' => array(
				array(
					'core/heading',
					array(
						'level'     => 3,
						'className' => 'pet-card-name',
					),
					'name',
				),
				array( 'core/paragraph', array( 'className' => 'pet-card-tagline' ), 'tagline' ),
				array(
					'core/button',
					array(
						'text'      => 'View Details',
						'className' => 'pet-card-button',
					),
					'url',
					'url',
				),
			),
			'attributes'  => array(
				'className' => 'pet-card-content',
			),
		),
		array(
			'name'        => 'pet-adoption-cta-content',
			'title'       => 'Pet Adoption CTA',
			'description' => 'Adoption title, fee, and apply button.',
			'icon'        => 'heart',
			'keywords'    => array( 'pet', 'adoption', 'cta', 'fee', 'apply' ),
			'innerBlocks' => array(
				array( 'core/heading', array( 'level' => 2 ), 'adoption_title' ),
				array( 'core/paragraph', array( 'className' => 'pet-adoption-fee' ), 'adoption_fee_formatted' ),
				array(
					'core/button',
					array(
						'text'       => 'Start Adoption',
						'linkTarget' => '_blank',
					),
					'adoption_form_url',
					'url',
				),
			),
			'attributes'  => array(
				'className' => 'pet-adoption-cta',
			),
		),
		// New compound variations
		array(
			'name'        => 'pet-full-profile',
			'title'       => 'Pet Full Profile',
			'description' => 'Name, photo, tagline, compatibility, and description.',
			'icon'        => 'id',
			'keywords'    => array( 'pet', 'profile', 'full', 'complete', 'bio' ),
			'innerBlocks' => array(
				array( 'core/heading', array( 'level' => 1 ), 'name' ),
				array( 'core/image', array( 'sizeSlug' => 'large' ), 'image', 'url' ),
				array( 'core/paragraph', array( 'className' => 'pet-profile-tagline' ), 'tagline' ),
				array( 'core/paragraph', array( 'className' => 'pet-profile-compat' ), 'compatibility' ),
				array( 'core/paragraph', array( 'className' => 'pet-profile-description' ), 'description' ),
			),
			'attributes'  => array(
				'className' => 'pet-full-profile',
			),
		),
		array(
			'name'        => 'pet-adoption-card',
			'title'       => 'Pet Adoption Card',
			'description' => 'Photo, name, breed, age, fee, and adopt button.',
			'icon'        => 'id-alt',
			'keywords'    => array( 'pet', 'adoption', 'card', 'listing' ),
			'innerBlocks' => array(
				array( 'core/image', array( 'sizeSlug' => 'medium_large' ), 'image', 'url' ),
				array( 'core/heading', array( 'level' => 3 ), 'name' ),
				array( 'core/paragraph', array( 'className' => 'pet-card-breed' ), 'breed' ),
				array( 'core/paragraph', array( 'className' => 'pet-card-age' ), 'age' ),
				array( 'core/paragraph', array( 'className' => 'pet-card-fee' ), 'adoption_fee_formatted' ),
				array(
					'core/button',
					array(
						'text'       => 'Start Adoption',
						'linkTarget' => '_blank',
					),
					'adoption_form_url',
					'url',
				),
			),
			'attributes'  => array(
				'className' => 'pet-adoption-card',
			),
		),
		array(
			'name'        => 'pet-compatibility-grid',
			'title'       => 'Pet Compatibility Grid',
			'description' => 'All compatibility fields in a grid layout.',
			'icon'        => 'screenoptions',
			'keywords'    => array( 'pet', 'compatibility', 'grid', 'good', 'with', 'dogs', 'cats', 'kids' ),
			'innerBlocks' => array(
				array( 'core/paragraph', array( 'className' => 'pet-compat-item' ), 'ok_with_dogs' ),
				array( 'core/paragraph', array( 'className' => 'pet-compat-item' ), 'ok_with_cats' ),
				array( 'core/paragraph', array( 'className' => 'pet-compat-item' ), 'ok_with_kids' ),
			),
			'attributes'  => array(
				'className' => 'pet-compatibility-grid',
				'layout'    => array(
					'type'     => 'flex',
					'flexWrap' => 'wrap',
				),
			),
		),
		array(
			'name'        => 'pet-medical-summary',
			'title'       => 'Pet Medical Summary',
			'description' => 'Shots, spayed/neutered, and special needs at a glance.',
			'icon'        => 'plus-alt',
			'keywords'    => array( 'pet', 'medical', 'health', 'shots', 'spayed', 'special', 'needs' ),
			'innerBlocks' => array(
				array( 'core/paragraph', array( 'className' => 'pet-medical-item' ), 'shots_current' ),
				array( 'core/paragraph', array( 'className' => 'pet-medical-item' ), 'spayed_neutered' ),
				array( 'core/paragraph', array( 'className' => 'pet-medical-item' ), 'special_needs' ),
			),
			'attributes'  => array(
				'className' => 'pet-medical-summary',
				'layout'    => array(
					'type'     => 'flex',
					'flexWrap' => 'wrap',
				),
			),
		),
		array(
			'name'        => 'pet-stats-banner',
			'title'       => 'Pet Stats Banner',
			'description' => 'Available count and species breakdown for landing pages.',
			'icon'        => 'chart-bar',
			'keywords'    => array( 'pet', 'stats', 'banner', 'count', 'landing' ),
			'source'      => 'petsync/adoption-stats',
			'innerBlocks' => array(
				array( 'core/heading', array( 'level' => 2 ), 'available_count' ),
				array( 'core/paragraph', array( 'className' => 'pet-stats-species' ), 'available_by_species' ),
			),
			'attributes'  => array(
				'className' => 'pet-stats-banner',
				'layout'    => array(
					'type'           => 'flex',
					'flexWrap'       => 'wrap',
					'justifyContent' => 'center',
				),
			),
		),
	);

	public function __construct() {
		add_action( 'init', array( $this, 'register_block_category' ) );
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue_variation_assets' ) );
	}

	/**
	 * Register custom block category for pet variations.
	 */
	public function register_block_category(): void {
		add_filter(
			'block_categories_all',
			function ( $categories ) {
				// Add at the beginning for visibility.
				array_unshift(
					$categories,
					array(
						'slug'  => 'petsync',
						'title' => __( 'Pet Blocks', 'shelter-pets' ),
						'icon'  => 'pets',
					)
				);
				return $categories;
			}
		);
	}

	/**
	 * Enqueue JS to register variations in the editor.
	 */
	public function enqueue_variation_assets(): void {
		// Build variation data for JS.
		$variations_data = array(
			'simple'           => $this->build_simple_variations(),
			'groups'           => $this->build_group_variations(),
			'source'           => 'petsync/pet-data',
			'placeholderImage' => PETSTABLISHED_SYNC_URL . 'assets/images/pet-placeholder.svg',
		);

		wp_enqueue_script(
			'petstablished-variations',
			PETSTABLISHED_SYNC_URL . 'assets/js/variations.js',
			array( 'wp-blocks', 'wp-dom-ready', 'wp-element', 'wp-i18n' ),
			PETSTABLISHED_SYNC_VERSION,
			true
		);

		wp_localize_script( 'petstablished-variations', 'petstablishedVariations', $variations_data );
	}

	/**
	 * Build simple variation data for JS registration.
	 */
	private function build_simple_variations(): array {
		$variations = array();

		// Pet data variations.
		$variations = array_merge(
			$variations,
			$this->build_variations_for_source( self::VARIATIONS, 'petsync/pet-data' )
		);

		// Adoption stats variations.
		$variations = array_merge(
			$variations,
			$this->build_variations_for_source( self::STATS_VARIATIONS, 'petsync/adoption-stats' )
		);

		return $variations;
	}

	/**
	 * Build variation data for a specific binding source.
	 */
	private function build_variations_for_source( array $source_variations, string $source ): array {
		$variations = array();

		foreach ( $source_variations as $block_type => $block_variations ) {
			foreach ( $block_variations as $var ) {
				$binding_attr = $var['binding_attr'] ?? ( 'core/image' === $block_type ? 'url' : 'content' );

				$bindings = array(
					$binding_attr => array(
						'source' => $source,
						'args'   => array_merge(
							array( 'key' => $var['binding_key'] ),
							$var['binding_args'] ?? array()
						),
					),
				);

				// Add alt binding for images.
				if ( isset( $var['alt_key'] ) ) {
					$bindings['alt'] = array(
						'source' => $source,
						'args'   => array( 'key' => $var['alt_key'] ),
					);
				}

				$variations[] = array(
					'block'       => $block_type,
					'name'        => 'petsync/' . $var['name'],
					'title'       => $var['title'],
					'description' => $var['description'],
					'icon'        => $var['icon'],
					'keywords'    => $var['keywords'],
					'category'    => 'petsync',
					'attributes'  => array_merge(
						$var['attributes'] ?? array(),
						array(
							'metadata' => array( 'bindings' => $bindings ),
						)
					),
					'isActive'    => array( 'metadata.bindings.' . $binding_attr . '.args.key' ),
				);
			}
		}

		return $variations;
	}

	/**
	 * Build group variation data for JS registration.
	 */
	private function build_group_variations(): array {
		$variations = array();

		foreach ( self::GROUP_VARIATIONS as $var ) {
			$inner_blocks = array();
			$source       = $var['source'] ?? 'petsync/pet-data';

			foreach ( $var['innerBlocks'] as $inner ) {
				$block_name   = $inner[0];
				$attrs        = $inner[1] ?? array();
				$binding_key  = $inner[2] ?? null;
				$binding_attr = $inner[3] ?? ( str_starts_with( $block_name, 'core/button' ) ? 'url' : ( str_starts_with( $block_name, 'core/image' ) ? 'url' : 'content' ) );

				if ( $binding_key ) {
					$attrs['metadata'] = array(
						'bindings' => array(
							$binding_attr => array(
								'source' => $source,
								'args'   => array( 'key' => $binding_key ),
							),
						),
					);

					// Add alt binding for image blocks.
					if ( str_starts_with( $block_name, 'core/image' ) ) {
						$attrs['metadata']['bindings']['alt'] = array(
							'source' => $source,
							'args'   => array( 'key' => 'name' ),
						);
					}
				}

				$inner_blocks[] = array( $block_name, $attrs );
			}

			$variations[] = array(
				'block'       => 'core/group',
				'name'        => 'petsync/' . $var['name'],
				'title'       => $var['title'],
				'description' => $var['description'],
				'icon'        => $var['icon'],
				'keywords'    => $var['keywords'],
				'category'    => 'petsync',
				'attributes'  => $var['attributes'] ?? array(),
				'innerBlocks' => $inner_blocks,
				'scope'       => array( 'inserter' ),
			);
		}

		return $variations;
	}

	/**
	 * Get all pet-data variation definitions (for templates).
	 */
	public static function get_variations(): array {
		return self::VARIATIONS;
	}

	/**
	 * Get adoption stats variation definitions.
	 */
	public static function get_stats_variations(): array {
		return self::STATS_VARIATIONS;
	}

	/**
	 * Get group variation definitions.
	 */
	public static function get_group_variations(): array {
		return self::GROUP_VARIATIONS;
	}
}
