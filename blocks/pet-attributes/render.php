<?php
/**
 * Pet Attributes Block — Definition list of pet characteristics
 *
 * Displays breed, age, sex, size, color, coat, coat pattern, and weight
 * in a responsive grid of label/value pairs, each with an icon.
 *
 * Values with corresponding taxonomies link to the pet archive filtered
 * by that term (e.g., clicking "Labrador" → /adopt/pets/?filter_breed=labrador).
 *
 * @package Shelter_Pets
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
				'class' => 'pet-attributes pet-attributes--placeholder',
			)
		);
		?>
		<div <?php echo $wrapper_attributes; /* phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_block_wrapper_attributes() returns escaped HTML. */ ?>>
			<div class="pet-attributes__placeholder">
				<p><?php esc_html_e( 'Pet Attributes', 'shelter-pets' ); ?></p>
				<small><?php esc_html_e( 'Breed, age, size, and more. Requires pet context.', 'shelter-pets' ); ?></small>
			</div>
		</div>
		<?php
	}
	return;
}

// Load pet data via Abilities API.
$pet = petsync_get_pet( (int) $post_id );
if ( ! $pet ) {
	return;
}

// Taxonomy key → WordPress taxonomy slug mapping (for archive links).
$taxonomy_map = Petsync_Helpers::TAXONOMIES;

// Pet archive base URL for filter links.
$archive_url = get_post_type_archive_link( 'vcps_pet' );

// Icon mapping — attribute key → icon name in Petsync_Icons.
$icon_map = array(
	'breed'        => 'paw',
	'age'          => 'clock',
	'sex'          => 'user',
	'size'         => 'maximize',
	'color'        => 'droplet',
	'coat'         => 'wind',
	'coat_pattern' => 'layers',
	'weight'       => 'scale',
);

// The filter URL parameter key for each attribute.
// Most map directly; age uses the 'age' taxonomy key but the entity
// field is 'numerical_age'. The filter param matches the taxonomy key.
$filter_key_map = array(
	'breed' => 'breed',
	'age'   => 'age',
	'sex'   => 'sex',
	'size'  => 'size',
	'color' => 'color',
	'coat'  => 'coat',
);

// Build attribute list — each entry references an entity field key.
// Only attributes whose toggle is enabled are included.
$attr_defs = array(
	array(
		'toggle' => 'showBreed',
		'label'  => __( 'Breed', 'shelter-pets' ),
		'key'    => 'breed',
	),
	array(
		'toggle'       => 'showAge',
		'label'        => __( 'Age', 'shelter-pets' ),
		'key'          => 'numerical_age',
		'fallback'     => 'age',
		'taxonomy_key' => 'age',
	),
	array(
		'toggle' => 'showSex',
		'label'  => __( 'Sex', 'shelter-pets' ),
		'key'    => 'sex',
	),
	array(
		'toggle' => 'showSize',
		'label'  => __( 'Size', 'shelter-pets' ),
		'key'    => 'size',
	),
	array(
		'toggle' => 'showColor',
		'label'  => __( 'Color', 'shelter-pets' ),
		'key'    => 'color',
	),
	array(
		'toggle' => 'showCoat',
		'label'  => __( 'Coat', 'shelter-pets' ),
		'key'    => 'coat',
	),
	array(
		'toggle' => 'showCoatPattern',
		'label'  => __( 'Coat Pattern', 'shelter-pets' ),
		'key'    => 'coat_pattern',
	),
	array(
		'toggle' => 'showWeight',
		'label'  => __( 'Weight', 'shelter-pets' ),
		'key'    => 'weight',
	),
);

$items = array();
foreach ( $attr_defs as $def ) {
	if ( ! ( $attributes[ $def['toggle'] ] ?? true ) ) {
		continue;
	}
	$value = $pet[ $def['key'] ] ?? '';
	if ( empty( $value ) && ! empty( $def['fallback'] ) ) {
		$value = $pet[ $def['fallback'] ] ?? '';
	}
	if ( empty( $value ) ) {
		continue;
	}

	// Determine the taxonomy key for this attribute (used for icon + link lookup).
	$attr_key = $def['taxonomy_key'] ?? $def['key'];

	// Build taxonomy archive link if a taxonomy exists for this attribute.
	$link          = '';
	$taxonomy_slug = $taxonomy_map[ $attr_key ] ?? '';
	$filter_key    = $filter_key_map[ $attr_key ] ?? '';

	if ( $taxonomy_slug && $filter_key && $archive_url ) {
		$terms = get_the_terms( $post_id, $taxonomy_slug );
		if ( $terms && ! is_wp_error( $terms ) ) {
			$link = add_query_arg( 'filter_' . $filter_key, $terms[0]->slug, $archive_url );
		}
	}

	$items[] = array(
		'label'    => $def['label'],
		'value'    => $value,
		'icon'     => $icon_map[ $attr_key ] ?? '',
		'link'     => $link,
		'attr_key' => $attr_key,
	);
}

if ( empty( $items ) ) {
	return;
}

$wrapper_attributes = get_block_wrapper_attributes(
	array(
		'class' => 'pet-attributes',
	)
);
?>
<dl <?php echo $wrapper_attributes; /* phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_block_wrapper_attributes() returns escaped HTML. */ ?>>
	<?php foreach ( $items as $item ) : ?>
		<div class="pet-attributes__item">
			<dt class="pet-attributes__label">
				<?php if ( $item['icon'] ) : ?>
					<?php
					Petsync_Icons::render(
						$item['icon'],
						array(
							'width'        => 14,
							'height'       => 14,
							'stroke-width' => 2,
							'class'        => 'pet-attributes__icon',
						)
					);
					?>
				<?php endif; ?>
				<?php echo esc_html( $item['label'] ); ?>
			</dt>
			<dd class="pet-attributes__value">
				<?php if ( $item['link'] ) : ?>
					<a href="<?php echo esc_url( $item['link'] ); ?>" class="pet-attributes__link">
						<?php echo esc_html( $item['value'] ); ?>
					</a>
				<?php else : ?>
					<?php echo esc_html( $item['value'] ); ?>
				<?php endif; ?>
			</dd>
		</div>
	<?php endforeach; ?>
</dl>
