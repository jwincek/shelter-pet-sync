<?php
/**
 * Pet Tagline Block — Quick-facts summary with taxonomy filter links
 *
 * Renders a horizontal list of the pet's key taxonomy terms (animal, breed,
 * age, sex, size) separated by a configurable delimiter. Each term links to
 * the pet archive filtered by that value, matching the filter URL pattern
 * used by the pet-filters and pet-listing-grid blocks.
 *
 * @package Shelter_Pets
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$post_id = $block->context['postId'] ?? get_the_ID();

if ( ! $post_id || 'vcps_pet' !== get_post_type( $post_id ) ) {
	return;
}

$pet = petsync_get_pet( (int) $post_id );
if ( ! $pet ) {
	return;
}

$archive_url = get_post_type_archive_link( 'vcps_pet' );
$separator   = $attributes['separator'] ?? ' · ';

// Tagline fields — ordered by what adopters scan first.
// Each maps to a taxonomy for the filter link.
$tagline_fields = array(
	array(
		'key'      => 'animal',
		'taxonomy' => 'pet_animal',
		'filter'   => 'filter_animal',
	),
	array(
		'key'      => 'breed',
		'taxonomy' => 'pet_breed',
		'filter'   => 'filter_breed',
	),
	array(
		'key'      => 'age',
		'taxonomy' => 'pet_age',
		'filter'   => 'filter_age',
	),
	array(
		'key'      => 'sex',
		'taxonomy' => 'pet_sex',
		'filter'   => 'filter_sex',
	),
	array(
		'key'      => 'size',
		'taxonomy' => 'pet_size',
		'filter'   => 'filter_size',
	),
);

$items = array();
foreach ( $tagline_fields as $field ) {
	$value = $pet[ $field['key'] ] ?? '';
	if ( empty( $value ) ) {
		continue;
	}

	$link = '';
	if ( $archive_url && $field['taxonomy'] ) {
		$terms = get_the_terms( $post_id, $field['taxonomy'] );
		if ( $terms && ! is_wp_error( $terms ) ) {
			$link = add_query_arg( $field['filter'], $terms[0]->slug, $archive_url );
		}
	}

	$items[] = array(
		'label' => $value,
		'link'  => $link,
	);
}

if ( empty( $items ) ) {
	return;
}

$wrapper_attributes = get_block_wrapper_attributes(
	array(
		'class' => 'pet-details__tagline',
	)
);
?>
<p <?php echo $wrapper_attributes; /* phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_block_wrapper_attributes() returns escaped HTML. */ ?>>
	<?php
	$parts = array();
	foreach ( $items as $item ) {
		if ( $item['link'] ) {
			$parts[] = '<a href="' . esc_url( $item['link'] ) . '" class="pet-details__tagline-link">' . esc_html( $item['label'] ) . '</a>';
		} else {
			$parts[] = esc_html( $item['label'] );
		}
	}
	echo implode( '<span class="pet-details__tagline-sep" aria-hidden="true">' . esc_html( $separator ) . '</span>', $parts ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built with proper escaping above.
	?>
</p>
