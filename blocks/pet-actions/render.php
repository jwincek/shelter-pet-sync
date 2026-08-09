<?php
/**
 * Pet Actions Block — Favorite, Compare, Share
 *
 * Mobile-first design:
 *   - Mobile: always icon-only (compact bar)
 *   - Desktop: controlled by labelDisplay attribute (icon-and-text, icon-only, text-only)
 *
 * Share button opens a dropdown with:
 *   - Native share (Web Share API, mobile/supported browsers)
 *   - Copy link to clipboard
 *   - Direct social links (Facebook, X/Twitter, Email)
 *
 * @package ShelterKit_Pets
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$post_id   = $block->context['postId'] ?? get_the_ID();
$is_editor = defined( 'REST_REQUEST' ) && REST_REQUEST;

if ( ! $post_id || 'vcps_pet' !== get_post_type( $post_id ) ) {
	if ( $is_editor ) {
		$wrapper_attributes = get_block_wrapper_attributes(
			array(
				'class' => 'pet-actions pet-actions--placeholder',
			)
		);
		?>
		<div <?php echo $wrapper_attributes; /* phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_block_wrapper_attributes() returns escaped HTML. */ ?>>
			<div class="pet-actions__placeholder">
				<?php
				Petsync_Icons::render(
					'heart',
					array(
						'width'        => 24,
						'height'       => 24,
						'stroke-width' => 1.5,
					)
				);
				?>
				<p><?php esc_html_e( 'Pet Actions', 'shelterkit-pets' ); ?></p>
				<small><?php esc_html_e( 'Favorite, compare, and share buttons. Requires pet context.', 'shelterkit-pets' ); ?></small>
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

$pet_name      = $pet['name'] ?? get_the_title( $post_id );
$pet_url       = $pet['url'] ?? get_permalink( $post_id );
$is_favorited  = $pet['favorited'] ?? false;
$label_display = $attributes['labelDisplay'] ?? 'icon-and-text';

$show_favorite = $attributes['showFavorite'] ?? true;
$show_compare  = $attributes['showCompare'] ?? true;
$show_share    = $attributes['showShare'] ?? true;

if ( ! $show_favorite && ! $show_compare && ! $show_share ) {
	return;
}

// Share URLs for social links.
$share_url   = rawurlencode( $pet_url );
$share_title = rawurlencode(
	sprintf(
	/* translators: %s: pet name */
		__( 'Meet %s — Available for Adoption', 'shelterkit-pets' ),
		$pet_name
	)
);
$share_links = array(
	'facebook' => 'https://www.facebook.com/sharer/sharer.php?u=' . $share_url,
	'x'        => 'https://x.com/intent/tweet?url=' . $share_url . '&text=' . $share_title,
	'email'    => 'mailto:?subject=' . $share_title . '&body=' . $share_url,
);

// Display mode: "inline" (segmented bar) or "overlay" (floating icons on gallery).
$display_mode = $attributes['displayMode'] ?? 'inline';

// Label display CSS modifier.
// Mobile always shows icon-only regardless of attribute (handled via CSS).
// Overlay mode always uses icon-only (handled via CSS).
$display_class = 'pet-actions--display-' . $label_display;
$mode_class    = 'pet-actions--' . $display_mode;

$wrapper_attributes = get_block_wrapper_attributes(
	array(
		'class'               => 'pet-actions ' . $display_class . ' ' . $mode_class,
		'data-wp-interactive' => 'petsync',
	)
);
?>
<div <?php echo $wrapper_attributes; /* phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_block_wrapper_attributes() returns escaped HTML. */ ?>>

	<?php if ( $show_favorite ) : ?>
	<button
		type="button"
		class="pet-actions__button pet-actions__button--favorite<?php echo $is_favorited ? ' is-active' : ''; ?>"
		data-wp-on--click="actions.toggleFavorite"
		data-wp-bind--aria-pressed="state.isFavorited"
		data-wp-bind--aria-label="state.favoriteLabel"
		data-wp-class--is-active="state.isFavorited"
		aria-pressed="<?php echo $is_favorited ? 'true' : 'false'; ?>"
		aria-label="
		<?php
		echo esc_attr(
			sprintf(
				$is_favorited
				? /* translators: %s: pet name */ __( 'Unfavorite %s', 'shelterkit-pets' )
				: /* translators: %s: pet name */ __( 'Favorite %s', 'shelterkit-pets' ),
				$pet_name
			)
		);
		?>
		"
	>
		<?php echo Petsync_Icons::get_heart_interactive(); /* phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static, plugin-controlled SVG. */ ?>
		<span class="pet-actions__label" data-wp-text="state.favoriteButtonText">
			<?php
			echo $is_favorited
				? esc_html__( 'Unfavorite', 'shelterkit-pets' )
				: esc_html__( 'Favorite', 'shelterkit-pets' );
			?>
		</span>
	</button>
	<?php endif; ?>

	<?php if ( $show_compare ) : ?>
	<button
		type="button"
		class="pet-actions__button pet-actions__button--compare"
		data-wp-on--click="actions.toggleComparison"
		data-wp-bind--aria-pressed="state.isInComparison"
		data-wp-bind--aria-label="state.compareLabel"
		data-wp-bind--disabled="state.isCompareDisabled"
		data-wp-class--is-active="state.isInComparison"
		aria-pressed="false"
		aria-label="
		<?php
		echo esc_attr(
			sprintf(
			/* translators: %s: pet name */
				__( 'Add %s to comparison', 'shelterkit-pets' ),
				$pet_name
			)
		);
		?>
		"
	>
		<?php
		Petsync_Icons::render(
			'compare',
			array(
				'width'  => 20,
				'height' => 20,
			)
		);
		?>
		<span class="pet-actions__label" data-wp-text="state.compareButtonText">
			<?php esc_html_e( 'Compare', 'shelterkit-pets' ); ?>
		</span>
	</button>
	<?php endif; ?>

	<?php if ( $show_share ) : ?>
	<div class="pet-actions__share-wrapper">
		<button
			type="button"
			class="pet-actions__button pet-actions__button--share"
			data-wp-on--click="actions.toggleShareMenu"
			data-wp-bind--aria-expanded="state.isShareMenuOpen"
			aria-expanded="false"
			aria-haspopup="true"
			aria-label="
			<?php
			echo esc_attr(
				sprintf(
				/* translators: %s: pet name */
					__( 'Share %s', 'shelterkit-pets' ),
					$pet_name
				)
			);
			?>
			"
		>
			<?php
			Petsync_Icons::render(
				'share',
				array(
					'width'  => 20,
					'height' => 20,
				)
			);
			?>
			<span class="pet-actions__label"><?php esc_html_e( 'Share', 'shelterkit-pets' ); ?></span>
		</button>

		<div
			class="pet-actions__share-dropdown"
			data-wp-bind--hidden="!state.isShareMenuOpen"
			data-wp-on-document--click="actions.closeShareMenuOnOutsideClick"
			data-wp-on-document--keydown="actions.closeShareMenuOnEscape"
			role="menu"
			aria-label="<?php esc_attr_e( 'Share options', 'shelterkit-pets' ); ?>"
			hidden
		>
			<!-- Native share (hidden if Web Share API unavailable — handled via CSS/JS) -->
			<button
				type="button"
				class="pet-actions__share-item pet-actions__share-item--native"
				data-wp-on--click="actions.nativeShare"
				data-wp-bind--hidden="!state.hasNativeShare"
				role="menuitem"
				hidden
			>
				<?php
				Petsync_Icons::render(
					'share',
					array(
						'width'  => 18,
						'height' => 18,
					)
				);
				?>
				<span><?php esc_html_e( 'Share via…', 'shelterkit-pets' ); ?></span>
			</button>

			<!-- Copy link -->
			<button
				type="button"
				class="pet-actions__share-item pet-actions__share-item--copy"
				data-wp-on--click="actions.copyPetLink"
				data-wp-class--is-copied="state.isLinkCopied"
				role="menuitem"
			>
				<?php
				Petsync_Icons::render(
					'link',
					array(
						'width'  => 18,
						'height' => 18,
					)
				);
				?>
				<span data-wp-text="state.copyButtonText">
					<?php esc_html_e( 'Copy link', 'shelterkit-pets' ); ?>
				</span>
			</button>

			<hr class="pet-actions__share-divider" role="separator">

			<!-- Social links -->
			<a
				href="<?php echo esc_url( $share_links['facebook'] ); ?>"
				class="pet-actions__share-item pet-actions__share-item--facebook"
				target="_blank"
				rel="noopener noreferrer"
				role="menuitem"
			>
				<?php
				Petsync_Icons::render(
					'facebook',
					array(
						'width'  => 18,
						'height' => 18,
					)
				);
				?>
				<span>Facebook</span>
			</a>

			<a
				href="<?php echo esc_url( $share_links['x'] ); ?>"
				class="pet-actions__share-item pet-actions__share-item--x"
				target="_blank"
				rel="noopener noreferrer"
				role="menuitem"
			>
				<?php
				Petsync_Icons::render(
					'x-twitter',
					array(
						'width'  => 18,
						'height' => 18,
					)
				);
				?>
				<span>X</span>
			</a>

			<a
				href="<?php echo esc_url( $share_links['email'] ); ?>"
				class="pet-actions__share-item pet-actions__share-item--email"
				role="menuitem"
			>
				<?php
				Petsync_Icons::render(
					'mail',
					array(
						'width'  => 18,
						'height' => 18,
					)
				);
				?>
				<span><?php esc_html_e( 'Email', 'shelterkit-pets' ); ?></span>
			</a>
		</div>
	</div>
	<?php endif; ?>

</div>