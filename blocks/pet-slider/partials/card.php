<?php
/**
 * Pet Slider Card Partial
 *
 * Shared card markup for cards mode and carousel mode.
 * The article wrapper, card link, image, info, and quick actions
 * are identical between modes — only the article's class and
 * data-wp directives differ.
 *
 * Expected variables (set before include):
 *   $pet                (array)  Hydrated pet entity.
 *   $index              (int)    Zero-based position in the list.
 *   $card_style         (string) 'default' | 'minimal' | 'overlay'.
 *   $show_quick_actions (bool)   Whether to render favorite/compare buttons.
 *   $show_badges       (bool)   Whether to render special needs / bonded pair badges.
 *   $badge_position    (string) 'image-top' | 'overlay-bottom' | 'above-name'.
 *   $slide_class        (string) CSS classes for the <article> wrapper.
 *   $slide_directives   (string) Extra data-wp-* attributes for the <article>.
 *
 * @package ShelterKit_Pets
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$pet_context = array(
	'petId'    => $pet['id'],
	'petName'  => $pet['name'],
	'petIndex' => $index,
);

// Compute badges for this pet.
$badges = array();
if ( ! empty( $show_badges ) ) {
	if ( ! empty( $pet['has_special_needs'] ) && strtolower( $pet['has_special_needs'] ) === 'yes' ) {
		$badges[] = array(
			'label' => __( 'Special Needs', 'shelterkit-pets' ),
			'class' => 'pet-slider__badge--special-needs',
			'icon'  => 'heart-special',
		);
	}
	if ( ! empty( $pet['is_bonded_pair'] ) ) {
		$pair_name = '';
		if ( ! empty( $pet['bonded_pair_names'][0]['name'] ) ) {
			/* translators: %s: bonded partner pet name */
			$pair_name = sprintf( __( 'Bonded with %s', 'shelterkit-pets' ), $pet['bonded_pair_names'][0]['name'] );
		} else {
			$pair_name = __( 'Bonded Pair', 'shelterkit-pets' );
		}
		$badges[] = array(
			'label' => $pair_name,
			'class' => 'pet-slider__badge--bonded-pair',
			'icon'  => 'link',
		);
	}
}
$badge_pos = $badge_position ?? 'image-top';
?>
<article
	class="<?php echo esc_attr( $slide_class ); ?>"
	<?php echo wp_interactivity_data_wp_context( $pet_context ); /* phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_interactivity_data_wp_context() returns an escaped attribute. */ ?>
	<?php echo $slide_directives ?? ''; /* phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static Interactivity directive string. */ ?>
>
	<a href="<?php echo esc_url( $pet['url'] ); ?>" class="pet-slider__card">
		<div class="pet-slider__image-wrapper">
			<?php if ( ! empty( $badges ) && $badge_pos === 'image-top' ) : ?>
				<div class="pet-slider__badges pet-slider__badges--image-top">
					<?php foreach ( $badges as $badge ) : ?>
						<span class="pet-slider__badge <?php echo esc_attr( $badge['class'] ); ?>">
							<?php
							Petsync_Icons::render(
								$badge['icon'],
								array(
									'width'  => 12,
									'height' => 12,
								)
							);
							?>
							<?php echo esc_html( $badge['label'] ); ?>
						</span>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<img
				src="<?php echo esc_url( $pet['image'] ); ?>"
				alt="<?php echo esc_attr( $pet['name'] ); ?>"
				class="pet-slider__image"
				loading="<?php echo $index === 0 ? 'eager' : 'lazy'; ?>"
			>
			<?php if ( $card_style === 'overlay' ) : ?>
				<div class="pet-slider__overlay">
					<?php if ( ! empty( $badges ) && $badge_pos === 'above-name' ) : ?>
						<div class="pet-slider__badges pet-slider__badges--above-name">
							<?php foreach ( $badges as $badge ) : ?>
								<span class="pet-slider__badge <?php echo esc_attr( $badge['class'] ); ?>">
									<?php
									Petsync_Icons::render(
										$badge['icon'],
										array(
											'width'  => 12,
											'height' => 12,
										)
									);
									?>
									<?php echo esc_html( $badge['label'] ); ?>
								</span>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
					<span class="pet-slider__name"><?php echo esc_html( $pet['name'] ); ?></span>
					<?php if ( ! empty( $pet['breed'] ) ) : ?>
						<span class="pet-slider__breed"><?php echo esc_html( $pet['breed'] ); ?></span>
					<?php endif; ?>
					<?php if ( ! empty( $badges ) && $badge_pos === 'overlay-bottom' ) : ?>
						<div class="pet-slider__badges pet-slider__badges--overlay-bottom">
							<?php foreach ( $badges as $badge ) : ?>
								<span class="pet-slider__badge <?php echo esc_attr( $badge['class'] ); ?>">
									<?php
									Petsync_Icons::render(
										$badge['icon'],
										array(
											'width'  => 12,
											'height' => 12,
										)
									);
									?>
									<?php echo esc_html( $badge['label'] ); ?>
								</span>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
				</div>
			<?php endif; ?>
		</div>

		<?php if ( $card_style !== 'overlay' ) : ?>
			<div class="pet-slider__info">
				<?php if ( ! empty( $badges ) && ( $badge_pos === 'above-name' || $badge_pos === 'overlay-bottom' ) ) : ?>
					<div class="pet-slider__badges pet-slider__badges--info">
						<?php foreach ( $badges as $badge ) : ?>
							<span class="pet-slider__badge <?php echo esc_attr( $badge['class'] ); ?>">
								<?php
								Petsync_Icons::render(
									$badge['icon'],
									array(
										'width'  => 12,
										'height' => 12,
									)
								);
								?>
								<?php echo esc_html( $badge['label'] ); ?>
							</span>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
				<h3 class="pet-slider__name"><?php echo esc_html( $pet['name'] ); ?></h3>
				<?php if ( $card_style === 'default' ) : ?>
					<p class="pet-slider__meta">
						<?php
						$meta_parts = array_filter( array( $pet['breed'] ?? '', $pet['age'] ?? '', $pet['sex'] ?? '' ) );
						echo esc_html( implode( ' · ', $meta_parts ) );
						?>
					</p>
				<?php endif; ?>
			</div>
		<?php endif; ?>
	</a>

	<?php if ( $show_quick_actions ) : ?>
		<div class="pet-slider__actions">
			<button
				type="button"
				class="pet-slider__action pet-slider__action--favorite"
				data-wp-on--click="actions.toggleFavorite"
				data-wp-bind--aria-pressed="state.isFavorited"
				data-wp-class--is-active="state.isFavorited"
				aria-label="<?php esc_attr_e( 'Add to favorites', 'shelterkit-pets' ); ?>"
			>
				<?php
				echo Petsync_Icons::get_heart_interactive( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static, plugin-controlled SVG.
					array(
						'width'  => 18,
						'height' => 18,
					)
				); /* phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static, plugin-controlled SVG. */
				?>
			</button>
			<button
				type="button"
				class="pet-slider__action pet-slider__action--compare"
				data-wp-on--click="actions.toggleComparison"
				data-wp-bind--aria-pressed="state.isInComparison"
				data-wp-class--is-active="state.isInComparison"
				data-wp-bind--disabled="state.isCompareDisabled"
				aria-label="<?php esc_attr_e( 'Add to comparison', 'shelterkit-pets' ); ?>"
			>
				<?php
				Petsync_Icons::render(
					'compare',
					array(
						'width'  => 18,
						'height' => 18,
					)
				);
				?>
			</button>
		</div>
	<?php endif; ?>
</article>