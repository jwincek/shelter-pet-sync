<?php
/**
 * Pet Compatibility Block — Good with dogs, cats, children
 *
 * Displays tristate compatibility indicators (yes/no/unknown) in a card
 * grid matching the pet-attributes visual treatment. Positive values link
 * to the pet archive filtered by that compatibility trait.
 *
 * @package Petstablished_Sync
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
				'class' => 'pet-compat pet-compat--placeholder',
			)
		);
		?>
		<div <?php echo $wrapper_attributes; /* phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_block_wrapper_attributes() returns escaped HTML. */ ?>>
			<div class="pet-compat__placeholder">
				<p><?php esc_html_e( 'Pet Compatibility', 'shelter-pets' ); ?></p>
				<small><?php esc_html_e( 'Good with dogs, cats, and children. Requires pet context.', 'shelter-pets' ); ?></small>
			</div>
		</div>
		<?php
	}
	return;
}

// Shared helper: Abilities API → Hydrator fallback.
$pet = petstablished_get_pet( (int) $post_id );
if ( ! $pet ) {
	return;
}

$archive_url = get_post_type_archive_link( 'vcps_pet' );

// Build compatibility list — each entry has an entity field key and a
// compat_* URL filter parameter for archive links.
$compat_defs = array(
	array(
		'toggle' => 'showDogs',
		'label'  => __( 'Dogs', 'shelter-pets' ),
		'key'    => 'ok_with_dogs',
		'icon'   => 'dog',
		'filter' => 'compat_goodWithDogs',
	),
	array(
		'toggle' => 'showCats',
		'label'  => __( 'Cats', 'shelter-pets' ),
		'key'    => 'ok_with_cats',
		'icon'   => 'cat',
		'filter' => 'compat_goodWithCats',
	),
	array(
		'toggle' => 'showKids',
		'label'  => __( 'Children', 'shelter-pets' ),
		'key'    => 'ok_with_kids',
		'icon'   => 'child',
		'filter' => 'compat_goodWithKids',
	),
);

$items = array();
foreach ( $compat_defs as $def ) {
	if ( ! ( $attributes[ $def['toggle'] ] ?? true ) ) {
		continue;
	}
	// Tristate values are pre-normalized by the Hydrator to
	// 'yes', 'no', 'unknown', or '' (no data).
	$status = $pet[ $def['key'] ] ?? '';
	if ( $status === '' ) {
		continue; // No data for this field.
	}

	// "Yes" items link to the archive filtered by this compat trait.
	$link = '';
	if ( $status === 'yes' && $archive_url ) {
		$link = add_query_arg( $def['filter'], '1', $archive_url );
	}

	$items[] = array(
		'label'  => $def['label'],
		'icon'   => $def['icon'],
		'status' => $status,
		'link'   => $link,
	);
}

if ( empty( $items ) ) {
	return;
}

// Status labels for screen readers and the visible status text.
$status_labels = array(
	'yes'     => __( 'Yes', 'shelter-pets' ),
	'no'      => __( 'No', 'shelter-pets' ),
	'unknown' => __( 'Ask us', 'shelter-pets' ),
);

// Status icons.
$status_icons = array(
	'yes'     => array(
		'name'  => 'check',
		'attrs' => array(
			'width'        => 16,
			'height'       => 16,
			'stroke-width' => 3,
		),
	),
	'no'      => array(
		'name'  => 'x',
		'attrs' => array(
			'width'        => 16,
			'height'       => 16,
			'stroke-width' => 3,
		),
	),
	'unknown' => array(
		'name'  => 'minus',
		'attrs' => array(
			'width'        => 16,
			'height'       => 16,
			'stroke-width' => 3,
		),
	),
);

$display_style = $attributes['displayStyle'] ?? 'cards';

// Heading auto-switch: use the positive heading ("Plays nicely with") when
// every visible item is "yes". Fall back to the neutral heading ("Good with")
// if any item is "no" or "unknown".
$all_positive     = ! array_filter( $items, fn( $i ) => $i['status'] !== 'yes' );
$positive_heading = $attributes['positiveHeadingText'] ?? __( 'Plays nicely with', 'shelter-pets' );
$neutral_heading  = $attributes['headingText'] ?? __( 'Good with', 'shelter-pets' );
$heading_text     = $all_positive ? $positive_heading : $neutral_heading;

$wrapper_attributes = get_block_wrapper_attributes(
	array(
		'class' => 'pet-compat pet-compat--' . sanitize_html_class( $display_style ),
	)
);
?>
<div <?php echo $wrapper_attributes; /* phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_block_wrapper_attributes() returns escaped HTML. */ ?>>
	<p class="pet-compat__heading"><?php echo esc_html( $heading_text ); ?></p>
	<ul class="pet-compat__list" role="list">
	<?php
	foreach ( $items as $item ) :
		$icon_data   = $status_icons[ $item['status'] ];
		$status_text = $status_labels[ $item['status'] ];
		?>
		<li class="pet-compat__item pet-compat__item--<?php echo esc_attr( $item['status'] ); ?>">
			<?php if ( $item['link'] ) : ?>
			<a href="<?php echo esc_url( $item['link'] ); ?>" class="pet-compat__link">
			<?php endif; ?>
				<span class="pet-compat__icon" aria-hidden="true">
					<?php
					Petstablished_Icons::render(
						$item['icon'],
						array(
							'width'  => 20,
							'height' => 20,
						)
					);
					?>
				</span>
				<span class="pet-compat__label"><?php echo esc_html( $item['label'] ); ?></span>
				<span class="pet-compat__status-badge">
					<?php Petstablished_Icons::render( $icon_data['name'], $icon_data['attrs'] ); ?>
					<span class="pet-compat__status-text"><?php echo esc_html( $status_text ); ?></span>
				</span>
			<?php if ( $item['link'] ) : ?>
			</a>
			<?php endif; ?>
		</li>
	<?php endforeach; ?>
	</ul>
</div>
