<?php
/**
 * Pet Breadcrumb Block
 *
 * Renders a breadcrumb trail: Home › Adoptable Pets › Pet Name.
 * Plain HTML — no core/button wrapper, no specificity issues.
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

$pet_name      = $pet['name'] ?? get_the_title( $post_id );
$archive_url   = get_post_type_archive_link( 'vcps_pet' );
$home_url      = home_url( '/' );
$home_label    = $attributes['homeLabel'] ?? __( 'Home', 'shelter-pets' );
$archive_label = $attributes['archiveLabel'] ?? __( 'Adoptable Pets', 'shelter-pets' );
$separator     = $attributes['separator'] ?? '›';

$wrapper_attributes = get_block_wrapper_attributes(
	array(
		'class' => 'pet-details__breadcrumb',
	)
);
?>
<nav <?php echo $wrapper_attributes; /* phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_block_wrapper_attributes() returns escaped HTML. */ ?> aria-label="<?php esc_attr_e( 'Breadcrumb', 'shelter-pets' ); ?>">
	<a href="<?php echo esc_url( $home_url ); ?>" class="pet-details__breadcrumb-item">
		<?php echo esc_html( $home_label ); ?>
	</a>
	<span class="pet-details__breadcrumb-sep" aria-hidden="true"><?php echo esc_html( $separator ); ?></span>
	<a href="<?php echo esc_url( $archive_url ); ?>" class="pet-details__breadcrumb-item">
		<?php echo esc_html( $archive_label ); ?>
	</a>
	<span class="pet-details__breadcrumb-sep" aria-hidden="true"><?php echo esc_html( $separator ); ?></span>
	<span class="pet-details__breadcrumb-item pet-details__breadcrumb-current" aria-current="page">
		<?php echo esc_html( $pet_name ); ?>
	</span>
</nav>
