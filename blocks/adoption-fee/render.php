<?php
/**
 * Pet Adoption Fee Block
 *
 * Renders the adoption fee row (label + amount). Returns early with no output
 * if the pet has no adoption fee, so the row is automatically absent from the
 * published markup without any editor intervention.
 *
 * @package Petstablished_Sync
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$post_id = $block->context['postId'] ?? get_the_ID();

if ( ! $post_id || 'vcps_pet' !== get_post_type( $post_id ) ) {
	return;
}

$pet = petstablished_get_pet( (int) $post_id );
if ( ! $pet ) {
	return;
}

$fee = $pet['adoption_fee_formatted'] ?? '';
if ( ! $fee ) {
	return;
}

$wrapper_attributes = get_block_wrapper_attributes(
	array(
		'class' => 'pet-adoption-cta__fee-row',
	)
);
?>
<div <?php echo $wrapper_attributes; /* phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_block_wrapper_attributes() returns escaped HTML. */ ?>>
	<p class="pet-adoption-cta__fee-label"><?php esc_html_e( 'Adoption Fee:', 'shelter-pets' ); ?></p>
	<p class="pet-adoption-cta__fee-amount"><?php echo esc_html( $fee ); ?></p>
</div>
