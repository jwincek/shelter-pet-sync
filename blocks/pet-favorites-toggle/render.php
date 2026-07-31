<?php
/**
 * Pet Favorites Toggle Block
 *
 * A compact control that opens the favorites modal. For a richer experience,
 * use the pet-favorites-modal block instead.
 *
 * @package Petstablished_Sync
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$show_count = $attributes['showCount'] ?? true;
$favorites  = Petstablished_Helpers::get_favorites();

$wrapper_attributes = get_block_wrapper_attributes(
	array(
		'class'               => 'pet-favorites-toggle',
		'data-wp-interactive' => 'petsync',
	)
);
?>

<div <?php echo $wrapper_attributes; /* phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_block_wrapper_attributes() returns escaped HTML. */ ?>>
	<button
		type="button"
		class="pet-favorites-toggle__btn"
		data-wp-on--click="actions.openFavoritesModal"
		aria-label="<?php esc_attr_e( 'View favorites', 'shelter-pets' ); ?>"
	>
		<?php
		echo Petstablished_Icons::get_heart_interactive( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static, plugin-controlled SVG.
			array(
				'width'  => 20,
				'height' => 20,
				'class'  => 'pet-favorites-toggle__icon',
			),
			"petsync::state.favoritesCount > 0 ? 'currentColor' : 'none'"
		);
		?>

		<span class="pet-favorites-toggle__label">
			<?php esc_html_e( 'Favorites', 'shelter-pets' ); ?>
		</span>

		<?php if ( $show_count ) : ?>
			<span class="pet-favorites-toggle__count" data-wp-text="state.favoritesCount">
				<?php echo count( $favorites ); ?>
			</span>
		<?php endif; ?>
	</button>
</div>
