<?php
/**
 * Pet Compare Bar Block
 *
 * Shows selected pets for comparison with actions to view, share, or clear.
 *
 * v4.2.0: attachTo router region for cross-page persistence.
 * Slots are server-rendered with current comparison state and reactively
 * updated via data-wp-bind directives keyed to global state indices.
 *
 * Architecture note: We do NOT use data-wp-each here because the
 * compareBarSlots getter is a client-only derived value that the WP 6.9
 * server-side directive processor cannot evaluate. Instead, we render 4
 * fixed slots with data-wp-bind directives that reference indexed state.
 * The global store's comparison array + pets cache drive the reactivity.
 *
 * @package Petstablished_Sync
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Don't show compare bar when viewing the comparison page itself.
if ( isset( $_GET['compare'] ) && ! empty( $_GET['compare'] ) ) {
	return;
}

$position    = $attributes['position'] ?? 'bottom';
$comparison  = Petstablished_Helpers::get_comparison();
$max_compare = 4;
$archive_url = get_post_type_archive_link( 'vcps_pet' ) ?: home_url( '/pets/' );

// Hydrate only the compared pets (not all available pets).
$compared_pets = array();
if ( $comparison ) {
	$posts = get_posts(
		array(
			'post_type'      => 'vcps_pet',
			'post_status'    => 'publish',
			'post__in'       => $comparison,
			'posts_per_page' => $max_compare,
			'orderby'        => 'post__in',
		)
	);

	foreach ( $posts as $post ) {
		$pet_data                   = array(
			'id'    => $post->ID,
			'name'  => $post->post_title,
			'image' => Petstablished_Helpers::get_image( $post->ID, 'thumbnail' ),
			'url'   => get_permalink( $post->ID ),
		);
		$compared_pets[ $post->ID ] = $pet_data;
	}
}

$context = array(
	'archiveUrl' => $archive_url,
);

// Merge compared pet data into the global interactivity state so that
// WP 6.9's server-side directive processor can resolve state.slotImage
// and state.slotName. Without this, pets not rendered elsewhere on the
// current page would have empty src/alt attributes until JS hydrates.
//
// Uses string keys so array_replace_recursive merges additively with
// pets from other blocks (slider, grid) rather than replacing them.
if ( ! empty( $compared_pets ) ) {
	$pets_for_state = array();
	foreach ( $compared_pets as $id => $pet_data ) {
		$pets_for_state[ (string) $id ] = $pet_data;
	}
	wp_interactivity_state(
		'petsync',
		array(
			'pets' => $pets_for_state,
		)
	);
}

// Router region with attachTo — injected into <body> on pages that
// include this block, removed on navigation to pages that don't.
$router_region = wp_json_encode(
	array(
		'id'       => 'pet-compare-bar',
		'attachTo' => 'body',
	)
);

$is_expanded = ! empty( $comparison ); // SSR default: expanded if has pets

$wrapper_classes = 'pet-compare-bar pet-compare-bar--' . $position;

$wrapper_attributes = get_block_wrapper_attributes(
	array(
		'class'                      => $wrapper_classes,
		'data-wp-interactive'        => 'petsync/compare-bar',
		'data-wp-router-region'      => $router_region,
		'data-wp-context'            => wp_json_encode( $context ),
		'data-wp-bind--hidden'       => 'petsync::state.isCompareBarHidden',
		'data-wp-class--is-visible'  => 'petsync::state.isCompareBarVisible',
		'data-wp-class--is-expanded' => 'petsync::state._compareBarExpanded',
		'data-wp-init'               => 'callbacks.init',
		'data-wp-watch'              => 'callbacks.watchAutoExpand',
		'role'                       => 'region',
		'aria-label'                 => __( 'Pet comparison', 'shelter-pets' ),
	)
);
?>

<aside <?php echo $wrapper_attributes; /* phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_block_wrapper_attributes() returns escaped HTML. */ ?>>
	<!-- Collapsed pill — visible when bar is collapsed -->
	<button
		type="button"
		class="pet-compare-bar__pill"
		data-wp-on--click="actions.expandBar"
		aria-label="<?php esc_attr_e( 'Show comparison bar', 'shelter-pets' ); ?>"
	>
		<?php
		Petstablished_Icons::render(
			'compare',
			array(
				'width'  => 14,
				'height' => 14,
			)
		);
		?>
		<span><?php esc_html_e( 'Compare', 'shelter-pets' ); ?></span>
		<span
			class="pet-compare-bar__pill-count"
			data-wp-text="petsync::state.comparisonCount"
		><?php echo (int) count( $comparison ); ?></span>
	</button>

	<!-- Expanded bar — the full interface -->
	<div class="pet-compare-bar__inner">
		<div class="pet-compare-bar__header">
			<span class="pet-compare-bar__label">
				<?php esc_html_e( 'Compare', 'shelter-pets' ); ?>
				(<span data-wp-text="petsync::state.comparisonCount"><?php echo (int) count( $comparison ); ?></span>/<?php echo (int) $max_compare; ?>)
			</span>
			<button
				type="button"
				class="pet-compare-bar__minimize"
				data-wp-on--click="actions.toggleBar"
				aria-label="<?php esc_attr_e( 'Minimize comparison bar', 'shelter-pets' ); ?>"
			>
				<?php
				Petstablished_Icons::render(
					'chevron-down',
					array(
						'width'  => 16,
						'height' => 16,
					)
				);
				?>
			</button>
		</div>

		<div class="pet-compare-bar__pets">
			<?php
			/**
			 * Fixed slot rendering — 4 slots, each bound to state by index.
			 *
			 * Each slot uses data-wp-bind and data-wp-class directives that
			 * reference per-index derived getters from the compare-bar store.
			 * The getters read globalState.comparison[slotIndex] and resolve
			 * against the global pets cache. This is fully reactive: adding
			 * or removing a pet from comparison updates the slots instantly.
			 */
			for ( $i = 0; $i < $max_compare; $i++ ) :
				$pet_id = $comparison[ $i ] ?? null;
				$pet    = $pet_id ? ( $compared_pets[ $pet_id ] ?? null ) : null;

				$slot_context = array(
					'slotIndex' => $i,
					'petId'     => $pet_id ?: 0,
				);
				?>
				<div
					class="pet-compare-bar__slot"
					<?php echo wp_interactivity_data_wp_context( $slot_context ); /* phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_interactivity_data_wp_context() returns an escaped attribute. */ ?>
					data-wp-class--has-pet="state.slotHasPet"
				>
					<div
						class="pet-compare-bar__pet <?php echo $pet ? 'pet-compare-bar__pet--filled' : 'pet-compare-bar__pet--empty'; ?>"
						data-wp-class--pet-compare-bar__pet--empty="!state.slotHasPet"
						data-wp-class--pet-compare-bar__pet--filled="state.slotHasPet"
						<?php
						if ( $pet ) :
							?>
							title="<?php echo esc_attr( $pet['name'] ); ?>"<?php endif; ?>
						data-wp-bind--title="state.slotName"
					>
						<?php if ( $pet ) : ?>
							<img
								src="<?php echo esc_url( $pet['image'] ); ?>"
								alt="<?php echo esc_attr( $pet['name'] ); ?>"
								class="pet-compare-bar__pet-image"
								data-wp-bind--src="state.slotImage"
								data-wp-bind--alt="state.slotName"
								data-wp-bind--hidden="!state.slotHasPet"
							>
						<?php else : ?>
							<img
								src=""
								alt=""
								class="pet-compare-bar__pet-image"
								data-wp-bind--src="state.slotImage"
								data-wp-bind--alt="state.slotName"
								data-wp-bind--hidden="!state.slotHasPet"
								hidden
							>
						<?php endif; ?>

						<button
							type="button"
							class="pet-compare-bar__remove"
							data-wp-on--click="actions.removeFromSlot"
							data-wp-bind--aria-label="state.slotRemoveLabel"
							data-wp-bind--hidden="!state.slotHasPet"
							aria-label="<?php echo $pet ? esc_attr( sprintf( /* translators: %s: pet name */ __( 'Remove %s from comparison', 'shelter-pets' ), $pet['name'] ) ) : ''; ?>"
							<?php echo $pet ? '' : 'hidden'; ?>
						>
						<?php
						Petstablished_Icons::render(
							'x',
							array(
								'width'  => 10,
								'height' => 10,
							)
						);
						?>
						</button>

						<span
							class="screen-reader-text"
							data-wp-bind--hidden="state.slotHasPet"
							<?php echo $pet ? 'hidden' : ''; ?>
						><?php esc_html_e( 'Empty slot', 'shelter-pets' ); ?></span>
					</div>
					<span
						class="pet-compare-bar__pet-name"
						data-wp-text="state.slotName"
						data-wp-bind--hidden="!state.slotHasPet"
						<?php echo $pet ? '' : 'hidden'; ?>
					><?php echo $pet ? esc_html( $pet['name'] ) : ''; ?></span>
				</div>
			<?php endfor; ?>
		</div>

		<div class="pet-compare-bar__actions">
			<button
				type="button"
				class="pet-compare-bar__btn pet-compare-bar__btn--primary"
				data-wp-on--click="actions.viewComparison"
				data-wp-bind--disabled="!state.canCompare"
			>
				<?php esc_html_e( 'Compare', 'shelter-pets' ); ?>
			</button>

			<button
				type="button"
				class="pet-compare-bar__btn pet-compare-bar__btn--secondary"
				data-wp-on--click="petsync::actions.shareComparison"
				data-wp-bind--disabled="!state.canCompare"
				title="<?php esc_attr_e( 'Copy share link', 'shelter-pets' ); ?>"
			>
				<?php
				Petstablished_Icons::render(
					'share',
					array(
						'width'  => 16,
						'height' => 16,
					)
				);
				?>
				<span class="pet-compare-bar__btn-text"><?php esc_html_e( 'Share', 'shelter-pets' ); ?></span>
			</button>

			<button
				type="button"
				class="pet-compare-bar__btn pet-compare-bar__btn--text"
				data-wp-on--click="actions.clearComparison"
			>
				<?php esc_html_e( 'Clear', 'shelter-pets' ); ?>
			</button>
		</div>
	</div>
	
	<?php // Toast notifications render via the petsync/pet-toast block (pet-floating-ui part). ?>
</aside>