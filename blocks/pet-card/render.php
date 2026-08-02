<?php
/**
 * Pet Card Block - Server-side render
 *
 * @package Shelter_Pets
 * @since 1.0.0
 *
 * @var array    $attributes Block attributes.
 * @var string   $content    Block content.
 * @var WP_Block $block      Block instance.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$post_id = $block->context['postId'] ?? get_the_ID();

if ( ! $post_id || 'vcps_pet' !== get_post_type( $post_id ) ) {
	return;
}

$pet     = null;
$ability = function_exists( 'wp_get_ability' ) ? wp_get_ability( 'petsync/get-pet' ) : null;
if ( $ability ) {
	$result = $ability->execute( [ 'id' => (int) $post_id ] );
	if ( ! is_wp_error( $result ) ) {
		$pet = $result;
	}
} else {
	$pet = \Petsync\Core\Pet_Hydrator::get( $post_id );
}

$show_favorite = $attributes['showFavorite'] ?? true;
$show_compare  = $attributes['showCompare'] ?? true;
$show_status   = $attributes['showStatus'] ?? true;

$context = wp_json_encode(
	array(
		'petId'   => $pet['id'],
		'petName' => $pet['name'],
	)
);

$wrapper_attributes = get_block_wrapper_attributes(
	array(
		'class'               => 'pet-card',
		'data-wp-interactive' => 'petsync',
		'data-wp-context'     => $context,
	)
);
?>

<article <?php echo $wrapper_attributes; /* phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_block_wrapper_attributes() returns escaped HTML. */ ?>>
	<a href="<?php echo esc_url( $pet['url'] ); ?>" class="pet-card__link">
		<?php if ( $pet['image'] ) : ?>
			<img 
				src="<?php echo esc_url( $pet['image'] ); ?>" 
				alt="<?php echo esc_attr( $pet['name'] ); ?>"
				class="pet-card__image"
				loading="lazy"
			>
		<?php endif; ?>

		<?php if ( $show_status && $pet['status'] ) : ?>
			<span class="pet-card__status pet-card__status--<?php echo esc_attr( sanitize_title( $pet['status'] ) ); ?>">
				<?php echo esc_html( $pet['status'] ); ?>
			</span>
		<?php endif; ?>
	</a>

	<div class="pet-card__content">
		<h3 class="pet-card__name">
			<a href="<?php echo esc_url( $pet['url'] ); ?>">
				<?php echo esc_html( $pet['name'] ); ?>
			</a>
		</h3>

		<p class="pet-card__meta">
			<?php
			$meta_parts = array_filter( array( $pet['breed'], $pet['age'], $pet['sex'] ) );
			echo esc_html( implode( ' · ', $meta_parts ) );
			?>
		</p>

		<div class="pet-card__actions">
			<?php if ( $show_favorite ) : ?>
				<button
					type="button"
					class="pet-favorite-btn"
					data-wp-on--click="actions.toggleFavorite"
					data-wp-bind--aria-pressed="state.isFavorited"
					data-wp-bind--aria-label="state.favoriteLabel"
				>
					<?php echo Petsync_Icons::get_heart_interactive(); /* phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static, plugin-controlled SVG. */ ?>
					<span class="screen-reader-text"><?php esc_html_e( 'Favorite', 'shelter-pets' ); ?></span>
				</button>
			<?php endif; ?>

			<?php if ( $show_compare ) : ?>
				<button
					type="button"
					class="pet-compare-btn"
					data-wp-on--click="actions.toggleComparison"
					data-wp-bind--aria-pressed="state.isInComparison"
					data-wp-bind--aria-label="state.compareLabel"
				>
					<?php
					Petsync_Icons::render(
						'compare-grid',
						array(
							'width'  => 16,
							'height' => 16,
						)
					);
					?>
					<span><?php esc_html_e( 'Compare', 'shelter-pets' ); ?></span>
				</button>
			<?php endif; ?>
		</div>
	</div>
</article>
