<?php
/**
 * Pet Favorites Modal Block
 *
 * Floating heart button + modal overlay with full pet cards.
 * Uses attachTo:"body" for cross-page availability via the router.
 *
 * SSR SAFETY RULES (WP 6.9):
 * ─────────────────────────────────────────────────────────────
 * The server-side directive processor evaluates data-wp-bind,
 * data-wp-text, data-wp-class values against wp_interactivity_state().
 * JS-only getters evaluate to null on the server.
 *
 * For data-wp-bind--hidden:
 *   null  → remove hidden (element VISIBLE)
 *   false → remove hidden (element VISIBLE)
 *   true  → set hidden    (element HIDDEN)
 *
 * Negation flips null: !null → true → HIDDEN (DANGER!)
 *
 * Therefore:
 *   SAFE:   data-wp-bind--hidden="state.isCardRemoved"
 *           SSR: null → visible. Client: false → visible, true → hidden. ✓
 *
 *   UNSAFE: data-wp-bind--hidden="!state.isCardFavorited"
 *           SSR: !null → true → hidden on first render! ✗
 *
 * For counts and text, use petsync::state.favorites.length
 * which the SSR evaluates via the array .length handler (6.8+).
 *
 * @package Shelter_Pets
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$position     = $attributes['position'] ?? 'bottom-right';
$show_compare = $attributes['showCompare'] ?? true;
$favorites    = Petsync_Helpers::get_favorites();
$fav_count    = count( $favorites );

// Enqueue the modal script module so the Interactivity API hydration
// waits for it. This ensures store() runs BEFORE directives evaluate,
// replacing the server-provided static state with reactive getters.
wp_enqueue_script_module( 'petstablished-favorites-modal' );

// Hydrate favorited pets for initial server render.
$favorite_pets = array();
if ( $favorites ) {
	$posts = get_posts(
		array(
			'post_type'      => 'vcps_pet',
			'post_status'    => 'publish',
			'post__in'       => $favorites,
			'posts_per_page' => $fav_count,
			'orderby'        => 'post__in',
		)
	);

	foreach ( $posts as $post ) {
		$pet = \Petsync\Core\Pet_Hydrator::hydrate( $post, 'summary' );
		if ( $pet ) {
			$favorite_pets[] = $pet;
		}
	}

	// Merge pet data into the global store so client-side syncCards
	// can render cards for newly-added favorites from the pet cache.
	$pets_cache = array();
	foreach ( $favorite_pets as $pet ) {
		$pets_cache[ $pet['id'] ] = $pet;
	}
	$pets_for_state = array();
	foreach ( $pets_cache as $id => $data ) {
		$pets_for_state[ (string) $id ] = $data;
	}
	wp_interactivity_state(
		'petsync',
		array(
			'pets' => $pets_for_state,
		)
	);
}

$context = array(
	'isOpen'      => false,
	'showCompare' => $show_compare,
);

$router_region = wp_json_encode(
	array(
		'id'       => 'pet-favorites-modal',
		'attachTo' => 'body',
	)
);

$wrapper_attributes = get_block_wrapper_attributes(
	array(
		'class'                 => 'pet-favorites-modal pet-favorites-modal--' . $position,
		'data-wp-interactive'   => 'petsync/favorites-modal',
		'data-wp-router-region' => $router_region,
		'data-wp-context'       => wp_json_encode( $context ),
		'data-wp-init'          => 'callbacks.init',
	)
);
?>

<div <?php echo $wrapper_attributes; /* phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_block_wrapper_attributes() returns escaped HTML. */ ?>>

	<!-- ─── Floating trigger button ─── -->
	<button
		type="button"
		class="pet-favorites-modal__trigger"
		data-wp-on--click="actions.toggleModal"
		data-wp-bind--aria-expanded="context.isOpen"
		aria-label="<?php esc_attr_e( 'View favorites', 'shelter-pets' ); ?>"
	>
		<svg
			class="pet-favorites-modal__trigger-icon"
			xmlns="http://www.w3.org/2000/svg"
			viewBox="0 0 24 24"
			width="24" height="24"
			fill="none" stroke="currentColor" stroke-width="2"
			stroke-linecap="round" stroke-linejoin="round"
			data-wp-bind--fill="state.triggerFill"
		>
			<path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
		</svg>
		<?php
		// Badge: uses petsync::state.favorites.length which the
		// SSR evaluates correctly (array .length → count()).
		?>
		<span
			class="pet-favorites-modal__trigger-count"
			data-wp-text="petsync::state.favorites.length"
			data-wp-watch="callbacks.syncBadgeVisibility"
			<?php echo $fav_count === 0 ? 'hidden' : ''; ?>
		><?php echo esc_html( $fav_count ?: '' ); ?></span>
	</button>

	<!-- ─── Modal overlay ─── -->
	<div
		class="pet-favorites-modal__overlay"
		data-wp-bind--hidden="!context.isOpen"
		data-wp-class--is-open="context.isOpen"
		data-wp-on--click="actions.handleOverlayClick"
		data-wp-on--keydown="actions.handleKeydown"
		role="dialog"
		aria-modal="true"
		aria-label="<?php esc_attr_e( 'Your favorite pets', 'shelter-pets' ); ?>"
		hidden
	>
		<div class="pet-favorites-modal__panel">

			<!-- Header -->
			<div class="pet-favorites-modal__header">
				<h2 class="pet-favorites-modal__title">
					<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" fill="currentColor" stroke="none" aria-hidden="true">
						<path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
					</svg>
					<?php esc_html_e( 'Favorites', 'shelter-pets' ); ?>
					<span class="pet-favorites-modal__count">(<span
						data-wp-text="petsync::state.favorites.length"
					><?php echo (int) $fav_count; ?></span>)</span>
				</h2>
				<button
					type="button"
					class="pet-favorites-modal__close"
					data-wp-on--click="actions.closeModal"
					aria-label="<?php esc_attr_e( 'Close favorites', 'shelter-pets' ); ?>"
				>
					<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
						<line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
					</svg>
				</button>
			</div>

			<!-- Content -->
			<div class="pet-favorites-modal__content">

				<!-- Empty state -->
				<div
					class="pet-favorites-modal__empty"
					data-wp-watch="callbacks.syncEmptyVisibility"
					<?php echo $fav_count > 0 ? 'hidden' : ''; ?>
				>
					<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="48" height="48" fill="none" stroke="currentColor" stroke-width="1" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
						<path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
					</svg>
					<p><?php esc_html_e( 'No favorites yet.', 'shelter-pets' ); ?></p>
					<p class="pet-favorites-modal__empty-hint"><?php esc_html_e( 'Tap the heart on any pet to save them here.', 'shelter-pets' ); ?></p>
					<a
						href="<?php echo esc_url( get_post_type_archive_link( 'vcps_pet' ) ); ?>"
						class="pet-favorites-modal__empty-cta"
						data-wp-on--click="actions.closeAndNavigate"
					>
						<?php esc_html_e( 'Browse Adoptable Pets', 'shelter-pets' ); ?>
					</a>
				</div>

				<!-- Pet cards grid (imperatively managed by callbacks.syncCards) -->
				<div
					class="pet-favorites-modal__grid"
					data-wp-watch--grid="callbacks.syncGridVisibility"
					data-wp-watch--cards="callbacks.syncCards"
					<?php echo $fav_count === 0 ? 'hidden' : ''; ?>
				>
					<?php
					// Server-rendered cards for initial paint (no directives).
					// The syncCards watch replaces these on hydration.
					foreach ( $favorite_pets as $pet ) :
						$meta_parts = array_filter( array( $pet['breed'] ?? '', $pet['age'] ?? '', $pet['sex'] ?? '' ) );
						$meta_text  = implode( ' · ', $meta_parts );
						$partners   = $pet['bonded_pair_names'] ?? array();
						?>
						<article class="pet-favorites-modal__card" data-pet-id="<?php echo esc_attr( $pet['id'] ); ?>">
							<a href="<?php echo esc_url( $pet['url'] ); ?>" class="pet-favorites-modal__card-link js-card-nav">
								<?php if ( ! empty( $pet['image'] ) ) : ?>
									<img
										src="<?php echo esc_url( $pet['image'] ); ?>"
										alt="<?php echo esc_attr( $pet['name'] ); ?>"
										class="pet-favorites-modal__card-image"
										loading="lazy"
									>
								<?php else : ?>
									<div class="pet-favorites-modal__card-placeholder">
										<?php
										Petsync_Icons::render(
											'paw',
											array(
												'width'  => 32,
												'height' => 32,
												'stroke-width' => 1,
											)
										);
										?>
									</div>
								<?php endif; ?>
							</a>
							<div class="pet-favorites-modal__card-content">
								<h3 class="pet-favorites-modal__card-name"><?php echo esc_html( $pet['name'] ); ?></h3>
								<?php if ( $meta_text ) : ?>
									<p class="pet-favorites-modal__card-meta"><?php echo esc_html( $meta_text ); ?></p>
								<?php endif; ?>
								<?php if ( ! empty( $pet['size'] ) ) : ?>
									<p class="pet-favorites-modal__card-detail"><?php echo esc_html( $pet['size'] ); ?></p>
								<?php endif; ?>
								<div class="pet-favorites-modal__card-badges">
									<?php if ( ! empty( $pet['is_new'] ) ) : ?>
										<span class="pet-favorites-modal__badge pet-favorites-modal__badge--new"><?php esc_html_e( 'New', 'shelter-pets' ); ?></span>
									<?php endif; ?>
									<?php if ( isset( $pet['special_needs'] ) && 'yes' === strtolower( (string) $pet['special_needs'] ) ) : ?>
										<span class="pet-favorites-modal__badge pet-favorites-modal__badge--special"><?php esc_html_e( 'Special Needs', 'shelter-pets' ); ?></span>
									<?php endif; ?>
									<?php if ( ! empty( $pet['is_bonded_pair'] ) && ! empty( $partners ) ) : ?>
										<span class="pet-favorites-modal__badge-popover-anchor">
											<button type="button" class="pet-favorites-modal__badge pet-favorites-modal__badge--bonded js-bonded-toggle" aria-expanded="false">
												Bonded Pair
											</button>
											<div class="pet-favorites-modal__bonded-popover" hidden role="tooltip">
												<div class="pet-favorites-modal__bonded-popover-arrow"></div>
												<p class="pet-favorites-modal__bonded-popover-label"><?php esc_html_e( 'Must adopt together with:', 'shelter-pets' ); ?></p>
												<ul class="pet-favorites-modal__bonded-popover-list">
													<?php foreach ( $partners as $partner ) : ?>
														<li>
															<?php if ( ! empty( $partner['url'] ) ) : ?>
																<a href="<?php echo esc_url( $partner['url'] ); ?>" class="pet-favorites-modal__bonded-popover-link"><?php echo esc_html( $partner['name'] ); ?></a>
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
								<div class="pet-favorites-modal__card-actions">
									<button type="button" class="pet-favorites-modal__card-unfavorite js-unfavorite" data-pet-id="<?php echo esc_attr( $pet['id'] ); ?>" data-pet-name="<?php echo esc_attr( $pet['name'] ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: pet name */ __( 'Remove %s from favorites', 'shelter-pets' ), $pet['name'] ) ); ?>">
										<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="16" height="16" fill="currentColor" stroke="none" aria-hidden="true">
											<path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
										</svg>
									</button>
								</div>
							</div>
						</article>
					<?php endforeach; ?>
				</div>
			</div>

			<!-- Footer -->
			<div
				class="pet-favorites-modal__footer"
				data-wp-watch="callbacks.syncGridVisibility"
				<?php echo $fav_count === 0 ? 'hidden' : ''; ?>
			>
				<button
					type="button"
					class="pet-favorites-modal__clear-btn"
					data-wp-on--click="actions.handleClearClick"
					data-wp-text="state.clearButtonText"
					data-wp-class--is-confirming="state.clearConfirming"
					data-default-text="<?php esc_attr_e( 'Clear all favorites', 'shelter-pets' ); ?>"
					data-confirm-text="<?php esc_attr_e( 'Tap again to confirm', 'shelter-pets' ); ?>"
				>
					<?php esc_html_e( 'Clear all favorites', 'shelter-pets' ); ?>
				</button>
			</div>
		</div>
	</div>
</div>