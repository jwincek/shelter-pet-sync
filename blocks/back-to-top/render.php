<?php
/**
 * Back to Top Block
 *
 * Floating button that appears after scrolling past a threshold.
 * Works without JS as a plain anchor link; enhanced with smooth
 * scroll and show/hide via the Interactivity API.
 *
 * @package Petstablished_Sync
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$position  = $attributes['position'] ?? 'bottom-left';
$threshold = $attributes['threshold'] ?? 400;

$wrapper_attributes = get_block_wrapper_attributes(
	array(
		'class'                     => 'back-to-top back-to-top--' . $position,
		'data-wp-interactive'       => 'petsync/back-to-top',
		'data-wp-context'           => wp_json_encode( array( 'threshold' => $threshold ) ),
		'data-wp-class--is-visible' => 'state.isVisible',
		'data-wp-init'              => 'callbacks.init',
	)
);
?>
<div <?php echo $wrapper_attributes; /* phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_block_wrapper_attributes() returns escaped HTML. */ ?>>
	<a
		href="#"
		class="back-to-top__button"
		data-wp-on--click="actions.scrollToTop"
		aria-label="<?php esc_attr_e( 'Back to top', 'shelter-pets' ); ?>"
	>
		<?php
		Petstablished_Icons::render(
			'chevron-up',
			array(
				'width'  => 20,
				'height' => 20,
			)
		);
		?>
	</a>
</div>
