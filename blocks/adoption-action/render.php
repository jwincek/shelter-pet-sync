<?php
/**
 * Pet Adoption Action Block — Button or PDF download
 *
 * Renders the adoption application action for a pet. Supports three modes:
 *   - petstablished: links to the Petstablished adoption form URL (per-pet, from API)
 *   - pdf: renders a download link for an editor-selected PDF from the media library
 *   - page: links to an editor-selected internal page (e.g. Adoption Resources)
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

$form_mode = $attributes['formMode'] ?? 'petstablished';

$has_action  = false;
$action_html = '';

if ( $form_mode === 'pdf' ) {
	$pdf_id = (int) ( $attributes['pdfAttachmentId'] ?? 0 );
	if ( $pdf_id && get_post( $pdf_id ) ) {
		$pdf_url      = wp_get_attachment_url( $pdf_id );
		$pdf_filename = basename( get_attached_file( $pdf_id ) );
		$pdf_filesize = size_format( filesize( get_attached_file( $pdf_id ) ), 1 );
		$pdf_text     = $attributes['pdfButtonText'] ?? __( 'Download Adoption Application', 'shelter-pets' );

		if ( $pdf_url ) {
			$has_action = true;
			ob_start();
			?>
			<div class="pet-adoption-cta__file">
				<a
					href="<?php echo esc_url( $pdf_url ); ?>"
					class="pet-adoption-cta__action-btn"
					download
				>
					<?php
					Petsync_Icons::render(
						'download',
						array(
							'width'  => 18,
							'height' => 18,
						)
					);
					?>
					<span><?php echo esc_html( $pdf_text ); ?></span>
				</a>
				<span class="pet-adoption-cta__file-meta">
					<?php echo esc_html( $pdf_filename ); ?>
					<span class="pet-adoption-cta__file-size">(<?php echo esc_html( $pdf_filesize ); ?>)</span>
				</span>
			</div>
			<?php
			$action_html = ob_get_clean();
		}
	}
} elseif ( $form_mode === 'page' ) {
	$page_id = (int) ( $attributes['pageId'] ?? 0 );
	$page    = $page_id ? get_post( $page_id ) : null;

	if ( $page && 'publish' === $page->post_status ) {
		$page_url  = get_permalink( $page );
		$page_text = $attributes['pageButtonText'] ?? __( 'View Adoption Resources', 'shelter-pets' );

		if ( $page_url ) {
			$has_action = true;
			ob_start();
			?>
			<a
				href="<?php echo esc_url( $page_url ); ?>"
				class="pet-adoption-cta__action-btn"
			>
				<span><?php echo esc_html( $page_text ); ?></span>
				<?php
				Petsync_Icons::render(
					'arrow-right',
					array(
						'width'  => 18,
						'height' => 18,
					)
				);
				?>
			</a>
			<?php
			$action_html = ob_get_clean();
		}
	}
} else {
	$adoption_url = $pet['adoption_form_url'] ?? '';
	$button_text  = $attributes['buttonText'] ?? __( 'Start Adoption Application', 'shelter-pets' );

	if ( $adoption_url ) {
		$has_action = true;
		ob_start();
		?>
		<a
			href="<?php echo esc_url( $adoption_url ); ?>"
			class="pet-adoption-cta__action-btn"
			target="_blank"
			rel="noopener noreferrer"
		>
			<span><?php echo esc_html( $button_text ); ?></span>
			<?php
			Petsync_Icons::render(
				'external-link',
				array(
					'width'  => 18,
					'height' => 18,
				)
			);
			?>
		</a>
		<?php
		$action_html = ob_get_clean();
	}
}

if ( ! $has_action ) {
	return;
}

$wrapper_attributes = get_block_wrapper_attributes(
	array(
		'class' => 'pet-adoption-cta__actions',
	)
);
?>
<div <?php echo $wrapper_attributes; /* phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- get_block_wrapper_attributes() returns escaped HTML. */ ?>>
	<?php echo $action_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built above with proper escaping. ?>
</div>
