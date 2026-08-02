<?php
/**
 * Pet Toast Block — plugin-wide notification region
 *
 * A single fixed-position aria-live region bound to the global
 * `petsync` store's `notification` state. Every message routed through
 * the store's toast helper (favorites/comparison confirmations, sync
 * errors, "Link copied!") renders here, visibly and to screen readers.
 *
 * This block exists so notifications don't depend on any other block's
 * visibility: the compare bar previously hosted the only toast, which
 * made messages invisible whenever the bar itself was hidden.
 *
 * @package Shelter_Pets
 * @since 1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Empty and hidden until client-side state provides a message; the
// aria-live region announces the text change to screen readers, so the
// store's toast helper must not also announce() when this block exists.
$wrapper_attributes = get_block_wrapper_attributes(
	array(
		'class'                => 'pet-toast',
		'data-wp-interactive'  => 'petsync',
		'data-wp-bind--hidden' => 'state.noNotification',
		'data-wp-text'         => 'state.notification',
		'role'                 => 'status',
		'aria-live'            => 'polite',
	)
);

// `hidden` is best-effort initial state: get_block_wrapper_attributes()
// drops it from its array input, and server directive processing strips
// it again because block templates render before wp_enqueue_scripts
// registers the petsync state. The stylesheet's :not(:empty) guard is
// what actually keeps the empty region invisible before hydration; the
// hidden binding takes over afterwards.
?>
<div <?php echo $wrapper_attributes; /* phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_block_wrapper_attributes() returns escaped HTML. */ ?> hidden></div>
