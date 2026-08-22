<?php
/**
 * Pet Health Block — Vaccinations, spay/neuter, housebroken, special needs,
 * hypoallergenic, declawed.
 *
 * Displays tristate health/care indicators with check/x/info icons.
 * Relies on Hydrator-normalized tristate values ('yes'|'no'|'unknown'|'').
 * The displayMode attribute selects 'all', 'known' (default), or 'positive';
 * declawed is suppressed for non-cat animals regardless of toggle.
 *
 * @package ShelterKit_Pets
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
				'class' => 'pet-health pet-health--placeholder',
			)
		);
		?>
		<div <?php echo $wrapper_attributes; /* phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_block_wrapper_attributes() returns escaped HTML. */ ?>>
			<div class="pet-health__placeholder">
				<p><?php esc_html_e( 'Pet Health', 'shelterkit-pets' ); ?></p>
				<small><?php esc_html_e( 'Vaccinations, spay/neuter, and more. Requires pet context.', 'shelterkit-pets' ); ?></small>
			</div>
		</div>
		<?php
	}
	return;
}

// Shared helper: Abilities API → Hydrator fallback.
$pet = petsync_get_pet( (int) $post_id );
if ( ! $pet ) {
	return;
}

// Display mode: 'all' | 'known' (default) | 'positive'.
$display_mode = $attributes['displayMode'] ?? 'known';

// Determine animal type for conditional fields.
$animal_slug = strtolower( $pet['animalSlug'] ?? $pet['animal'] ?? '' );
$is_cat      = ( $animal_slug === 'cat' );

// Health field definitions.
// Each entry maps a block toggle attribute to an entity field key.
// 'cat_only' fields are suppressed for non-cat animals.
$health_defs = array(
	array(
		'toggle' => 'showSpayedNeutered',
		'label'  => __( 'Spayed/Neutered', 'shelterkit-pets' ),
		'key'    => 'spayed_neutered',
	),
	array(
		'toggle' => 'showVaccinations',
		'label'  => __( 'Vaccinations Current', 'shelterkit-pets' ),
		'key'    => 'shots_current',
	),
	array(
		'toggle' => 'showHousebroken',
		'label'  => __( 'House Trained', 'shelterkit-pets' ),
		'key'    => 'housebroken',
	),
	array(
		'toggle' => 'showSpecialNeeds',
		'label'  => __( 'Special Needs', 'shelterkit-pets' ),
		'key'    => 'has_special_needs',
	),
	array(
		'toggle' => 'showHypoallergenic',
		'label'  => __( 'Hypoallergenic', 'shelterkit-pets' ),
		'key'    => 'hypoallergenic',
	),
	array(
		'toggle'   => 'showDeclawed',
		'label'    => __( 'Declawed', 'shelterkit-pets' ),
		'key'      => 'declawed',
		'cat_only' => true,
	),
);

$items = array();
foreach ( $health_defs as $def ) {
	// Respect the editor toggle.
	if ( ! ( $attributes[ $def['toggle'] ] ?? true ) ) {
		continue;
	}

	// Skip cat-only fields for non-cat animals.
	if ( ! empty( $def['cat_only'] ) && ! $is_cat ) {
		continue;
	}

	// Tristate values are pre-normalized by the Hydrator to
	// 'yes', 'no', 'unknown', or '' (no data).
	$status = $pet[ $def['key'] ] ?? '';

	// No data recorded — always skip.
	if ( $status === '' ) {
		continue;
	}

	// Apply display mode filter.
	if ( $display_mode === 'positive' && $status !== 'yes' ) {
		continue;
	}
	if ( $display_mode === 'known' && $status === 'unknown' ) {
		continue;
	}

	// For special needs, append detail text when positive.
	$label = $def['label'];
	if ( $def['key'] === 'has_special_needs' && $status === 'yes' ) {
		$detail = trim( $pet['special_needs_detail'] ?? '' );
		if ( $detail ) {
			$label = sprintf(
				/* translators: 1: "Special Needs" label, 2: detail text (e.g. "FeLV+") */
				__( '%1$s: %2$s', 'shelterkit-pets' ),
				$label,
				$detail
			);
		}
	}

	$items[] = array(
		'label'  => $label,
		'status' => $status,
	);
}

if ( empty( $items ) ) {
	return;
}

// Status icons — filled circle variants for health indicators.
$status_icons = array(
	'yes'     => array(
		'name'  => 'check-circle',
		'attrs' => array(
			'width'        => 18,
			'height'       => 18,
			'stroke-width' => 2.5,
		),
	),
	'no'      => array(
		'name'  => 'x-circle',
		'attrs' => array(
			'width'        => 18,
			'height'       => 18,
			'stroke-width' => 2.5,
		),
	),
	'unknown' => array(
		'name'  => 'help-circle',
		'attrs' => array(
			'width'        => 18,
			'height'       => 18,
			'stroke-width' => 2.5,
		),
	),
);

$status_labels = array(
	'yes'     => __( 'Yes', 'shelterkit-pets' ),
	'no'      => __( 'No', 'shelterkit-pets' ),
	'unknown' => __( 'Unknown', 'shelterkit-pets' ),
);

$wrapper_attributes = get_block_wrapper_attributes(
	array(
		'class' => 'pet-health',
	)
);
?>
<ul <?php echo $wrapper_attributes; /* phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_block_wrapper_attributes() returns escaped HTML. */ ?> role="list">
	<?php foreach ( $items as $item ) : ?>
		<li
			class="pet-health__item pet-health__item--<?php echo esc_attr( $item['status'] ); ?>"
			aria-label="<?php echo esc_attr( $item['label'] . ': ' . $status_labels[ $item['status'] ] ); ?>"
		>
			<span class="pet-health__icon" aria-hidden="true">
				<?php
				$icon = $status_icons[ $item['status'] ];
				Petsync_Icons::render( $icon['name'], $icon['attrs'] );
				?>
			</span>
			<span class="pet-health__label"><?php echo esc_html( $item['label'] ); ?></span>
			<?php
			/*
			 * The status word is aria-hidden — the value is already on the <li>
			 * as an aria-label, so assistive technology reads it either way and
			 * turning this off costs nothing there. It is a content choice, not
			 * a presentation one, which is why it is an attribute rather than a
			 * CSS rule: a kennel card printed at four to a sheet can rely on the
			 * icon, while the pet's own page has room for the word.
			 */
			?>
			<?php if ( ! empty( $attributes['showStatusText'] ) ) : ?>
				<span class="pet-health__status" aria-hidden="true">
					<?php echo esc_html( $status_labels[ $item['status'] ] ); ?>
				</span>
			<?php endif; ?>
		</li>
	<?php endforeach; ?>
</ul>
