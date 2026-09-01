<?php
/**
 * Pet Gallery Block
 *
 * Renders a featured image hero (with badge overlays) above a thumbnail
 * grid of additional photos. The lightbox covers the gallery thumbnails.
 *
 * The featured image is the WordPress sideloaded copy (fast, local CDN).
 * Gallery images come from the Petstablished API response. Deduplication
 * by filename prevents the same photo from appearing in both places.
 *
 * Data is loaded via the Abilities API (petsync/get-pet) which
 * shares the per-request cache with other blocks on the page.
 *
 * @package ShelterKit_Pets
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$post_id   = $block->context['postId'] ?? get_the_ID();
$is_editor = defined( 'REST_REQUEST' ) && REST_REQUEST;

// Show placeholder in editor when no pet context.
if ( ! $post_id || 'vcps_pet' !== get_post_type( $post_id ) ) {
	if ( $is_editor ) {
		$wrapper_attributes = get_block_wrapper_attributes(
			array(
				'class' => 'pet-gallery pet-gallery--placeholder',
			)
		);
		?>
		<div <?php echo $wrapper_attributes; /* phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_block_wrapper_attributes() returns escaped HTML. */ ?>>
			<div class="pet-gallery__placeholder">
				<?php
				Petsync_Icons::render(
					'image-placeholder',
					array(
						'width'        => 48,
						'height'       => 48,
						'stroke-width' => 1.5,
					)
				);
				?>
				<p><?php esc_html_e( 'Pet Gallery', 'shelterkit-pets' ); ?></p>
				<small><?php esc_html_e( 'Displays pet photos with lightbox. Requires pet context.', 'shelterkit-pets' ); ?></small>
			</div>
		</div>
		<?php
	}
	return;
}

// Shared helper: Abilities API → Hydrator fallback (per-request cached).
$pet = petsync_get_pet( (int) $post_id );

if ( ! $pet ) {
	return;
}

$pet_name = $pet['name'] ?? get_the_title( $post_id );
$columns  = $attributes['columns'] ?? 3;

// === Featured image ===
// The WordPress sideloaded copy (local, CDN-friendly).
$featured_url = $pet['image'] ?? '';
$featured_alt = $pet_name;

// Get full-size featured image URL and srcset for the hero display.
$thumbnail_id = get_post_thumbnail_id( $post_id );
if ( $thumbnail_id ) {
	$featured_full = wp_get_attachment_image_url( $thumbnail_id, 'large' );
	if ( $featured_full ) {
		$featured_url = $featured_full;
	}
	$featured_alt = get_post_meta( $thumbnail_id, '_wp_attachment_image_alt', true ) ?: $pet_name;
}

// === Gallery images ===
// All images from the API response.
$all_gallery = $pet['gallery'] ?? [];

// For the visible thumbnail grid: exclude the image that matches the
// featured image so it doesn't appear twice on the page.
// WordPress sideloading preserves the original filename, so a
// case-insensitive basename comparison catches the domain mismatch.
$featured_basename = strtolower( pathinfo( $featured_url, PATHINFO_FILENAME ) );
$thumbnail_images  = array();

foreach ( $all_gallery as $img ) {
	$img_url = $img['url'] ?? '';
	if ( ! $img_url ) {
		continue;
	}
	$img_basename = strtolower( pathinfo( $img_url, PATHINFO_FILENAME ) );
	if ( $featured_basename && $img_basename === $featured_basename ) {
		continue; // Skip the duplicate in the visible grid.
	}
	$thumbnail_images[] = $img;
}

$has_featured   = ! empty( $featured_url );
// Both of these had editor controls that render.php never read, so the
// toggles moved and nothing happened. Honoured here rather than removed: a
// gallery with the thumbnail strip switched off, or with the lightbox off so
// photos are plain images, are both reasonable things to want.
$has_thumbnails = ! empty( $thumbnail_images ) && ( $attributes['showThumbnails'] ?? true );
// The lightbox gets the full gallery — all API images, no deduplication.
// This ensures every photo is navigable even though the featured image
// is visually presented separately as the hero.
$lightbox_images = array_values( array_filter( $all_gallery, fn( $img ) => ! empty( $img['url'] ) ) );

// Nothing to show.
if ( ! $has_featured && ! $has_thumbnails ) {
	if ( $is_editor ) {
		$wrapper_attributes = get_block_wrapper_attributes(
			array(
				'class' => 'pet-gallery pet-gallery--placeholder',
			)
		);
		?>
		<div <?php echo $wrapper_attributes; /* phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_block_wrapper_attributes() returns escaped HTML. */ ?>>
			<div class="pet-gallery__placeholder">
				<?php
				Petsync_Icons::render(
					'image-placeholder',
					array(
						'width'        => 48,
						'height'       => 48,
						'stroke-width' => 1.5,
					)
				);
				?>
				<p><?php esc_html_e( 'No photos available', 'shelterkit-pets' ); ?></p>
				<small><?php esc_html_e( 'Add a featured image or gallery to this pet.', 'shelterkit-pets' ); ?></small>
			</div>
		</div>
		<?php
	}
	return;
}

// === Badges ===
// Split into two groups:
// - Overlay: status badge only (stays on the featured image, top-left)
// - Below:   everything else (rendered as a pill strip beneath the image)
$overlay_badges = array();
$below_badges   = array();

$status = $pet['status'] ?? '';
if ( ( $attributes['showBadgeStatus'] ?? true ) && $status ) {
	$status_slug      = sanitize_title( $status );
	$overlay_badges[] = array(
		'label' => esc_html( $status ),
		'class' => 'pet-gallery__badge--status pet-gallery__badge--status-' . $status_slug,
	);
}

if ( ( $attributes['showBadgeNew'] ?? true ) && ! empty( $pet['is_new'] ) ) {
	$below_badges[] = array(
		'label' => __( 'New', 'shelterkit-pets' ),
		'class' => 'pet-gallery__badge--new',
	);
}

if ( ( $attributes['showBadgeBondedPair'] ?? true ) && ! empty( $pet['is_bonded_pair'] ) ) {
	$below_badges[] = array(
		'label' => __( 'Bonded Pair', 'shelterkit-pets' ),
		'class' => 'pet-gallery__badge--bonded',
	);
}

if ( ( $attributes['showBadgeSpecialNeeds'] ?? true ) && isset( $pet['has_special_needs'] ) && 'yes' === strtolower( (string) $pet['has_special_needs'] ) ) {
	$below_badges[] = array(
		'label' => __( 'Special Needs', 'shelterkit-pets' ),
		'class' => 'pet-gallery__badge--special-needs',
	);
}

// Age badge omitted from below-badges — already displayed in the
// pet-attributes block and the tagline. The showBadgeAge attribute
// remains in block.json for backward compatibility but is no longer
// rendered in the default single-pet template.

// === Interactivity context ===
// The lightbox gets the complete gallery so every image is navigable.
$has_lightbox = ! empty( $lightbox_images ) && ( $attributes['showLightbox'] ?? true );
$context      = array(
	'images'       => $lightbox_images,
	'currentIndex' => 0,
	'isOpen'       => false,
);

// Build wrapper attributes — only add Interactivity directives on the front-end.
$wrapper_attrs = array(
	'class' => 'pet-gallery',
	'style' => '--pet-gallery-columns: ' . intval( $columns ),
);

if ( ! $is_editor ) {
	$wrapper_attrs['data-wp-interactive'] = 'petsync/gallery';
	$wrapper_attrs['data-wp-context']     = wp_json_encode( $context );
}

$wrapper_attributes = get_block_wrapper_attributes( $wrapper_attrs );
?>

<div <?php echo $wrapper_attributes; /* phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_block_wrapper_attributes() returns escaped HTML. */ ?>>

	<?php if ( ! empty( $overlay_badges ) ) : ?>
		<div class="pet-gallery__status-bar">
			<?php foreach ( $overlay_badges as $badge ) : ?>
				<span class="pet-gallery__badge <?php echo esc_attr( $badge['class'] ); ?>">
					<?php echo esc_html( $badge['label'] ); ?>
				</span>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

	<?php if ( $has_featured ) : ?>
	<!-- Featured image hero — clicking opens the lightbox at index 0 -->
	<figure class="pet-gallery__featured">
		<?php if ( ! $is_editor && $has_lightbox ) : ?>
		<button
			type="button"
			class="pet-gallery__featured-trigger"
			data-wp-on--click="actions.open"
			data-index="0"
			aria-label="
			<?php
			echo esc_attr(
				sprintf(
				/* translators: %s: pet name */
					__( 'View photos of %s', 'shelterkit-pets' ),
					$pet_name
				)
			);
			?>
			"
		>
		<?php endif; ?>
			<img
				class="pet-gallery__featured-image"
				src="<?php echo esc_url( $featured_url ); ?>"
				alt="<?php echo esc_attr( $featured_alt ); ?>"
				loading="eager"
			>
		<?php if ( ! $is_editor && $has_lightbox ) : ?>
		</button>
		<?php endif; ?>
	</figure>
	<?php endif; ?>

	<?php if ( $has_thumbnails ) : ?>
	<!-- Thumbnail grid — additional photos (featured image excluded visually) -->
	<ul class="pet-gallery__grid" role="list">
		<?php
		// Map each thumbnail back to its position in the full lightbox
		// array so clicking a thumbnail opens the correct lightbox image.
		foreach ( $thumbnail_images as $thumb ) :
			$thumb_url      = $thumb['url'] ?? '';
			$lightbox_index = 0;
			foreach ( $lightbox_images as $li => $lb_img ) {
				if ( ( $lb_img['url'] ?? '' ) === $thumb_url ) {
					$lightbox_index = $li;
					break;
				}
			}
			?>
			<li class="pet-gallery__item">
				<button
					type="button"
					class="pet-gallery__trigger"
					<?php if ( ! $is_editor ) : ?>
					data-wp-on--click="actions.open"
					data-index="<?php echo esc_attr( $lightbox_index ); ?>"
					<?php endif; ?>
					aria-label="
					<?php
					echo esc_attr(
						sprintf(
						/* translators: 1: pet name, 2: image number, 3: total images */
							__( 'View %1$s photo %2$d of %3$d', 'shelterkit-pets' ),
							$pet_name,
							$lightbox_index + 1,
							count( $lightbox_images )
						)
					);
					?>
					"
				>
					<img
						src="<?php echo esc_url( $thumb_url ); ?>"
						alt="<?php echo esc_attr( $thumb['alt'] ?? $pet_name ); ?>"
						loading="lazy"
					>
				</button>
			</li>
		<?php endforeach; ?>
	</ul>
	<?php endif; ?>

	<?php if ( ! empty( $below_badges ) ) : ?>
	<div class="pet-gallery__badges-below" aria-hidden="true">
		<?php foreach ( $below_badges as $badge ) : ?>
			<span class="pet-gallery__badge <?php echo esc_attr( $badge['class'] ); ?>">
				<?php echo esc_html( $badge['label'] ); ?>
			</span>
		<?php endforeach; ?>
	</div>
	<?php endif; ?>

	<?php
	// === Videos ===
	// YouTube embeds rendered as a separate section below the thumbnail grid.
	$videos = ( $attributes['showVideos'] ?? true ) ? ( $pet['videos'] ?? [] ) : [];
	?>
	<?php if ( ! empty( $videos ) ) : ?>
	<div class="pet-gallery__videos">
		<h3 class="pet-gallery__videos-heading">
			<?php
			echo esc_html(
				sprintf(
				/* translators: %s: pet name */
					_n( 'Video of %s', 'Videos of %s', count( $videos ), 'shelterkit-pets' ),
					$pet_name
				)
			);
			?>
		</h3>
		<div class="pet-gallery__videos-grid">
			<?php foreach ( $videos as $video_id ) : ?>
				<div class="pet-gallery__video-wrapper">
					<iframe
						class="pet-gallery__video-iframe"
						src="https://www.youtube-nocookie.com/embed/<?php echo esc_attr( $video_id ); ?>"
						title="
						<?php
						echo esc_attr(
							sprintf(
							/* translators: %s: pet name */
								__( 'Video of %s', 'shelterkit-pets' ),
								$pet_name
							)
						);
						?>
						"
						frameborder="0"
						allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
						allowfullscreen
						loading="lazy"
					></iframe>
				</div>
			<?php endforeach; ?>
		</div>
	</div>
	<?php endif; ?>

	<?php if ( ! $is_editor && $has_lightbox ) : ?>
	<!--
		Lightbox — navigates the complete gallery.

		A native <dialog> opened with showModal(), which promotes it to the
		browser's top layer. That matters structurally: the top layer sits
		outside the stacking-context tree, so no ancestor can trap it. This
		element stays nested inside .pet-details__gallery-col, which is
		position:sticky and therefore always creates a stacking context — which
		is what previously left the whole page painted on top of the lightbox.

		role/aria-modal/tabindex are omitted deliberately: showModal() provides
		modal semantics, focus containment and Escape natively.
	-->
	<dialog
		class="pet-gallery__lightbox"
		data-wp-watch="callbacks.syncLightbox"
		data-wp-on--close="actions.handleDialogClose"
		data-wp-on--keydown="actions.handleKeydown"
		aria-label="<?php esc_attr_e( 'Image gallery', 'shelterkit-pets' ); ?>"
	>
		<div
			class="pet-gallery__lightbox-backdrop"
			data-wp-on--click="actions.handleBackdropClick"
		></div>

		<figure class="pet-gallery__lightbox-content">
			<img
				class="pet-gallery__lightbox-image"
				data-wp-bind--src="state.currentImageUrl"
				data-wp-bind--alt="state.currentImageAlt"
			>
			<figcaption class="screen-reader-text">
				<span data-wp-text="state.currentImageAlt"></span>
			</figcaption>
		</figure>

		<button
			type="button"
			class="pet-gallery__lightbox-nav pet-gallery__lightbox-nav--prev"
			data-wp-on--click="actions.prev"
			aria-label="<?php esc_attr_e( 'Previous image', 'shelterkit-pets' ); ?>"
		>
			<span aria-hidden="true">‹</span>
		</button>

		<button
			type="button"
			class="pet-gallery__lightbox-nav pet-gallery__lightbox-nav--next"
			data-wp-on--click="actions.next"
			aria-label="<?php esc_attr_e( 'Next image', 'shelterkit-pets' ); ?>"
		>
			<span aria-hidden="true">›</span>
		</button>

		<button
			type="button"
			class="pet-gallery__lightbox-close"
			data-wp-on--click="actions.close"
			aria-label="<?php esc_attr_e( 'Close gallery', 'shelterkit-pets' ); ?>"
		>
			<span aria-hidden="true">×</span>
		</button>

		<div class="pet-gallery__lightbox-counter" aria-live="polite">
			<span data-wp-text="state.currentNumber">1</span>
			/
			<span data-wp-text="state.totalImages"><?php echo count( $lightbox_images ); ?></span>
		</div>
	</dialog>
	<?php endif; ?>

</div>