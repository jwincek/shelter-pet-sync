<?php
/**
 * Pet Listing Grid Block — Server-Side Render
 *
 * WordPress 6.9 Interactivity API patterns:
 * - data-wp-router-region for client-side filter/pagination navigation
 * - data-wp-context for instance-scoped mutable state
 * - Pet_Hydrator::hydrate_many() for N+1 elimination
 * - Ability-backed data fetching (petsync/filter-pets)
 * - Live filter counts with inline display and zero-count hiding
 *
 * v3.0.0 changes:
 * - Replaced per-pet Helper calls with Pet_Hydrator batch hydration
 * - Initial data fetched via filter-pets ability (same code path as client)
 * - Filter counts calculated server-side, passed to context for live updates
 * - Router region wraps the grid for server-driven filter navigation
 * - Search highlighting via data-wp-watch on the grid container
 *
 * @package Petstablished_Sync
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Petstablished\Core\Pet_Hydrator;

// Don't render listing grid when viewing comparison.
if ( isset( $_GET['compare'] ) && ! empty( $_GET['compare'] ) ) {
	return;
}

// Ensure the grid store is enqueued so the interactivity-router
// appears in the import map for dynamic imports.
wp_enqueue_script_module( 'petstablished-grid' );

// === Block Attributes ===
$columns               = $attributes['columns'] ?? 3;
$per_page              = $attributes['perPage'] ?? 100; // Still loads all for small shelters
$show_filters          = $attributes['showFilters'] ?? true;
$show_search           = $attributes['showSearch'] ?? true;
$show_results_count    = $attributes['showResultsCount'] ?? true;
$show_favorites_toggle = true; // Always show — client-side toggle, no attribute needed.
$badge_type            = $attributes['badgeType'] ?? 'animal';

// Filter visibility settings.
$filter_animal = $attributes['filterAnimal'] ?? true;
$filter_breed  = $attributes['filterBreed'] ?? true;
$filter_age    = $attributes['filterAge'] ?? true;
$filter_sex    = $attributes['filterSex'] ?? true;
$filter_size   = $attributes['filterSize'] ?? true;

// Compatibility filter settings.
$show_compat_filters    = $attributes['showCompatibilityFilters'] ?? true;
$filter_good_with_dogs  = $attributes['filterGoodWithDogs'] ?? true;
$filter_good_with_cats  = $attributes['filterGoodWithCats'] ?? true;
$filter_good_with_kids  = $attributes['filterGoodWithKids'] ?? true;
$filter_shots_current   = $attributes['filterShotsCurrent'] ?? true;
$filter_spayed_neutered = $attributes['filterSpayedNeutered'] ?? true;
$filter_housebroken     = $attributes['filterHousebroken'] ?? true;
$filter_special_needs   = $attributes['filterSpecialNeeds'] ?? false;
$compat_style           = $attributes['compatibilityStyle'] ?? 'chips';

// === URL Parameters (for SSR + bookmarkable URLs) ===
$search_query = sanitize_text_field( wp_unslash( $_GET['pet_search'] ?? '' ) );
$paged        = max( 1, absint( $_GET['paged'] ?? 1 ) );
$current_sort = sanitize_text_field( wp_unslash( $_GET['sort'] ?? '' ) );

$url_filters = array(
	'animal' => sanitize_text_field( wp_unslash( $_GET['filter_animal'] ?? '' ) ),
	'breed'  => sanitize_text_field( wp_unslash( $_GET['filter_breed'] ?? '' ) ),
	'age'    => sanitize_text_field( wp_unslash( $_GET['filter_age'] ?? '' ) ),
	'sex'    => sanitize_text_field( wp_unslash( $_GET['filter_sex'] ?? '' ) ),
	'size'   => sanitize_text_field( wp_unslash( $_GET['filter_size'] ?? '' ) ),
);

$url_compat = array(
	'goodWithDogs'   => ! empty( $_GET['compat_goodWithDogs'] ),
	'goodWithCats'   => ! empty( $_GET['compat_goodWithCats'] ),
	'goodWithKids'   => ! empty( $_GET['compat_goodWithKids'] ),
	'shotsCurrent'   => ! empty( $_GET['compat_shotsCurrent'] ),
	'spayedNeutered' => ! empty( $_GET['compat_spayedNeutered'] ),
	'housebroken'    => ! empty( $_GET['compat_housebroken'] ),
	'specialNeeds'   => ! empty( $_GET['compat_specialNeeds'] ),
);

// === Fetch Data via Ability ===
$ability = function_exists( 'wp_get_ability' )
	? wp_get_ability( 'petsync/filter-pets' )
	: null;

$pets          = array();
$total         = 0;
$total_pages   = 0;
$filter_counts = array();

if ( $ability ) {
	$input = array(
		'status'   => 'available',
		'per_page' => $per_page,
		'page'     => $paged,
	);

	// Add taxonomy filters.
	foreach ( $url_filters as $key => $value ) {
		if ( $value ) {
			$input[ $key ] = $value;
		}
	}

	// Add compatibility filters.
	foreach ( $url_compat as $key => $value ) {
		if ( $value ) {
			$input[ $key ] = true;
		}
	}

	// Add search.
	if ( $search_query ) {
		$input['search'] = $search_query;
	}

	// Add sort.
	if ( $current_sort ) {
		$sort_parts       = explode( '-', $current_sort, 2 );
		$input['orderby'] = $sort_parts[0] === 'name' ? 'title' : $sort_parts[0];
		$input['order']   = strtoupper( $sort_parts[1] ?? 'asc' );
	}

	$result = $ability->execute( $input );

	if ( ! is_wp_error( $result ) ) {
		$pets          = $result['pets'] ?? array();
		$total         = $result['total'] ?? 0;
		$total_pages   = $result['totalPages'] ?? 0;
		$filter_counts = $result['counts'] ?? array();
	}
} else {
	// Fallback: direct query with Pet_Hydrator (no live counts).
	$fallback_orderby = 'date';
	$fallback_order   = 'DESC';
	if ( $current_sort ) {
		$sort_parts       = explode( '-', $current_sort, 2 );
		$fallback_orderby = $sort_parts[0] === 'name' ? 'title' : ( $sort_parts[0] === 'random' ? 'rand' : $sort_parts[0] );
		$fallback_order   = strtoupper( $sort_parts[1] ?? 'asc' );
	}

	$args = array(
		'post_type'      => 'vcps_pet',
		'post_status'    => 'publish',
		'posts_per_page' => $per_page,
		'paged'          => $paged,
		'orderby'        => $fallback_orderby,
		'order'          => $fallback_order,
		'tax_query'      => array(
			array(
				'taxonomy' => 'pet_status',
				'field'    => 'slug',
				'terms'    => array( 'available' ),
			),
		),
	);

	$query       = new WP_Query( $args );
	$pets        = Pet_Hydrator::hydrate_many( $query->posts, 'grid' );
	$total       = $query->found_posts;
	$total_pages = $query->max_num_pages;
	wp_reset_postdata();
}

// === Build petIds list for client (comparison/favorites reactivity) ===
// Full pet data stays in the server-rendered card HTML. The client only
// needs IDs and names for announcement strings and cache priming.
$pet_ids = array();
foreach ( $pets as $pet ) {
	$pet_ids[] = array(
		'id'                => $pet['id'],
		'name'              => $pet['name'],
		'url'               => $pet['url'] ?? '',
		'image'             => $pet['image'] ?? '',
		'breed'             => $pet['breed'] ?? '',
		'age'               => $pet['age'] ?? '',
		'sex'               => $pet['sex'] ?? '',
		'size'              => $pet['size'] ?? '',
		'special_needs'     => $pet['special_needs'] ?? '',
		'is_new'            => $pet['is_new'] ?? false,
		'is_bonded_pair'    => $pet['is_bonded_pair'] ?? false,
		'bonded_pair_names' => $pet['bonded_pair_names'] ?? array(),
	);
}

// === Persisted State ===
$favorites  = Petstablished_Helpers::get_favorites();
$comparison = Petstablished_Helpers::get_comparison();

// === Router Region ID ===
$region_id = 'pet-grid';

// === Archive URL (base for filter navigation) ===
$archive_url = get_post_type_archive_link( 'vcps_pet' ) ?: home_url( '/pets/' );

// === Sort options ===
$sort_options = array(
	''          => __( 'Newest First', 'shelter-pets' ),
	'name-asc'  => __( 'Name A–Z', 'shelter-pets' ),
	'name-desc' => __( 'Name Z–A', 'shelter-pets' ),
	'age-asc'   => __( 'Youngest First', 'shelter-pets' ),
	'age-desc'  => __( 'Oldest First', 'shelter-pets' ),
	'random'    => __( 'Random', 'shelter-pets' ),
);

// === Build Context ===
$context = array(
	// Router.
	'routerRegionId' => $region_id,
	'archiveUrl'     => $archive_url,

	// Listing data — minimal payload for client reactivity.
	'petIds'         => $pet_ids,
	'total'          => $total,
	'totalPages'     => $total_pages,
	'page'           => $paged,

	// Filter state.
	'searchQuery'    => $search_query,
	'filters'        => $url_filters,
	'compatFilters'  => $url_compat,
	'filterCounts'   => $filter_counts,
	'sort'           => $current_sort,
	// Config.
	'badgeType'      => $badge_type,
	'perPage'        => $per_page,
);

$wrapper_attributes = get_block_wrapper_attributes(
	array(
		'class'                 => 'pet-listing-grid',
		'data-wp-interactive'   => 'petsync/grid',
		'data-wp-router-region' => $region_id,
		'data-wp-key'           => $region_id,
		'data-pets-archive-url' => $archive_url,
		'style'                 => '--pet-grid-columns: ' . intval( $columns ),
	)
);

// Context is on an inner element so it gets replaced when the
// interactivity-router swaps the region's innerHTML on navigation.
// wp_interactivity_data_wp_context() emits a properly escaped
// data-wp-context attribute (JSON_HEX_* flags), preventing a single
// quote in request-derived state (e.g. ?pet_search=') from breaking
// out of the attribute. Do NOT hand-roll this with wp_json_encode().
$inner_attributes = sprintf(
	'%s data-wp-init="callbacks.init"',
	wp_interactivity_data_wp_context( $context )
);

// === Compatibility Filters Config ===
$compat_icons = array(
	'goodWithDogs'   => Petstablished_Icons::get(
		'dog',
		array(
			'width'  => 16,
			'height' => 16,
		)
	),
	'goodWithCats'   => Petstablished_Icons::get(
		'cat',
		array(
			'width'  => 16,
			'height' => 16,
		)
	),
	'goodWithKids'   => Petstablished_Icons::get(
		'child',
		array(
			'width'  => 16,
			'height' => 16,
		)
	),
	'shotsCurrent'   => Petstablished_Icons::get(
		'shield-check',
		array(
			'width'  => 16,
			'height' => 16,
		)
	),
	'spayedNeutered' => Petstablished_Icons::get(
		'check',
		array(
			'width'  => 16,
			'height' => 16,
		)
	),
	'housebroken'    => Petstablished_Icons::get(
		'house',
		array(
			'width'  => 16,
			'height' => 16,
		)
	),
	'specialNeeds'   => Petstablished_Icons::get(
		'heart-special',
		array(
			'width'  => 16,
			'height' => 16,
		)
	),
);

$compat_filters_config = array(
	'goodWithDogs'   => array(
		'label' => __( 'Good with dogs', 'shelter-pets' ),
		'icon'  => $compat_icons['goodWithDogs'],
		'show'  => $filter_good_with_dogs,
		'key'   => 'goodWithDogs',
	),
	'goodWithCats'   => array(
		'label' => __( 'Good with cats', 'shelter-pets' ),
		'icon'  => $compat_icons['goodWithCats'],
		'show'  => $filter_good_with_cats,
		'key'   => 'goodWithCats',
	),
	'goodWithKids'   => array(
		'label' => __( 'Good with kids', 'shelter-pets' ),
		'icon'  => $compat_icons['goodWithKids'],
		'show'  => $filter_good_with_kids,
		'key'   => 'goodWithKids',
	),
	'shotsCurrent'   => array(
		'label' => __( 'Shots current', 'shelter-pets' ),
		'icon'  => $compat_icons['shotsCurrent'],
		'show'  => $filter_shots_current,
		'key'   => 'shotsCurrent',
	),
	'spayedNeutered' => array(
		'label' => __( 'Spayed/Neutered', 'shelter-pets' ),
		'icon'  => $compat_icons['spayedNeutered'],
		'show'  => $filter_spayed_neutered,
		'key'   => 'spayedNeutered',
	),
	'housebroken'    => array(
		'label' => __( 'Housebroken', 'shelter-pets' ),
		'icon'  => $compat_icons['housebroken'],
		'show'  => $filter_housebroken,
		'key'   => 'housebroken',
	),
	'specialNeeds'   => array(
		'label' => __( 'Special needs', 'shelter-pets' ),
		'icon'  => $compat_icons['specialNeeds'],
		'show'  => $filter_special_needs,
		'key'   => 'specialNeeds',
	),
);

// Calculate compat filter counts from server data.
// Map compat keys to hydrated entity field names for the fallback counter.
$compat_field_map = array(
	'goodWithDogs'   => 'ok_with_dogs',
	'goodWithCats'   => 'ok_with_cats',
	'goodWithKids'   => 'ok_with_kids',
	'shotsCurrent'   => 'shots_current',
	'spayedNeutered' => 'spayed_neutered',
	'housebroken'    => 'housebroken',
	'specialNeeds'   => 'special_needs',
);
$truthy_lc        = array( 'yes', '1', 'true' );

$compat_counts = array();
foreach ( array_keys( $compat_filters_config ) as $key ) {
	$count_data = $filter_counts[ $key ] ?? null;
	if ( is_array( $count_data ) && isset( $count_data[0]['count'] ) ) {
		// Structured format from ability: [ { value, label, count } ]
		$compat_counts[ $key ] = $count_data[0]['count'];
	} else {
		// Fallback: count from hydrated pet data.
		$field                 = $compat_field_map[ $key ] ?? $key;
		$compat_counts[ $key ] = count(
			array_filter(
				$pets,
				function ( $pet ) use ( $field, $truthy_lc ) {
					return in_array( strtolower( (string) ( $pet[ $field ] ?? '' ) ), $truthy_lc, true );
				}
			)
		);
	}
}

$has_compat_filters = $show_compat_filters && count( array_filter( array_column( $compat_filters_config, 'show' ) ) ) > 0;

// === Filter Dropdown Config ===
$filter_config = array(
	'animal' => array(
		'label' => __( 'Animal', 'shelter-pets' ),
		'all'   => __( 'All Animals', 'shelter-pets' ),
		'show'  => $filter_animal,
	),
	'breed'  => array(
		'label' => __( 'Breed', 'shelter-pets' ),
		'all'   => __( 'All Breeds', 'shelter-pets' ),
		'show'  => $filter_breed,
	),
	'age'    => array(
		'label' => __( 'Age', 'shelter-pets' ),
		'all'   => __( 'Any Age', 'shelter-pets' ),
		'show'  => $filter_age,
	),
	'sex'    => array(
		'label' => __( 'Sex', 'shelter-pets' ),
		'all'   => __( 'Any Sex', 'shelter-pets' ),
		'show'  => $filter_sex,
	),
	'size'   => array(
		'label' => __( 'Size', 'shelter-pets' ),
		'all'   => __( 'Any Size', 'shelter-pets' ),
		'show'  => $filter_size,
	),
);
?>

<div <?php echo $wrapper_attributes; /* phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_block_wrapper_attributes() returns escaped HTML. */ ?>>
<div class="pet-listing-grid__inner" <?php echo $inner_attributes; /* phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from wp_interactivity_data_wp_context() plus static directives. */ ?>>
	<?php
	if ( $show_filters || $show_search || $show_favorites_toggle ) :
		// Count active filters for the toggle badge.
		$active_filter_count = count( array_filter( $url_filters ) )
			+ count( array_filter( $url_compat ) );
		$has_active_filters  = $active_filter_count > 0 || $search_query || $current_sort;
		?>
		<div class="pet-listing-grid__toolbar">
			<!-- Always-visible row: search, filter toggle, sort -->
			<div class="pet-listing-grid__toolbar-row">
				<?php if ( $show_search ) : ?>
					<div class="pet-listing-grid__search">
						<label for="pet-search" class="screen-reader-text">
							<?php esc_html_e( 'Search pets', 'shelter-pets' ); ?>
						</label>
						<div class="pet-listing-grid__search-wrapper">
							<input
								type="search"
								id="pet-search"
								name="pet_search"
								class="pet-listing-grid__search-input"
								placeholder="<?php esc_attr_e( 'Search by name or breed...', 'shelter-pets' ); ?>"
								value="<?php echo esc_attr( $search_query ); ?>"
								data-wp-on--input="actions.handleSearchInput"
								data-wp-on--keydown="actions.handleSearchKeydown"
								data-wp-bind--value="context.searchQuery"
								autocomplete="off"
							>
							<button
								type="button"
								class="pet-listing-grid__search-clear"
								data-wp-on--click="actions.clearSearch"
								data-wp-bind--hidden="!state.hasSearchQuery"
								<?php echo $search_query ? '' : 'hidden'; ?>
								aria-label="<?php esc_attr_e( 'Clear search', 'shelter-pets' ); ?>"
							>
								<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
							</button>
							<button
								type="button"
								class="pet-listing-grid__search-submit"
								data-wp-on--click="actions.submitSearch"
								aria-label="<?php esc_attr_e( 'Search', 'shelter-pets' ); ?>"
							>
								<?php
								Petstablished_Icons::render(
									'search',
									array(
										'width'  => 16,
										'height' => 16,
									)
								);
								?>
							</button>
						</div>
					</div>
				<?php endif; ?>

				<?php if ( $show_filters ) : ?>
					<button
						type="button"
						class="pet-listing-grid__filter-toggle"
						data-wp-on--click="actions.toggleFilterDrawer"
						data-wp-class--is-open="state.filterDrawerOpen"
						data-wp-bind--aria-expanded="state.filterDrawerOpen"
						aria-expanded="<?php echo $has_active_filters ? 'true' : 'false'; ?>"
						aria-controls="pet-filter-drawer"
					>
						<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M22 3H2l8 9.46V19l4 2v-8.54L22 3z"/></svg>
						<span><?php esc_html_e( 'Filters', 'shelter-pets' ); ?></span>
						<span
							class="pet-listing-grid__filter-toggle-count"
							data-wp-text="state.activeFilterCount"
							data-wp-bind--hidden="!state.hasActiveFilterCount"
							<?php echo $active_filter_count > 0 ? '' : 'hidden'; ?>
						><?php echo $active_filter_count > 0 ? esc_html( $active_filter_count ) : ''; ?></span>
					</button>
				<?php endif; ?>

				<!-- Sort control -->
				<div class="pet-listing-grid__sort">
					<label class="screen-reader-text" for="pet-sort">
						<?php esc_html_e( 'Sort by', 'shelter-pets' ); ?>
					</label>
					<select
						id="pet-sort"
						class="pet-listing-grid__sort-select"
						data-wp-on--change="actions.updateSort"
					>
						<?php foreach ( $sort_options as $value => $label ) : ?>
							<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $current_sort, $value ); ?>>
								<?php echo esc_html( $label ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>

			</div>

			<?php if ( $show_filters ) : ?>
				<!-- Filter drawer — collapsible on mobile, always visible on desktop -->
				<div
					class="pet-listing-grid__filter-drawer<?php echo $has_active_filters ? ' is-open' : ''; ?>"
					id="pet-filter-drawer"
					data-wp-class--is-open="state.filterDrawerOpen"
				>
				<!-- Single inner wrapper ensures overflow:hidden clips everything
					(taxonomy selects + compat details) when the drawer collapses. -->
				<div class="pet-listing-grid__filter-drawer-inner">
					<div class="pet-listing-grid__filters" role="group" aria-label="<?php esc_attr_e( 'Filter pets', 'shelter-pets' ); ?>">
						<?php
						foreach ( $filter_config as $key => $config ) :
							if ( ! $config['show'] ) {
								continue;
							}
							$options = $filter_counts[ $key ] ?? array();
							if ( empty( $options ) ) {
								continue;
							}
							$current_value = $url_filters[ $key ] ?? '';
							?>
							<div class="pet-listing-grid__filter-group">
								<label class="pet-listing-grid__filter-label screen-reader-text" for="filter-<?php echo esc_attr( $key ); ?>">
									<?php echo esc_html( $config['label'] ); ?>
								</label>
								<select
									id="filter-<?php echo esc_attr( $key ); ?>"
									class="pet-listing-grid__filter-select"
									data-wp-on--change="actions.updateFilter"
									data-filter-key="<?php echo esc_attr( $key ); ?>"
								>
									<option value=""><?php echo esc_html( $config['all'] ); ?> (<?php echo esc_html( $total ); ?>)</option>
									<?php foreach ( $options as $opt ) : ?>
										<option
											value="<?php echo esc_attr( $opt['value'] ); ?>"
											<?php selected( $current_value, $opt['value'] ); ?>
										>
											<?php echo esc_html( $opt['label'] ); ?> (<?php echo esc_html( $opt['count'] ); ?>)
										</option>
									<?php endforeach; ?>
								</select>
							</div>
						<?php endforeach; ?>

						<button
							type="button"
							class="pet-listing-grid__filter-reset"
							data-wp-on--click="actions.resetFilters"
							data-wp-bind--hidden="!state.hasActiveFilters"
						>
							<?php esc_html_e( 'Clear All', 'shelter-pets' ); ?>
						</button>
					</div>

					<?php if ( $has_compat_filters ) : ?>
						<?php
						$active_compat_count  = count( array_filter( $url_compat ) );
						$visible_compat_count = count( array_filter( array_column( $compat_filters_config, 'show' ) ) );
						// Always open in HTML — visible on desktop immediately.
						// On mobile, the grid's init callback closes it unless
						// compat filters are active.
						?>
						<details class="pet-listing-grid__compat-details" open data-has-active="<?php echo $active_compat_count > 0 ? '1' : '0'; ?>">
							<summary class="pet-listing-grid__compat-summary">
								<?php
								printf(
									/* translators: %d: number of additional filter options */
									esc_html__( 'More Filters (%d)', 'shelter-pets' ),
									(int) $visible_compat_count
								);
								?>
								<?php if ( $active_compat_count > 0 ) : ?>
									<span class="pet-listing-grid__compat-summary-count"><?php echo esc_html( $active_compat_count ); ?></span>
								<?php endif; ?>
								<?php
								Petstablished_Icons::render(
									'chevron-down',
									array(
										'width'  => 16,
										'height' => 16,
										'class'  => 'pet-listing-grid__compat-summary-icon',
									)
								);
								?>
							</summary>
							<div class="pet-listing-grid__compat-filters pet-listing-grid__compat-filters--<?php echo esc_attr( $compat_style ); ?>" role="group" aria-label="<?php esc_attr_e( 'Compatibility filters', 'shelter-pets' ); ?>">
								<?php foreach ( $compat_filters_config as $key => $filter ) : ?>
									<?php
									if ( ! $filter['show'] ) {
										continue;
									}
									$count = $compat_counts[ $key ] ?? 0;
									if ( $count === 0 ) {
										continue;
									}
									?>
									<?php if ( $compat_style === 'chips' ) : ?>
										<button
											type="button"
											class="pet-listing-grid__compat-chip"
											data-wp-on--click="actions.toggleCompatFilter"
											data-wp-class--is-active="context.compatFilters.<?php echo esc_attr( $key ); ?>"
											data-compat-key="<?php echo esc_attr( $key ); ?>"
											aria-pressed="<?php echo $url_compat[ $key ] ? 'true' : 'false'; ?>"
											data-wp-bind--aria-pressed="context.compatFilters.<?php echo esc_attr( $key ); ?>"
										>
											<span class="pet-listing-grid__compat-chip-icon" aria-hidden="true"><?php echo $filter['icon']; /* phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static plugin SVG from Petstablished_Icons. */ ?></span>
											<span class="pet-listing-grid__compat-chip-label"><?php echo esc_html( $filter['label'] ); ?></span>
											<span class="pet-listing-grid__compat-chip-count">(<?php echo esc_html( $count ); ?>)</span>
										</button>
									<?php else : ?>
										<label class="pet-listing-grid__compat-checkbox">
											<input
												type="checkbox"
												data-wp-on--change="actions.toggleCompatFilter"
												data-wp-bind--checked="context.compatFilters.<?php echo esc_attr( $key ); ?>"
												data-compat-key="<?php echo esc_attr( $key ); ?>"
												<?php checked( $url_compat[ $key ] ); ?>
											>
											<span class="pet-listing-grid__compat-checkbox-icon" aria-hidden="true"><?php echo $filter['icon']; /* phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static plugin SVG from Petstablished_Icons. */ ?></span>
											<span class="pet-listing-grid__compat-checkbox-label"><?php echo esc_html( $filter['label'] ); ?></span>
											<span class="pet-listing-grid__compat-checkbox-count">(<?php echo esc_html( $count ); ?>)</span>
										</label>
									<?php endif; ?>
								<?php endforeach; ?>
							</div>
						</details>
					<?php endif; ?>
				</div><!-- .pet-listing-grid__filter-drawer-inner -->
				</div>
			<?php endif; ?>

		</div>
	<?php endif; ?>

	<!-- Loading overlay — shown during router navigation -->
	<div
		class="pet-listing-grid__loading-overlay"
		data-wp-class--is-loading="state.isNavigating"
		role="status"
		aria-live="polite"
	>
		<span class="pet-listing-grid__loading-text" hidden data-wp-bind--hidden="!state.isNavigating">
			<?php esc_html_e( 'Updating results…', 'shelter-pets' ); ?>
		</span>
	</div>

	<div class="pet-listing-grid__results-bar">
		<!-- Favorites toggle — view mode, not a query filter -->
		<div data-wp-interactive="petsync/grid">
			<button
				type="button"
				class="pet-listing-grid__favorites-toggle"
				data-wp-on--click="actions.toggleFavoritesFilter"
				data-wp-class--is-active="state.showFavoritesOnly"
				data-wp-text="state.favoritesFilterText"
				aria-pressed="false"
				data-wp-bind--aria-pressed="state.showFavoritesOnly"
			>
				<?php esc_html_e( "\u{2665} Favorites", 'shelter-pets' ); ?>
			</button>
		</div>

		<?php if ( $show_results_count ) : ?>
			<div class="pet-listing-grid__results-info" role="status" aria-live="polite">
				<?php
				if ( $search_query ) {
					printf(
						/* translators: 1: count, 2: search query */
						esc_html( _n( '%1$d result for &ldquo;%2$s&rdquo;', '%1$d results for &ldquo;%2$s&rdquo;', $total, 'shelter-pets' ) ),
						(int) $total,
						esc_html( $search_query )
					);
				} else {
					printf(
						esc_html( /* translators: %d: number of pets */ _n( '%d pet', '%d pets', $total, 'shelter-pets' ) ),
						(int) $total
					);
				}
				?>
			</div>
		<?php endif; ?>
	</div>

	<?php
	// === Active Filter Chips ===
	// Show a scannable summary of active filters with individual removal.
	$active_chips = array();

	// Taxonomy filters — display the human-readable value (slug → label).
	$filter_labels = array(
		'animal' => __( 'Animal', 'shelter-pets' ),
		'breed'  => __( 'Breed', 'shelter-pets' ),
		'age'    => __( 'Age', 'shelter-pets' ),
		'sex'    => __( 'Sex', 'shelter-pets' ),
		'size'   => __( 'Size', 'shelter-pets' ),
	);
	foreach ( $url_filters as $key => $value ) {
		if ( $value ) {
			$active_chips[] = array(
				'type'  => 'filter',
				'key'   => $key,
				'label' => ( $filter_labels[ $key ] ?? ucfirst( $key ) ) . ': ' . ucfirst( str_replace( '-', ' ', $value ) ),
			);
		}
	}

	// Compatibility filters.
	$compat_labels = array(
		'goodWithDogs'   => __( 'Good with dogs', 'shelter-pets' ),
		'goodWithCats'   => __( 'Good with cats', 'shelter-pets' ),
		'goodWithKids'   => __( 'Good with kids', 'shelter-pets' ),
		'shotsCurrent'   => __( 'Shots current', 'shelter-pets' ),
		'spayedNeutered' => __( 'Spayed/Neutered', 'shelter-pets' ),
		'housebroken'    => __( 'Housebroken', 'shelter-pets' ),
		'specialNeeds'   => __( 'Special needs', 'shelter-pets' ),
	);
	foreach ( $url_compat as $key => $active ) {
		if ( $active ) {
			$active_chips[] = array(
				'type'  => 'compat',
				'key'   => $key,
				'label' => $compat_labels[ $key ] ?? $key,
			);
		}
	}

	// Search query chip.
	if ( $search_query ) {
		$active_chips[] = array(
			'type'  => 'search',
			'key'   => 'search',
			'label' => sprintf( /* translators: %s: search query */ __( 'Search: "%s"', 'shelter-pets' ), $search_query ),
		);
	}

	if ( ! empty( $active_chips ) ) :
		?>
		<div class="pet-listing-grid__active-filters" role="list" aria-label="<?php esc_attr_e( 'Active filters', 'shelter-pets' ); ?>">
			<?php foreach ( $active_chips as $chip ) : ?>
				<button
					type="button"
					class="pet-listing-grid__filter-chip"
					data-filter-type="<?php echo esc_attr( $chip['type'] ); ?>"
					data-filter-key="<?php echo esc_attr( $chip['key'] ); ?>"
					data-wp-on--click="actions.removeFilter"
					role="listitem"
					aria-label="<?php echo esc_attr( sprintf( /* translators: %s: active filter label */ __( 'Remove filter: %s', 'shelter-pets' ), $chip['label'] ) ); ?>"
				>
					<span class="pet-listing-grid__filter-chip-label"><?php echo esc_html( $chip['label'] ); ?></span>
					<span class="pet-listing-grid__filter-chip-remove" aria-hidden="true">&times;</span>
				</button>
			<?php endforeach; ?>

			<?php if ( count( $active_chips ) > 1 ) : ?>
				<button
					type="button"
					class="pet-listing-grid__filter-chip pet-listing-grid__filter-chip--clear"
					data-wp-on--click="actions.resetFilters"
					aria-label="<?php esc_attr_e( 'Clear all filters', 'shelter-pets' ); ?>"
				>
					<?php esc_html_e( 'Clear all', 'shelter-pets' ); ?>
				</button>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<ul class="pet-listing-grid__grid" role="list">
		<?php
		foreach ( $pets as $index => $pet ) :
			$pet_context = array(
				'petId'   => $pet['id'],
				'petName' => $pet['name'],
			);

			// Determine badge content.
			$badge_text  = '';
			$badge_class = '';
			switch ( $badge_type ) {
				case 'animal':
					$badge_text  = $pet['animal'] ?? '';
					$badge_class = 'badge--' . sanitize_title( $pet['animalSlug'] ?? $pet['animal'] ?? '' );
					break;
				case 'age':
					$badge_text  = $pet['age'] ?? '';
					$badge_class = 'badge--age';
					break;
				case 'new':
					if ( $pet['is_new'] ?? false ) {
						$badge_text  = __( 'New!', 'shelter-pets' );
						$badge_class = 'badge--new';
					}
					break;
			}
			$is_favorited = in_array( $pet['id'], $favorites, true );
			?>
			<li
				class="pet-listing-grid__item<?php echo $is_favorited ? ' is-favorited' : ''; ?>"
				data-wp-key="pet-<?php echo esc_attr( $pet['id'] ); ?>"
				<?php echo wp_interactivity_data_wp_context( $pet_context ); /* phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_interactivity_data_wp_context() returns an escaped attribute. */ ?>
				data-wp-bind--hidden="!state.isPetVisible"
				data-wp-class--is-favorited="state.isFavorited"
				data-wp-class--is-compared="state.isInComparison"
			>
				<article class="pet-listing-grid__card">
					<a
						href="<?php echo esc_url( $pet['url'] ); ?>"
						class="pet-listing-grid__card-link"
					>
						<?php if ( $pet['image'] ) : ?>
							<img
								src="<?php echo esc_url( $pet['image'] ); ?>"
								alt="<?php echo esc_attr( $pet['name'] ); ?>"
								class="pet-listing-grid__card-image"
								loading="<?php echo $index < 6 ? 'eager' : 'lazy'; ?>"
							>
						<?php else : ?>
							<div class="pet-listing-grid__card-placeholder">
								<?php
								Petstablished_Icons::render(
									'paw',
									array(
										'width'        => 48,
										'height'       => 48,
										'stroke-width' => 1,
									)
								);
								?>
							</div>
						<?php endif; ?>

						<?php if ( $badge_text ) : ?>
							<span class="pet-listing-grid__card-badge <?php echo esc_attr( $badge_class ); ?>">
								<?php echo esc_html( $badge_text ); ?>
							</span>
						<?php endif; ?>

						<span
							class="pet-listing-grid__card-fav-indicator"
							data-wp-bind--hidden="!state.isFavorited"
							aria-hidden="true"
							<?php echo $is_favorited ? '' : 'hidden'; ?>
						>♥</span>
					</a>

					<div class="pet-listing-grid__card-content">
						<h3 class="pet-listing-grid__card-name">
							<a
								href="<?php echo esc_url( $pet['url'] ); ?>"
								class="pet-listing-grid__card-name-link pet-card__name"
							><?php echo esc_html( $pet['name'] ); ?></a>
						</h3>

						<p class="pet-listing-grid__card-meta pet-card__meta">
							<?php
							$meta_parts = array_filter( array( $pet['breed'] ?? '', $pet['age'] ?? '', $pet['sex'] ?? '' ) );
							echo esc_html( implode( ' · ', $meta_parts ) );
							?>
						</p>

						<?php
						$has_badges = ( $pet['is_new'] ?? false )
							|| ( isset( $pet['special_needs'] ) && strtolower( (string) $pet['special_needs'] ) === 'yes' )
							|| ( $pet['is_bonded_pair'] ?? false );
						if ( $has_badges ) :
							?>
							<div class="pet-listing-grid__card-badges">
								<?php if ( $pet['is_new'] ?? false ) : ?>
									<span class="pet-listing-grid__badge pet-listing-grid__badge--new"><?php esc_html_e( 'New', 'shelter-pets' ); ?></span>
								<?php endif; ?>

								<?php if ( isset( $pet['special_needs'] ) && strtolower( (string) $pet['special_needs'] ) === 'yes' ) : ?>
									<span class="pet-listing-grid__badge pet-listing-grid__badge--special"><?php esc_html_e( 'Special Needs', 'shelter-pets' ); ?></span>
								<?php endif; ?>

								<?php if ( $pet['is_bonded_pair'] ?? false ) : ?>
									<span class="pet-listing-grid__badge-popover-anchor">
										<button
											type="button"
											class="pet-listing-grid__badge pet-listing-grid__badge--bonded"
											data-wp-on--click="actions.toggleBondedInfo"
											data-wp-class--is-expanded="state.isBondedExpanded"
											aria-expanded="false"
											data-wp-bind--aria-expanded="state.isBondedExpanded"
										>
											<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
												<path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
												<path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z" transform="translate(3, 0)" opacity="0.5"/>
											</svg>
											<?php esc_html_e( 'Bonded Pair', 'shelter-pets' ); ?>
										</button>
										<div
											class="pet-listing-grid__bonded-popover"
											data-wp-bind--hidden="!state.isBondedExpanded"
											role="tooltip"
											hidden
										>
											<div class="pet-listing-grid__bonded-popover-arrow"></div>
											<p class="pet-listing-grid__bonded-popover-label"><?php esc_html_e( 'Must adopt together with:', 'shelter-pets' ); ?></p>
											<ul class="pet-listing-grid__bonded-popover-list">
												<?php
												$partners = $pet['bonded_pair_names'] ?? array();
												foreach ( $partners as $partner ) :
													?>
													<li>
														<?php if ( ! empty( $partner['url'] ) ) : ?>
															<a href="<?php echo esc_url( $partner['url'] ); ?>" class="pet-listing-grid__bonded-popover-link">
																<?php echo esc_html( $partner['name'] ); ?>
															</a>
														<?php else : ?>
															<span><?php echo esc_html( $partner['name'] ); ?></span>
														<?php endif; ?>
													</li>
												<?php endforeach; ?>
											</ul>
										</div>
									</span>
								<?php endif; ?>
							</div>
						<?php endif; ?>

						<div class="pet-listing-grid__card-actions">
							<button
								type="button"
								class="pet-listing-grid__card-favorite"
								data-wp-on--click="actions.toggleFavorite"
								data-wp-bind--aria-pressed="state.isFavorited"
								aria-pressed="<?php echo $is_favorited ? 'true' : 'false'; ?>"
								aria-label="<?php esc_attr_e( 'Toggle favorite', 'shelter-pets' ); ?>"
							>
								<?php
								echo Petstablished_Icons::get_heart_interactive( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static, plugin-controlled SVG.
									array(),
									"state.isFavorited ? 'currentColor' : 'none'",
									$is_favorited ? 'currentColor' : 'none'
								);
								?>
							</button>

							<button
								type="button"
								class="pet-listing-grid__card-compare"
								data-wp-on--click="actions.toggleComparison"
								data-wp-bind--aria-pressed="state.isInComparison"
								data-wp-class--is-active="state.isInComparison"
								data-wp-bind--disabled="state.isCompareDisabled"
							>
								<?php
								Petstablished_Icons::render(
									'compare',
									array(
										'width'  => 16,
										'height' => 16,
									)
								);
								?>
								<span><?php esc_html_e( 'Compare', 'shelter-pets' ); ?></span>
							</button>
						</div>
					</div>
				</article>
			</li>
		<?php endforeach; ?>
	</ul>

	<!-- Empty State -->
	<div
		class="pet-listing-grid__empty"
		<?php echo empty( $pets ) ? '' : 'hidden'; ?>
		data-wp-bind--hidden="state.hasVisiblePets"
	>
		<p><?php esc_html_e( 'No pets match your filters.', 'shelter-pets' ); ?></p>
		<button type="button" class="pet-listing-grid__empty-reset" data-wp-on--click="actions.resetFilters">
			<?php esc_html_e( 'Show all pets', 'shelter-pets' ); ?>
		</button>
	</div>

	<?php // === Pagination === ?>
	<?php
	if ( $total_pages > 1 ) :
		$base_url       = $archive_url;
		$current_params = array_filter(
			array_merge(
				array_map(
					fn( $v ) => $v ?: null,
					array_combine(
						array_map( fn( $k ) => 'filter_' . $k, array_keys( $url_filters ) ),
						array_values( $url_filters )
					)
				),
				array_map(
					fn( $v ) => $v ? '1' : null,
					array_combine(
						array_map( fn( $k ) => 'compat_' . $k, array_keys( $url_compat ) ),
						array_values( $url_compat )
					)
				),
				$search_query ? array( 'pet_search' => $search_query ) : array(),
				$current_sort ? array( 'sort' => $current_sort ) : array(),
			)
		);

		// Build page URL helper.
		$page_url_fn = function ( $page_num ) use ( $current_params, $base_url ) {
			$params = $current_params;
			if ( $page_num > 1 ) {
				$params['paged'] = $page_num;
			} else {
				unset( $params['paged'] );
			}
			return add_query_arg( $params, $base_url );
		};

		// Ellipsis pagination: always show first, last, and a window around current.
		$window        = 1; // pages to show on each side of current
		$pages_to_show = array();
		for ( $i = 1; $i <= $total_pages; $i++ ) {
			if ( $i === 1 || $i === $total_pages
			|| ( $i >= $paged - $window && $i <= $paged + $window ) ) {
				$pages_to_show[] = $i;
			}
		}
		$pages_to_show = array_unique( $pages_to_show );
		sort( $pages_to_show );
		?>
		<nav class="pet-listing-grid__pagination" aria-label="<?php esc_attr_e( 'Pet listing pages', 'shelter-pets' ); ?>">
			<?php // Prev button ?>
			<?php if ( $paged > 1 ) : ?>
				<a
					href="<?php echo esc_url( $page_url_fn( $paged - 1 ) ); ?>"
					class="pet-listing-grid__page pet-listing-grid__page--prev"
					data-wp-on--click="actions.navigateToPage"
					data-wp-on--mouseenter="actions.prefetchPage"
					aria-label="<?php esc_attr_e( 'Previous page', 'shelter-pets' ); ?>"
				>
					<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>
				</a>
			<?php else : ?>
				<span class="pet-listing-grid__page pet-listing-grid__page--prev is-disabled" aria-hidden="true">
					<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M15 18l-6-6 6-6"/></svg>
				</span>
			<?php endif; ?>

			<?php
			// Page numbers with ellipsis.
			$prev_page = 0;
			foreach ( $pages_to_show as $page_num ) :
				if ( $prev_page && $page_num - $prev_page > 1 ) :
					?>
					<span class="pet-listing-grid__page pet-listing-grid__page--ellipsis" aria-hidden="true">…</span>
					<?php
				endif;
				$prev_page = $page_num;

				if ( $page_num === $paged ) :
					?>
					<span class="pet-listing-grid__page pet-listing-grid__page--current" aria-current="page">
						<?php echo esc_html( $page_num ); ?>
					</span>
				<?php else : ?>
					<a
						href="<?php echo esc_url( $page_url_fn( $page_num ) ); ?>"
						class="pet-listing-grid__page"
						data-wp-on--click="actions.navigateToPage"
						data-wp-on--mouseenter="actions.prefetchPage"
					>
						<?php echo esc_html( $page_num ); ?>
					</a>
					<?php
				endif;
			endforeach;
			?>

			<?php // Next button ?>
			<?php if ( $paged < $total_pages ) : ?>
				<a
					href="<?php echo esc_url( $page_url_fn( $paged + 1 ) ); ?>"
					class="pet-listing-grid__page pet-listing-grid__page--next"
					data-wp-on--click="actions.navigateToPage"
					data-wp-on--mouseenter="actions.prefetchPage"
					aria-label="<?php esc_attr_e( 'Next page', 'shelter-pets' ); ?>"
				>
					<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M9 18l6-6-6-6"/></svg>
				</a>
			<?php else : ?>
				<span class="pet-listing-grid__page pet-listing-grid__page--next is-disabled" aria-hidden="true">
					<svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path d="M9 18l6-6-6-6"/></svg>
				</span>
			<?php endif; ?>
		</nav>
	<?php endif; ?>
</div><!-- .pet-listing-grid__inner (context) -->
</div><!-- .pet-listing-grid (router-region) -->