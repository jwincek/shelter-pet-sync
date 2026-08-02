<?php
/**
 * Pet Slider Block
 *
 * A versatile carousel/hero component showcasing available pets.
 * Uses Interactivity API for smooth client-side navigation.
 * Shares pet data with comparison tool via global state.
 *
 * Display Modes:
 * - carousel: Traditional sliding carousel
 * - hero: Large featured pet with thumbnails (great for 404/home)
 * - grid: Static grid display
 *
 * Similar Pets Mode:
 * When similarPetsMode is enabled, filters by current pet's animal type
 * and age group, excluding the current pet. Adapts display based on
 * result count (cards for 1-3, slider for 4+).
 *
 * @package Shelter_Pets
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Attributes with defaults.
$title              = $attributes['title'] ?? __( 'Meet Our Pets', 'shelter-pets' );
$show_title         = $attributes['showTitle'] ?? true;
$count              = $attributes['count'] ?? 8;
$order_by           = $attributes['orderBy'] ?? 'random';
$autoplay           = $attributes['autoplay'] ?? false;
$autoplay_speed     = $attributes['autoplaySpeed'] ?? 5000;
$show_navigation    = $attributes['showNavigation'] ?? true;
$show_dots          = $attributes['showDots'] ?? true;
$card_style         = $attributes['cardStyle'] ?? 'default';
$display_mode       = $attributes['displayMode'] ?? 'carousel';
$show_quick_actions = $attributes['showQuickActions'] ?? true;
$show_badges        = $attributes['showBadges'] ?? true;
$badge_position     = $attributes['badgePosition'] ?? 'image-top';
$cta_text           = $attributes['ctaText'] ?? __( 'Find Your New Best Friend', 'shelter-pets' );
$show_cta           = $attributes['showCta'] ?? false;
$link_to_archive    = $attributes['linkToArchive'] ?? true;
$archive_link_text  = $attributes['archiveLinkText'] ?? __( 'View All Pets', 'shelter-pets' );

// Similar pets mode attributes.
$similar_pets_mode = $attributes['similarPetsMode'] ?? false;
$filter_animal     = $attributes['filterAnimal'] ?? '';
$filter_age        = $attributes['filterAge'] ?? '';
$exclude_post_id   = $attributes['excludePostId'] ?? 0;

// Style attributes.
$card_border_radius = absint( $attributes['cardBorderRadius'] ?? 12 );
$card_gap           = absint( $attributes['cardGap'] ?? 16 );
$name_font_size     = $attributes['nameFontSize'] ?? '';
$name_font_family   = $attributes['nameFontFamily'] ?? '';
$meta_font_size     = $attributes['metaFontSize'] ?? '';
$meta_font_family   = $attributes['metaFontFamily'] ?? '';

// Handle similar pets mode — read filters from current pet's hydrated entity.
if ( $similar_pets_mode ) {
	$current_post_id = $block->context['postId'] ?? get_the_ID();

	if ( $current_post_id && get_post_type( $current_post_id ) === 'vcps_pet' ) {
		$exclude_post_id = $current_post_id;

		// Read from hydrated entity instead of wp_get_post_terms() calls.
		$current_pet = null;
		$get_ability = function_exists( 'wp_get_ability' ) ? wp_get_ability( 'petsync/get-pet' ) : null;
		if ( $get_ability ) {
			$result = $get_ability->execute( [ 'id' => (int) $current_post_id ] );
			if ( ! is_wp_error( $result ) ) {
				$current_pet = $result;
			}
		}
		if ( ! $current_pet ) {
			$current_pet = \Petsync\Core\Pet_Hydrator::get( $current_post_id );
		}

		if ( $current_pet ) {
			// Use slug fields from hydrated entity (now included in all profiles).
			if ( empty( $filter_animal ) && ! empty( $current_pet['animalSlug'] ) ) {
				$filter_animal = $current_pet['animalSlug'];
			}
			if ( empty( $filter_age ) && ! empty( $current_pet['ageSlug'] ) ) {
				$filter_age = $current_pet['ageSlug'];
			}
		}
	}
}

// Build ability input — maps slider attributes to list-pets input schema.
$ability_input = array(
	'per_page' => $count,
	'page'     => 1,
	'status'   => 'available',
);

if ( ! empty( $filter_animal ) ) {
	$ability_input['animal'] = $filter_animal;
}
if ( ! empty( $filter_age ) ) {
	$ability_input['age'] = $filter_age;
}
if ( $exclude_post_id ) {
	$ability_input['exclude'] = array( $exclude_post_id );
}

// Map slider orderBy to query orderBy.
switch ( $order_by ) {
	case 'random':
		$ability_input['orderby'] = 'rand';
		break;
	case 'newest':
		$ability_input['orderby'] = 'date';
		$ability_input['order']   = 'DESC';
		break;
	case 'name':
		$ability_input['orderby'] = 'title';
		$ability_input['order']   = 'ASC';
		break;
}

// Execute via Abilities API with fallback.
$pets         = array();
$list_ability = function_exists( 'wp_get_ability' ) ? wp_get_ability( 'petsync/list-pets' ) : null;

if ( $list_ability ) {
	$result = $list_ability->execute( $ability_input );
	if ( ! is_wp_error( $result ) ) {
		$pets = $result['pets'] ?? array();
	}
}

// Fallback: direct query builder (ability unavailable or failed).
if ( empty( $pets ) ) {
	$query = \Petsync\Core\Query::for( 'vcps_pet' )
		->status( 'available' );

	if ( ! empty( $filter_animal ) ) {
		$query->where( 'animal', $filter_animal );
	}
	if ( ! empty( $filter_age ) ) {
		$query->where( 'age', $filter_age );
	}
	if ( $exclude_post_id ) {
		$query->withArgs( array( 'post__not_in' => array( $exclude_post_id ) ) );
	}
	if ( $order_by === 'random' ) {
		$query->orderBy( 'rand' );
	} elseif ( $order_by === 'name' ) {
		$query->orderBy( 'title', 'ASC' );
	}

	$result = $query->paginate( 1, $count, 'summary' );
	$pets   = $result['items'] ?? array();
}

if ( empty( $pets ) ) {
	return; // No pets to show.
}

// Determine display mode based on result count for similar pets.
$pet_count        = count( $pets );
$use_cards_layout = $similar_pets_mode && $pet_count <= 3;

// Build pets map for global state (comparison tool lookups).
// Uses string-keyed arrays for proper merge via array_replace_recursive.
$pets_for_state = array();
foreach ( $pets as $pet ) {
	$pets_for_state[ (string) $pet['id'] ] = array(
		'id'   => $pet['id'],
		'name' => $pet['name'],
		'url'  => $pet['url'],
	);
}

// Context for Interactivity API — slider navigation state.
$context = array(
	'pets'          => $pets,
	'currentIndex'  => 0,
	'autoplay'      => $autoplay,
	'autoplaySpeed' => $autoplay_speed,
	'isPaused'      => false,
	'touchStartX'   => 0,
	'displayMode'   => $use_cards_layout ? 'cards' : $display_mode,
	'archiveUrl'    => get_post_type_archive_link( 'vcps_pet' ),
);

// Merge minimal pet data into global state for comparison tool.
// Uses string-keyed array (not stdClass) — safe with multiple block instances.
wp_interactivity_state(
	'petsync',
	array(
		'pets' => $pets_for_state,
	)
);

$wrapper_classes = array(
	'pet-slider',
	'pet-slider--' . $card_style,
	'pet-slider--' . ( $use_cards_layout ? 'cards' : $display_mode ),
);

if ( $similar_pets_mode ) {
	$wrapper_classes[] = 'pet-slider--similar';
}

// Generate unique ID for this slider instance.
$slider_id = 'pet-slider-' . wp_unique_id();

// Build scoped CSS for custom styling — set custom properties on the root.
$scoped_styles   = array();
$scoped_styles[] = "#{$slider_id} { --slider-gap: " . absint( $card_gap ) . 'px; --slider-card-radius: ' . absint( $card_border_radius ) . 'px; }';

if ( ! empty( $name_font_size ) ) {
	$scoped_styles[] = "#{$slider_id} .pet-slider__name { font-size: " . esc_attr( $name_font_size ) . ' !important; }';
}
if ( ! empty( $name_font_family ) ) {
	$scoped_styles[] = "#{$slider_id} .pet-slider__name { font-family: " . esc_attr( $name_font_family ) . ' !important; }';
}
if ( ! empty( $meta_font_size ) ) {
	$scoped_styles[] = "#{$slider_id} .pet-slider__meta { font-size: " . esc_attr( $meta_font_size ) . ' !important; }';
}
if ( ! empty( $meta_font_family ) ) {
	$scoped_styles[] = "#{$slider_id} .pet-slider__meta { font-family: " . esc_attr( $meta_font_family ) . ' !important; }';
}

$extra_attrs = array(
	'id'                    => $slider_id,
	'class'                 => implode( ' ', $wrapper_classes ),
	'data-wp-interactive'   => 'petsync/slider',
	'data-wp-context'       => wp_json_encode( $context ),
	'data-wp-init'          => $use_cards_layout ? '' : 'callbacks.init',
	'data-wp-router-region' => 'pet-slider-' . $slider_id,
);

// Flush layout hints for CSS.
if ( 0 === $card_gap ) {
	$extra_attrs['data-flush-cards'] = '';
}
if ( 0 === $card_border_radius ) {
	$extra_attrs['data-flush-radius'] = '';
}

$wrapper_attributes = get_block_wrapper_attributes( $extra_attrs );

$archive_url = get_post_type_archive_link( 'vcps_pet' );
?>

<div <?php echo $wrapper_attributes; /* phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_block_wrapper_attributes() returns escaped HTML. */ ?>>
	<style><?php echo implode( ' ', $scoped_styles ); /* phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSS values pre-escaped with absint()/esc_attr(). */ ?></style>

	<?php if ( $use_cards_layout ) : ?>
		<!-- CARDS MODE: Static cards for 1-3 similar pets -->
		<?php if ( $show_title ) : ?>
			<div class="pet-slider__header">
				<h2 class="pet-slider__title"><?php echo esc_html( $title ); ?></h2>
				<?php if ( $link_to_archive ) : ?>
					<a 
						href="<?php echo esc_url( $archive_url ); ?>" 
						class="pet-slider__view-all"
					>
						<?php echo esc_html( $archive_link_text ); ?>
						<?php
						Petsync_Icons::render(
							'arrow-right',
							array(
								'width'  => 16,
								'height' => 16,
							)
						);
						?>
					</a>
				<?php endif; ?>
			</div>
		<?php endif; ?>

		<div class="pet-slider__cards" style="--card-count: <?php echo esc_attr( $pet_count ); ?>">
			<?php
			foreach ( $pets as $index => $pet ) :
				$slide_class      = 'pet-slider__slide pet-slider__slide--card';
				$slide_directives = '';
				include __DIR__ . '/partials/card.php';
			endforeach;
			?>
		</div>

		<?php if ( ! $show_title && $link_to_archive ) : ?>
			<div class="pet-slider__footer">
				<a 
					href="<?php echo esc_url( $archive_url ); ?>" 
					class="pet-slider__view-all-btn"
				>
					<?php echo esc_html( $archive_link_text ); ?>
					<?php
					Petsync_Icons::render(
						'arrow-right',
						array(
							'width'  => 16,
							'height' => 16,
						)
					);
					?>
				</a>
			</div>
		<?php endif; ?>

	<?php elseif ( $display_mode === 'hero' ) : ?>
		<!-- HERO MODE: Large featured pet with thumbnails -->
		<div class="pet-slider__hero">
			<?php if ( $show_title || $show_cta ) : ?>
				<div class="pet-slider__hero-header">
					<?php if ( $show_title ) : ?>
						<h2 class="pet-slider__hero-title"><?php echo esc_html( $title ); ?></h2>
					<?php endif; ?>
					<?php if ( $show_cta ) : ?>
						<p class="pet-slider__hero-cta"><?php echo esc_html( $cta_text ); ?></p>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<div class="pet-slider__hero-content">
				<!-- Main Featured Pet -->
				<div class="pet-slider__hero-main">
					<div
						class="pet-slider__hero-image-wrap"
						data-wp-on--touchstart="actions.handleTouchStart"
						data-wp-on--touchend="actions.handleTouchEnd"
						data-wp-watch="callbacks.heroCrossfade"
					>
						<img 
							src="<?php echo esc_url( $pets[0]['image'] ); ?>"
							alt=""
							class="pet-slider__hero-image"
							data-wp-bind--src="state.currentPetImage"
							data-wp-bind--alt="state.currentPetName"
							data-wp-class--is-transitioning="state.isTransitioning"
						>
						
						<?php if ( $show_quick_actions ) : ?>
							<div class="pet-slider__hero-actions">
								<button
									type="button"
									class="pet-slider__hero-action pet-slider__hero-action--favorite"
									data-wp-on--click="actions.toggleCurrentPetFavorite"
									data-wp-class--is-active="state.isCurrentPetFavorited"
									aria-label="<?php esc_attr_e( 'Add to favorites', 'shelter-pets' ); ?>"
								>
									<?php
									echo Petsync_Icons::get_heart_interactive( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static, plugin-controlled SVG.
										array(
											'width'  => 24,
											'height' => 24,
										),
										"state.isCurrentPetFavorited ? 'currentColor' : 'none'"
									); /* phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static, plugin-controlled SVG. */
									?>
								</button>
								<button
									type="button"
									class="pet-slider__hero-action pet-slider__hero-action--compare"
									data-wp-on--click="actions.toggleCurrentPetComparison"
									data-wp-class--is-active="state.isCurrentPetInComparison"
									aria-label="<?php esc_attr_e( 'Add to comparison', 'shelter-pets' ); ?>"
								>
									<?php
									Petsync_Icons::render(
										'compare-grid',
										array(
											'width'  => 24,
											'height' => 24,
										)
									);
									?>
								</button>
							</div>
						<?php endif; ?>

						<?php if ( count( $pets ) > 1 ) : ?>
							<div class="pet-slider__hero-counter">
								<span data-wp-text="state.currentNumber">1</span> / <?php echo count( $pets ); ?>
							</div>
						<?php endif; ?>

						<!-- Mobile compact overlay: name + meta + CTA over the image -->
						<div class="pet-slider__hero-overlay-info">
							<h3 class="pet-slider__hero-overlay-name">
								<a 
									href="<?php echo esc_url( $pets[0]['url'] ); ?>"
									data-wp-bind--href="state.currentPetUrl"
									data-wp-text="state.currentPetName"
								>
									<?php echo esc_html( $pets[0]['name'] ); ?>
								</a>
							</h3>
							<p class="pet-slider__hero-overlay-meta" data-wp-text="state.currentPetMeta">
								<?php
								$overlay_meta = array_filter( array( $pets[0]['breed'] ?? '', $pets[0]['age'] ?? '', $pets[0]['sex'] ?? '' ) );
								echo esc_html( implode( ' · ', $overlay_meta ) );
								?>
							</p>
							<a 
								href="<?php echo esc_url( $pets[0]['url'] ); ?>"
								class="pet-slider__hero-overlay-btn"
								data-wp-bind--href="state.currentPetUrl"
							>
								<?php esc_html_e( 'Meet Me', 'shelter-pets' ); ?>
								<?php
								Petsync_Icons::render(
									'arrow-right',
									array(
										'width'  => 14,
										'height' => 14,
									)
								);
								?>
							</a>
						</div>
					</div>

					<div class="pet-slider__hero-info">
						<h3 class="pet-slider__hero-name">
							<a 
								href="<?php echo esc_url( $pets[0]['url'] ); ?>"
								data-wp-bind--href="state.currentPetUrl"
								data-wp-text="state.currentPetName"
							>
								<?php echo esc_html( $pets[0]['name'] ); ?>
							</a>
						</h3>
						<p class="pet-slider__hero-meta" data-wp-text="state.currentPetMeta">
							<?php
							$meta_parts = array_filter( array( $pets[0]['breed'], $pets[0]['age'], $pets[0]['sex'] ) );
							echo esc_html( implode( ' · ', $meta_parts ) );
							?>
						</p>
						<a 
							href="<?php echo esc_url( $pets[0]['url'] ); ?>"
							class="pet-slider__hero-btn"
							data-wp-bind--href="state.currentPetUrl"
						>
							<?php esc_html_e( 'Meet Me', 'shelter-pets' ); ?>
							<?php
							Petsync_Icons::render(
								'arrow-right',
								array(
									'width'  => 16,
									'height' => 16,
								)
							);
							?>
						</a>
					</div>
				</div>

				<!-- Thumbnail Strip -->
				<?php if ( count( $pets ) > 1 ) : ?>
					<div class="pet-slider__hero-thumbs">
						<?php foreach ( $pets as $index => $pet ) : ?>
							<button
								type="button"
								class="pet-slider__hero-thumb <?php echo $index === 0 ? 'is-active' : ''; ?>"
								data-wp-on--click="actions.goTo"
								data-wp-class--is-active="state.isDotActive"
								<?php echo wp_interactivity_data_wp_context( array( 'dotIndex' => $index ) ); /* phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_interactivity_data_wp_context() returns an escaped attribute. */ ?>
								aria-label="<?php echo esc_attr( sprintf( /* translators: %s: pet name */ __( 'View %s', 'shelter-pets' ), $pet['name'] ) ); ?>"
							>
								<img 
									src="<?php echo esc_url( $pet['thumb'] ); ?>" 
									alt="<?php echo esc_attr( $pet['name'] ); ?>"
									loading="lazy"
								>
							</button>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</div>

			<?php if ( $link_to_archive ) : ?>
				<div class="pet-slider__hero-footer">
					<a 
						href="<?php echo esc_url( $archive_url ); ?>" 
						class="pet-slider__view-all-btn"
					>
						<?php echo esc_html( $archive_link_text ); ?>
						<?php
						Petsync_Icons::render(
							'arrow-right',
							array(
								'width'  => 16,
								'height' => 16,
							)
						);
						?>
					</a>
				</div>
			<?php endif; ?>
		</div>

	<?php else : ?>
		<!-- CAROUSEL MODE: Traditional sliding carousel -->
		<?php if ( $show_title ) : ?>
			<div class="pet-slider__header">
				<h2 class="pet-slider__title"><?php echo esc_html( $title ); ?></h2>
				<?php if ( $link_to_archive ) : ?>
					<a 
						href="<?php echo esc_url( $archive_url ); ?>" 
						class="pet-slider__view-all"
					>
						<?php echo esc_html( $archive_link_text ); ?>
						<?php
						Petsync_Icons::render(
							'arrow-right',
							array(
								'width'  => 16,
								'height' => 16,
							)
						);
						?>
					</a>
				<?php endif; ?>
			</div>
		<?php endif; ?>

		<div
			class="pet-slider__viewport"
			data-wp-watch="callbacks.syncScroll"
		>
			<div class="pet-slider__track">
				<?php
				foreach ( $pets as $index => $pet ) :
					$slide_class      = 'pet-slider__slide';
					$slide_directives = 'data-wp-class--is-active="state.isActiveSlide"';
					include __DIR__ . '/partials/card.php';
				endforeach;
				?>
			</div>
		</div>

		<?php if ( $show_navigation && count( $pets ) > 1 ) : ?>
			<button
				type="button"
				class="pet-slider__nav pet-slider__nav--prev"
				data-wp-on--click="actions.prev"
				aria-label="<?php esc_attr_e( 'Previous pet', 'shelter-pets' ); ?>"
			>
				<?php
				Petsync_Icons::render(
					'chevron-left',
					array(
						'width'  => 24,
						'height' => 24,
					)
				);
				?>
			</button>
			<button
				type="button"
				class="pet-slider__nav pet-slider__nav--next"
				data-wp-on--click="actions.next"
				aria-label="<?php esc_attr_e( 'Next pet', 'shelter-pets' ); ?>"
			>
				<?php
				Petsync_Icons::render(
					'chevron-right',
					array(
						'width'  => 24,
						'height' => 24,
					)
				);
				?>
			</button>
		<?php endif; ?>

		<?php if ( $show_dots && count( $pets ) > 1 ) : ?>
			<div
				class="pet-slider__dots" 
				role="tablist" 
				aria-label="<?php esc_attr_e( 'Slide navigation', 'shelter-pets' ); ?>"
				data-total-slides="<?php echo count( $pets ); ?>"
			>
				<!-- Dots generated dynamically by JS based on visible slides -->
			</div>
		<?php endif; ?>

		<?php if ( ! $show_title && $link_to_archive ) : ?>
			<div class="pet-slider__footer">
				<a 
					href="<?php echo esc_url( $archive_url ); ?>" 
					class="pet-slider__view-all-btn"
				>
					<?php echo esc_html( $archive_link_text ); ?>
					<?php
					Petsync_Icons::render(
						'arrow-right',
						array(
							'width'  => 16,
							'height' => 16,
						)
					);
					?>
				</a>
			</div>
		<?php endif; ?>
	<?php endif; ?>

</div>