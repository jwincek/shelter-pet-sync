<?php
/**
 * Pets → Import.
 *
 * Upload, preview, then commit. The preview is not decoration: it is the only
 * point at which a shelter can notice that "Good with dogs?" landed on the
 * wrong field, and it is produced by the same code path as the write.
 *
 * @package ShelterKit_Pets
 * @since   1.1.0
 */

declare( strict_types = 1 );

namespace Petsync\Import;

use Petsync\Export\Schema;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Admin {

	private const PAGE_SLUG = 'shelterkit-import';

	/**
	 * Core gates Tools → Import with this, and this is the same job. It also
	 * keeps the capability aligned with Export, which uses `export` for the
	 * mirror-image reason.
	 */
	private const CAPABILITY = 'import';

	private const NONCE = 'shelterkit_import';

	/**
	 * Rows shown in the preview. The report holds every row; the screen does
	 * not need to render eighty tables' worth to make the point.
	 */
	private const PREVIEW_ROWS = 25;

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_page' ) );
	}

	public function register_page(): void {
		add_submenu_page(
			'edit.php?post_type=vcps_pet',
			__( 'Import Pets', 'shelterkit-pets' ),
			__( 'Import', 'shelterkit-pets' ),
			self::CAPABILITY,
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Read the uploaded CSV, or return '' when there is nothing to read.
	 *
	 * Validates with wp_check_filetype_and_ext() rather than trusting the
	 * extension, and reads from the tmp_name rather than moving the file into
	 * uploads — an import has no reason to leave a copy of the shelter's
	 * spreadsheet, containing every animal's details, sitting in a
	 * web-reachable directory.
	 */
	private function uploaded_csv(): string|\WP_Error {
		// Verified here as well as in the caller. Not belt-and-braces for its
		// own sake: it means this method cannot be reached without a valid
		// nonce however it is called later, and it is what lets the sniff see
		// what is actually true rather than being told to look away.
		check_admin_referer( self::NONCE );

		if ( empty( $_FILES['petsync_csv']['tmp_name'] ) ) {
			return '';
		}

		$file = array_map( 'sanitize_text_field', wp_unslash( (array) $_FILES['petsync_csv'] ) );

		if ( ! empty( $file['error'] ) && UPLOAD_ERR_OK !== (int) $file['error'] ) {
			return new \WP_Error( 'petsync_import_upload', __( 'The file did not upload correctly. It may be larger than this server allows.', 'shelterkit-pets' ) );
		}

		$tmp = isset( $_FILES['petsync_csv']['tmp_name'] )
			? sanitize_text_field( wp_unslash( $_FILES['petsync_csv']['tmp_name'] ) )
			: '';

		if ( '' === $tmp || ! is_uploaded_file( $tmp ) ) {
			return new \WP_Error( 'petsync_import_upload', __( 'The upload could not be verified.', 'shelterkit-pets' ) );
		}

		$checked = wp_check_filetype_and_ext( $tmp, (string) ( $file['name'] ?? '' ), array( 'csv' => 'text/csv' ) );
		if ( 'csv' !== ( $checked['ext'] ?? '' ) ) {
			return new \WP_Error( 'petsync_import_type', __( 'That file is not a CSV. Export your spreadsheet as CSV and try again.', 'shelterkit-pets' ) );
		}

		$contents = file_get_contents( $tmp ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_get_contents -- the verified upload tmp path.

		return false === $contents
			? new \WP_Error( 'petsync_import_read', __( 'The uploaded file could not be read.', 'shelterkit-pets' ) )
			: $contents;
	}

	public function render_page(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You are not allowed to import pets.', 'shelterkit-pets' ) );
		}

		$report = null;
		$error  = null;

		// Either button submits the form; only the second one writes.
		if ( ! empty( $_POST['petsync_import_submit'] ) || ! empty( $_POST['petsync_import_commit'] ) ) {
			check_admin_referer( self::NONCE );

			$csv = $this->uploaded_csv();

			if ( is_wp_error( $csv ) ) {
				$error = $csv;
			} elseif ( '' === $csv ) {
				$error = new \WP_Error( 'petsync_import_none', __( 'Choose a CSV file first.', 'shelterkit-pets' ) );
			} else {
				$report = Importer::run(
					$csv,
					array(
						// Committing requires a second, explicit click. The
						// first submission can only ever produce a preview.
						'commit'   => ! empty( $_POST['petsync_import_commit'] ),
						'on_match' => sanitize_text_field( wp_unslash( $_POST['petsync_on_match'] ?? Importer::ON_MATCH_UPDATE ) ),
					)
				);

				if ( is_wp_error( $report ) ) {
					$error  = $report;
					$report = null;
				}
			}
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Import Pets', 'shelterkit-pets' ); ?></h1>

			<p class="description">
				<?php esc_html_e( 'Upload a spreadsheet saved as CSV. Nothing is written until you review the preview and confirm.', 'shelterkit-pets' ); ?>
				<?php esc_html_e( 'Imported pets are treated as entered by hand, so a later sync from a connected platform will not touch them.', 'shelterkit-pets' ); ?>
			</p>

			<?php if ( $error ) : ?>
				<div class="notice notice-error"><p><?php echo esc_html( $error->get_error_message() ); ?></p></div>
			<?php endif; ?>

			<?php $this->render_report( $report ); ?>

			<form method="post" enctype="multipart/form-data">
				<?php wp_nonce_field( self::NONCE ); ?>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="petsync_csv"><?php esc_html_e( 'CSV file', 'shelterkit-pets' ); ?></label></th>
						<td><input type="file" name="petsync_csv" id="petsync_csv" accept=".csv,text/csv" required></td>
					</tr>
					<tr>
						<th scope="row"><label for="petsync_on_match"><?php esc_html_e( 'If a pet already exists', 'shelterkit-pets' ); ?></label></th>
						<td>
							<select name="petsync_on_match" id="petsync_on_match">
								<option value="<?php echo esc_attr( Importer::ON_MATCH_UPDATE ); ?>"><?php esc_html_e( 'Update it', 'shelterkit-pets' ); ?></option>
								<option value="<?php echo esc_attr( Importer::ON_MATCH_SKIP ); ?>"><?php esc_html_e( 'Leave it alone', 'shelterkit-pets' ); ?></option>
								<option value="<?php echo esc_attr( Importer::ON_MATCH_DUPLICATE ); ?>"><?php esc_html_e( 'Add another', 'shelterkit-pets' ); ?></option>
							</select>
							<p class="description"><?php esc_html_e( 'Pets are matched on microchip number. Rows without one are always added.', 'shelterkit-pets' ); ?></p>
						</td>
					</tr>
				</table>

				<p class="submit">
					<button type="submit" name="petsync_import_submit" value="1" class="button button-primary">
						<?php esc_html_e( 'Preview import', 'shelterkit-pets' ); ?>
					</button>
					<button type="submit" name="petsync_import_commit" value="1" class="button">
						<?php esc_html_e( 'Import now', 'shelterkit-pets' ); ?>
					</button>
				</p>
			</form>

			<h2><?php esc_html_e( 'Columns you can include', 'shelterkit-pets' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Headers are matched loosely, so "Good with dogs?" finds ok_with_dogs. Only the name column is required.', 'shelterkit-pets' ); ?>
			</p>
			<p><code><?php echo esc_html( implode( ', ', Schema::columns( Schema::PORTABLE ) ) ); ?></code></p>
		</div>
		<?php
	}

	/**
	 * @param array<string, mixed>|null $report Result of Importer::run().
	 */
	private function render_report( ?array $report ): void {
		if ( null === $report ) {
			return;
		}

		$committed = ! empty( $report['committed'] );
		?>
		<div class="notice <?php echo $report['failed'] ? 'notice-warning' : 'notice-success'; ?>">
			<p>
				<strong>
					<?php
					echo esc_html(
						$committed
							? __( 'Import complete.', 'shelterkit-pets' )
							: __( 'Preview only — nothing has been written yet.', 'shelterkit-pets' )
					);
					?>
				</strong>
				<?php
				printf(
					/* translators: 1: created count, 2: updated count, 3: skipped count, 4: failed count. */
					esc_html__( '%1$d to add, %2$d to update, %3$d skipped, %4$d with errors.', 'shelterkit-pets' ),
					(int) $report['created'],
					(int) $report['updated'],
					(int) $report['skipped'],
					(int) $report['failed']
				);
				?>
			</p>
			<?php foreach ( (array) $report['unmapped'] as $header ) : ?>
				<p>
					<?php
					printf(
						/* translators: %s: the spreadsheet column heading. */
						esc_html__( 'Column "%s" did not match any field and will be ignored.', 'shelterkit-pets' ),
						esc_html( (string) $header )
					);
					?>
				</p>
			<?php endforeach; ?>
		</div>

		<table class="widefat striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Row', 'shelterkit-pets' ); ?></th>
					<th><?php esc_html_e( 'Name', 'shelterkit-pets' ); ?></th>
					<th><?php esc_html_e( 'Action', 'shelterkit-pets' ); ?></th>
					<th><?php esc_html_e( 'Notes', 'shelterkit-pets' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( array_slice( (array) $report['rows'], 0, self::PREVIEW_ROWS ) as $row ) : ?>
				<tr>
					<td><?php echo (int) $row['line']; ?></td>
					<td><?php echo esc_html( (string) $row['name'] ); ?></td>
					<td><?php echo esc_html( (string) $row['action'] ); ?></td>
					<td><?php echo esc_html( implode( ' ', (array) $row['errors'] ) ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php
		$extra = count( (array) $report['rows'] ) - self::PREVIEW_ROWS;
		if ( $extra > 0 ) {
			printf(
				'<p class="description">%s</p>',
				esc_html(
					sprintf(
						/* translators: %d: number of rows not shown. */
						_n( '%d further row not shown.', '%d further rows not shown.', $extra, 'shelterkit-pets' ),
						$extra
					)
				)
			);
		}
	}
}
