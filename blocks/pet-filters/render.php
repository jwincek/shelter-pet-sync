<?php
/**
 * Pet Filters Block
 *
 * Progressive enhancement: Works as plain HTML form (no JS required),
 * enhanced with Interactivity API for instant filtering.
 *
 * @package ShelterKit_Pets
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$options = [];
$ability = function_exists( 'wp_get_ability' ) ? wp_get_ability( 'petsync/get-filter-options' ) : null;
if ( $ability ) {
	$result = $ability->execute( [] );
	if ( ! is_wp_error( $result ) ) {
		$options = $result;
	}
} else {
	$options = Petsync_Helpers::get_filter_options();
}
$layout = $attributes['layout'] ?? 'horizontal';

// Compatibility filter settings.
$show_compatibility      = $attributes['showCompatibility'] ?? true;
$compatibility_style     = $attributes['compatibilityStyle'] ?? 'checkboxes';
$compatibility_collapsed = $attributes['compatibilityCollapsed'] ?? false;

// Get current filter values from URL.
$current = array(
	'animal' => sanitize_text_field( wp_unslash( $_GET['animal'] ?? '' ) ),
	'breed'  => sanitize_text_field( wp_unslash( $_GET['breed'] ?? '' ) ),
	'age'    => sanitize_text_field( wp_unslash( $_GET['age'] ?? '' ) ),
	'sex'    => sanitize_text_field( wp_unslash( $_GET['sex'] ?? '' ) ),
	'size'   => sanitize_text_field( wp_unslash( $_GET['size'] ?? '' ) ),
	'status' => sanitize_text_field( wp_unslash( $_GET['status'] ?? '' ) ),
);

// Compatibility/meta filter values (checkboxes use 'yes' when checked).
$current_compat = array(
	'good_with_dogs'  => sanitize_text_field( wp_unslash( $_GET['good_with_dogs'] ?? '' ) ),
	'good_with_cats'  => sanitize_text_field( wp_unslash( $_GET['good_with_cats'] ?? '' ) ),
	'good_with_kids'  => sanitize_text_field( wp_unslash( $_GET['good_with_kids'] ?? '' ) ),
	'shots_current'   => sanitize_text_field( wp_unslash( $_GET['shots_current'] ?? '' ) ),
	'spayed_neutered' => sanitize_text_field( wp_unslash( $_GET['spayed_neutered'] ?? '' ) ),
	'housebroken'     => sanitize_text_field( wp_unslash( $_GET['housebroken'] ?? '' ) ),
	'special_needs'   => sanitize_text_field( wp_unslash( $_GET['special_needs'] ?? '' ) ),
	'hypoallergenic'  => sanitize_text_field( wp_unslash( $_GET['hypoallergenic'] ?? '' ) ),
	'declawed'        => sanitize_text_field( wp_unslash( $_GET['declawed'] ?? '' ) ),
);

$filters = array(
	'animal' => array(
		'label'   => __( 'Animal', 'shelterkit-pets' ),
		'show'    => $attributes['showAnimal'] ?? true,
		'options' => $options['animal'] ?? array(),
		'all'     => __( 'All Animals', 'shelterkit-pets' ),
	),
	'breed'  => array(
		'label'   => __( 'Breed', 'shelterkit-pets' ),
		'show'    => $attributes['showBreed'] ?? true,
		'options' => $options['breed'] ?? array(),
		'all'     => __( 'All Breeds', 'shelterkit-pets' ),
	),
	'age'    => array(
		'label'   => __( 'Age', 'shelterkit-pets' ),
		'show'    => $attributes['showAge'] ?? true,
		'options' => $options['age'] ?? array(),
		'all'     => __( 'Any Age', 'shelterkit-pets' ),
	),
	'sex'    => array(
		'label'   => __( 'Sex', 'shelterkit-pets' ),
		'show'    => $attributes['showSex'] ?? true,
		'options' => $options['sex'] ?? array(),
		'all'     => __( 'Any', 'shelterkit-pets' ),
	),
	'size'   => array(
		'label'   => __( 'Size', 'shelterkit-pets' ),
		'show'    => $attributes['showSize'] ?? true,
		'options' => $options['size'] ?? array(),
		'all'     => __( 'Any Size', 'shelterkit-pets' ),
	),
	'status' => array(
		'label'   => __( 'Status', 'shelterkit-pets' ),
		'show'    => $attributes['showStatus'] ?? false,
		'options' => $options['status'] ?? array(),
		'all'     => __( 'Any Status', 'shelterkit-pets' ),
	),
);

// Compatibility/meta filters configuration.
$compat_filters = array(
	'good_with_dogs'  => array(
		'label' => __( 'Good with dogs', 'shelterkit-pets' ),
		'icon'  => '🐕',
		'show'  => $attributes['showGoodWithDogs'] ?? true,
		'group' => 'compatibility',
	),
	'good_with_cats'  => array(
		'label' => __( 'Good with cats', 'shelterkit-pets' ),
		'icon'  => '🐈',
		'show'  => $attributes['showGoodWithCats'] ?? true,
		'group' => 'compatibility',
	),
	'good_with_kids'  => array(
		'label' => __( 'Good with kids', 'shelterkit-pets' ),
		'icon'  => '👶',
		'show'  => $attributes['showGoodWithKids'] ?? true,
		'group' => 'compatibility',
	),
	'shots_current'   => array(
		'label' => __( 'Shots current', 'shelterkit-pets' ),
		'icon'  => '💉',
		'show'  => $attributes['showShotsCurrent'] ?? true,
		'group' => 'health',
	),
	'spayed_neutered' => array(
		'label' => __( 'Spayed/Neutered', 'shelterkit-pets' ),
		'icon'  => '✓',
		'show'  => $attributes['showSpayedNeutered'] ?? true,
		'group' => 'health',
	),
	'housebroken'     => array(
		'label' => __( 'Housebroken', 'shelterkit-pets' ),
		'icon'  => '🏠',
		'show'  => $attributes['showHousebroken'] ?? true,
		'group' => 'health',
	),
	'special_needs'   => array(
		'label' => __( 'Special needs', 'shelterkit-pets' ),
		'icon'  => '❤️',
		'show'  => $attributes['showSpecialNeeds'] ?? true,
		'group' => 'health',
	),
	'hypoallergenic'  => array(
		'label' => __( 'Hypoallergenic', 'shelterkit-pets' ),
		'icon'  => '✨',
		'show'  => $attributes['showHypoallergenic'] ?? true,
		'group' => 'health',
	),
	'declawed'        => array(
		'label' => __( 'Declawed', 'shelterkit-pets' ),
		'icon'  => '🐾',
		'show'  => $attributes['showDeclawed'] ?? false,
		'group' => 'health',
	),
);

// Check if any compatibility filters are enabled.
$has_compat_filters = $show_compatibility && count( array_filter( array_column( $compat_filters, 'show' ) ) ) > 0;

// Check if any compatibility filters are active.
$active_compat_count = count( array_filter( $current_compat ) );

$wrapper_attributes = get_block_wrapper_attributes(
	array(
		'class'               => 'pet-filters pet-filters--' . $layout,
		'data-wp-interactive' => 'petsync/filters',
		'data-wp-init'        => 'callbacks.init',
	)
);

$archive_url = get_post_type_archive_link( 'vcps_pet' );
?>

<div <?php echo $wrapper_attributes; /* phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_block_wrapper_attributes() returns escaped HTML. */ ?>>
	<form 
		class="pet-filters__form" 
		method="get" 
		action="<?php echo esc_url( $archive_url ); ?>"
		role="search"
		aria-label="<?php esc_attr_e( 'Filter pets', 'shelterkit-pets' ); ?>"
	>
		<div class="pet-filters__fields">
			<?php foreach ( $filters as $key => $filter ) : ?>
				<?php if ( $filter['show'] && ! empty( $filter['options'] ) ) : ?>
					<div class="pet-filters__field">
						<label for="pet-filter-<?php echo esc_attr( $key ); ?>" class="pet-filters__label">
							<?php echo esc_html( $filter['label'] ); ?>
						</label>
						<select
							id="pet-filter-<?php echo esc_attr( $key ); ?>"
							name="<?php echo esc_attr( $key ); ?>"
							class="pet-filters__select"
							data-wp-on--change="actions.handleFilterChange"
						>
							<option value=""><?php echo esc_html( $filter['all'] ); ?></option>
							<?php foreach ( $filter['options'] as $option ) : ?>
								<option 
									value="<?php echo esc_attr( $option['value'] ); ?>"
									<?php selected( $current[ $key ], $option['value'] ); ?>
								>
									<?php echo esc_html( $option['label'] ); ?>
									(<?php echo esc_html( $option['count'] ); ?>)
								</option>
							<?php endforeach; ?>
						</select>
					</div>
				<?php endif; ?>
			<?php endforeach; ?>
		</div>

		<?php if ( $has_compat_filters ) : ?>
			<div class="pet-filters__compat-section">
				<button 
					type="button"
					class="pet-filters__compat-toggle"
					aria-expanded="<?php echo $compatibility_collapsed ? 'false' : 'true'; ?>"
					aria-controls="pet-filters-compat"
					data-wp-on--click="actions.toggleCompatFilters"
					data-wp-bind--aria-expanded="state.compatFiltersExpanded"
				>
					<span class="pet-filters__compat-toggle-text">
						<?php esc_html_e( 'More Filters', 'shelterkit-pets' ); ?>
						<?php if ( $active_compat_count > 0 ) : ?>
							<span class="pet-filters__compat-badge"><?php echo esc_html( $active_compat_count ); ?></span>
						<?php endif; ?>
					</span>
					<?php
					Petsync_Icons::render(
						'chevron-down',
						array(
							'width'  => 20,
							'height' => 20,
							'class'  => 'pet-filters__compat-toggle-icon',
						)
					);
					?>
				</button>

				<div 
					id="pet-filters-compat"
					class="pet-filters__compat-content pet-filters__compat-content--<?php echo esc_attr( $compatibility_style ); ?>"
					data-wp-bind--hidden="!state.compatFiltersExpanded"
					<?php echo $compatibility_collapsed ? 'hidden' : ''; ?>
				>
					<?php if ( $compatibility_style === 'chips' ) : ?>
						<!-- Chip-style filters -->
						<div class="pet-filters__chips" role="group" aria-label="<?php esc_attr_e( 'Compatibility filters', 'shelterkit-pets' ); ?>">
							<?php foreach ( $compat_filters as $key => $filter ) : ?>
								<?php if ( $filter['show'] ) : ?>
									<label class="pet-filters__chip <?php echo $current_compat[ $key ] === 'yes' ? 'pet-filters__chip--active' : ''; ?>">
										<input
											type="checkbox"
											name="<?php echo esc_attr( $key ); ?>"
											value="yes"
											class="pet-filters__chip-input"
											<?php checked( $current_compat[ $key ], 'yes' ); ?>
											data-wp-on--change="actions.handleCompatFilterChange"
										>
										<span class="pet-filters__chip-icon" aria-hidden="true"><?php echo esc_html( $filter['icon'] ); ?></span>
										<span class="pet-filters__chip-label"><?php echo esc_html( $filter['label'] ); ?></span>
									</label>
								<?php endif; ?>
							<?php endforeach; ?>
						</div>
					<?php else : ?>
						<!-- Checkbox-style filters (grouped) -->
						<div class="pet-filters__checkboxes">
							<?php
							// Group filters by category.
							$compat_group = array_filter( $compat_filters, fn( $f ) => $f['show'] && $f['group'] === 'compatibility' );
							$health_group = array_filter( $compat_filters, fn( $f ) => $f['show'] && $f['group'] === 'health' );
							?>

							<?php if ( ! empty( $compat_group ) ) : ?>
								<fieldset class="pet-filters__checkbox-group">
									<legend class="pet-filters__checkbox-legend"><?php esc_html_e( 'Good with', 'shelterkit-pets' ); ?></legend>
									<?php foreach ( $compat_group as $key => $filter ) : ?>
										<label class="pet-filters__checkbox-label">
											<input
												type="checkbox"
												name="<?php echo esc_attr( $key ); ?>"
												value="yes"
												class="pet-filters__checkbox"
												<?php checked( $current_compat[ $key ], 'yes' ); ?>
												data-wp-on--change="actions.handleCompatFilterChange"
											>
											<span class="pet-filters__checkbox-icon" aria-hidden="true"><?php echo esc_html( $filter['icon'] ); ?></span>
											<span class="pet-filters__checkbox-text"><?php echo esc_html( str_replace( 'Good with ', '', $filter['label'] ) ); ?></span>
										</label>
									<?php endforeach; ?>
								</fieldset>
							<?php endif; ?>

							<?php if ( ! empty( $health_group ) ) : ?>
								<fieldset class="pet-filters__checkbox-group">
									<legend class="pet-filters__checkbox-legend"><?php esc_html_e( 'Health & Training', 'shelterkit-pets' ); ?></legend>
									<?php foreach ( $health_group as $key => $filter ) : ?>
										<label class="pet-filters__checkbox-label">
											<input
												type="checkbox"
												name="<?php echo esc_attr( $key ); ?>"
												value="yes"
												class="pet-filters__checkbox"
												<?php checked( $current_compat[ $key ], 'yes' ); ?>
												data-wp-on--change="actions.handleCompatFilterChange"
											>
											<span class="pet-filters__checkbox-icon" aria-hidden="true"><?php echo esc_html( $filter['icon'] ); ?></span>
											<span class="pet-filters__checkbox-text"><?php echo esc_html( $filter['label'] ); ?></span>
										</label>
									<?php endforeach; ?>
								</fieldset>
							<?php endif; ?>
						</div>
					<?php endif; ?>
				</div>
			</div>
		<?php endif; ?>

		<div class="pet-filters__actions">
			<button type="submit" class="pet-filters__submit">
				<?php esc_html_e( 'Apply Filters', 'shelterkit-pets' ); ?>
			</button>

			<?php if ( array_filter( $current ) || array_filter( $current_compat ) ) : ?>
				<a href="<?php echo esc_url( $archive_url ); ?>" class="pet-filters__reset">
					<?php esc_html_e( 'Reset', 'shelterkit-pets' ); ?>
				</a>
			<?php endif; ?>

		</div>

		<div class="pet-filters__favorites" data-wp-interactive="petsync/grid">
			<button
				type="button"
				class="pet-filters__favorites-toggle"
				data-wp-on--click="actions.toggleFavoritesFilter"
				data-wp-class--is-active="state.showFavoritesOnly"
				data-wp-text="state.favoritesFilterText"
				aria-pressed="false"
				data-wp-bind--aria-pressed="state.showFavoritesOnly"
			>
				<?php
				/* translators: default label before JS hydrates */
				esc_html_e( "\u{2665} Favorites", 'shelterkit-pets' );
				?>
			</button>
		</div>

		<?php
		// Show active filter count for screen readers.
		$active_count = count( array_filter( $current ) ) + $active_compat_count;
		if ( $active_count ) :
			?>
			<p class="screen-reader-text" role="status">
				<?php
				printf(
					/* translators: %d: number of active filters */
					esc_html( _n( '%d filter active', '%d filters active', $active_count, 'shelterkit-pets' ) ),
					(int) $active_count
				);
				?>
			</p>
		<?php endif; ?>
	</form>
</div>
