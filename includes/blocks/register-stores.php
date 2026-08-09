<?php
/**
 * Interactivity Store Registration
 *
 * Centralizes all wp_interactivity_state() and wp_interactivity_config() calls.
 *
 * v4.2.0: Adds favorites-modal and compare-bar script modules.
 * Adds loadOnClientNavigation for cross-page router support.
 *
 * @package ShelterKit_Pets
 * @since 1.0.0
 */

declare( strict_types = 1 );

namespace Petsync\Blocks;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register all interactivity stores and config.
 */
function register_stores(): void {
	// === Shared Config (non-reactive, available to all stores) ===
	wp_interactivity_config(
		'petsync',
		[
			'restUrl'    => rest_url(),
			'nonce'      => wp_create_nonce( 'wp_rest' ),
			'maxCompare' => 4,
			'features'   => [
				'serverFiltering' => true,
				'searchHighlight' => true,
			],
			'i18n'       => get_i18n_strings(),
		]
	);

	$comparison     = \Petsync_Helpers::get_comparison();
	$has_comparison = ! empty( $comparison );

	// === Global Store State ===
	wp_interactivity_state(
		'petsync',
		[
			'favorites'            => \Petsync_Helpers::get_favorites(),
			'comparison'           => $comparison,
			'comparisonMax'        => 4,
			'pets'                 => array(),
			'isLoading'            => false,
			'notification'         => null,
			'noNotification'       => true,
			'_compareBarExpanded'  => $has_comparison,
			'_compareBarPrevCount' => count( $comparison ),
			'isCompareBarHidden'   => ! $has_comparison,
			'isCompareBarVisible'  => $has_comparison,
			// Button text for pet-actions (used by derived state getters).
			'_i18n'                => [
				'favorite'       => __( 'Favorite', 'shelterkit-pets' ),
				'unfavorite'     => __( 'Unfavorite', 'shelterkit-pets' ),
				'compare'        => __( 'Compare', 'shelterkit-pets' ),
				'comparing'      => __( 'Comparing', 'shelterkit-pets' ),
				'share'          => __( 'Share', 'shelterkit-pets' ),
				'copyLink'       => __( 'Copy link', 'shelterkit-pets' ),
				'copied'         => __( 'Copied!', 'shelterkit-pets' ),
				'copiedAnnounce' => __( 'Link copied to clipboard', 'shelterkit-pets' ),
			],
		]
	);

	// === Grid Store State ===
	wp_interactivity_state(
		'petsync/grid',
		[
			'isNavigating'          => false,
			'compatFiltersExpanded' => true,
		]
	);
}

/**
 * Register script modules.
 */
function register_script_modules(): void {
	// Utils module (shared dependency).
	wp_register_script_module(
		'petstablished-utils',
		PETSYNC_URL . 'assets/js/utils.js',
		[ '@wordpress/interactivity' ],
		PETSYNC_VERSION
	);

	// Global store module.
	wp_register_script_module(
		'petstablished-store',
		PETSYNC_URL . 'assets/js/store.js',
		[ '@wordpress/interactivity', 'petstablished-utils' ],
		PETSYNC_VERSION
	);
	wp_enqueue_script_module( 'petstablished-store' );

	// Grid store — interactivity-router is a dynamic dependency.
	wp_register_script_module(
		'petstablished-grid',
		PETSYNC_URL . 'assets/js/interactivity/grid.js',
		[
			'@wordpress/interactivity',
			'petstablished-store',
			'petstablished-utils',
			[
				'id'     => '@wordpress/interactivity-router',
				'import' => 'dynamic',
			],
		],
		PETSYNC_VERSION
	);

	// Compare bar store — uses the router dynamically for viewComparison.
	wp_register_script_module(
		'petstablished-compare-bar',
		PETSYNC_URL . 'assets/js/interactivity/compare-bar.js',
		[
			'@wordpress/interactivity',
			'petstablished-store',
			'petstablished-utils',
			[
				'id'     => '@wordpress/interactivity-router',
				'import' => 'dynamic',
			],
		],
		PETSYNC_VERSION
	);

	// Favorites modal store — standalone, no router dependency.
	wp_register_script_module(
		'petstablished-favorites-modal',
		PETSYNC_URL . 'assets/js/interactivity/favorites-modal.js',
		[
			'@wordpress/interactivity',
			'petstablished-store',
			'petstablished-utils',
		],
		PETSYNC_VERSION
	);

	/**
	 * Mark script modules as compatible with client-side navigation.
	 *
	 * @since 1.0.0 (WordPress 6.9)
	 *
	 * When the interactivity-router performs client-side navigation and
	 * encounters a page needing a module not present on the current page,
	 * modules marked with loadOnClientNavigation can be loaded on-the-fly.
	 *
	 * Critical for the attachTo pattern: navigating between pages that
	 * do/don't include the compare bar or favorites modal requires their
	 * script modules to load dynamically.
	 */
	if ( method_exists( wp_interactivity(), 'add_client_navigation_support_to_script_module' ) ) {
		wp_interactivity()->add_client_navigation_support_to_script_module( 'petstablished-store' );
		wp_interactivity()->add_client_navigation_support_to_script_module( 'petstablished-grid' );
		wp_interactivity()->add_client_navigation_support_to_script_module( 'petstablished-compare-bar' );
		wp_interactivity()->add_client_navigation_support_to_script_module( 'petstablished-favorites-modal' );
		wp_interactivity()->add_client_navigation_support_to_script_module( 'petstablished-utils' );
	}
}

/**
 * Get translatable UI strings.
 */
function get_i18n_strings(): array {
	return [
		'added'             => __( 'Added to favorites', 'shelterkit-pets' ),
		'removed'           => __( 'Removed from favorites', 'shelterkit-pets' ),
		'compareAdd'        => __( 'Added to comparison', 'shelterkit-pets' ),
		'compareRemove'     => __( 'Removed from comparison', 'shelterkit-pets' ),
		'compareFull'       => __( 'Comparison is full (max 4)', 'shelterkit-pets' ),
		'copied'            => __( 'Link copied!', 'shelterkit-pets' ),
		'loading'           => __( 'Loading...', 'shelterkit-pets' ),
		'error'             => __( 'Something went wrong', 'shelterkit-pets' ),
		'noResults'         => __( 'No pets match your filters.', 'shelterkit-pets' ),
		'searchPlaceholder' => __( 'Search by name or breed…', 'shelterkit-pets' ),
	];
}
