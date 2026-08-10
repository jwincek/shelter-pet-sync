<?php
/**
 * Pets → Export.
 *
 * @package ShelterKit_Pets
 * @since   1.1.0
 */

declare( strict_types = 1 );

namespace Petsync\Export;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Admin {

	private const PAGE_SLUG = 'shelterkit-export';

	/**
	 * Core gates Tools → Export with this, and this is the same job.
	 */
	private const CAPABILITY = 'export';

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_page' ) );
		add_action( 'admin_post_shelterkit_export', array( $this, 'handle_download' ) );
	}

	public function register_page(): void {
		add_submenu_page(
			'edit.php?post_type=vcps_pet',
			__( 'Export Pets', 'shelterkit-pets' ),
			__( 'Export', 'shelterkit-pets' ),
			self::CAPABILITY,
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);
	}

	public function render_page(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You are not allowed to export pets.', 'shelterkit-pets' ) );
		}

		$statuses = get_terms(
			array(
				'taxonomy'   => 'pet_status',
				'hide_empty' => true,
			)
		);
		$animals  = get_terms(
			array(
				'taxonomy'   => 'pet_animal',
				'hide_empty' => true,
			)
		);
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Export Pets', 'shelterkit-pets' ); ?></h1>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="shelterkit_export" />
				<?php wp_nonce_field( 'shelterkit_export' ); ?>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'What to include', 'shelterkit-pets' ); ?></th>
						<td>
							<fieldset>
								<label>
									<input type="radio" name="mode" value="<?php echo esc_attr( Schema::PORTABLE ); ?>" checked />
									<strong><?php esc_html_e( 'Everything you can edit', 'shelterkit-pets' ); ?></strong>
								</label>
								<p class="description">
									<?php esc_html_e( 'The columns a person fills in. This file can be imported back.', 'shelterkit-pets' ); ?>
								</p>
								<br />
								<label>
									<input type="radio" name="mode" value="<?php echo esc_attr( Schema::FULL ); ?>" />
									<strong><?php esc_html_e( 'Everything on record', 'shelterkit-pets' ); ?></strong>
								</label>
								<p class="description">
									<?php esc_html_e( 'Adds photo URLs, compatibility summaries and other values the plugin works out for itself. Good for a report; cannot be imported back.', 'shelterkit-pets' ); ?>
								</p>
							</fieldset>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="skx-format"><?php esc_html_e( 'File type', 'shelterkit-pets' ); ?></label></th>
						<td>
							<select name="format" id="skx-format">
								<option value="csv"><?php esc_html_e( 'CSV — opens in Excel or Google Sheets', 'shelterkit-pets' ); ?></option>
								<option value="json"><?php esc_html_e( 'JSON — keeps photo lists intact', 'shelterkit-pets' ); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="skx-status"><?php esc_html_e( 'Status', 'shelterkit-pets' ); ?></label></th>
						<td>
							<select name="status" id="skx-status">
								<option value=""><?php esc_html_e( 'All', 'shelterkit-pets' ); ?></option>
								<?php foreach ( is_wp_error( $statuses ) ? array() : $statuses as $term ) : ?>
									<option value="<?php echo esc_attr( $term->slug ); ?>"><?php echo esc_html( $term->name ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="skx-animal"><?php esc_html_e( 'Species', 'shelterkit-pets' ); ?></label></th>
						<td>
							<select name="animal" id="skx-animal">
								<option value=""><?php esc_html_e( 'All', 'shelterkit-pets' ); ?></option>
								<?php foreach ( is_wp_error( $animals ) ? array() : $animals as $term ) : ?>
									<option value="<?php echo esc_attr( $term->slug ); ?>"><?php echo esc_html( $term->name ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="skx-provider"><?php esc_html_e( 'Source', 'shelterkit-pets' ); ?></label></th>
						<td>
							<select name="provider" id="skx-provider">
								<option value=""><?php esc_html_e( 'All', 'shelterkit-pets' ); ?></option>
								<option value="manual"><?php esc_html_e( 'Entered by hand', 'shelterkit-pets' ); ?></option>
								<option value="<?php echo esc_attr( \Petsync_Sync::PROVIDER ); ?>"><?php esc_html_e( 'Synced from the provider', 'shelterkit-pets' ); ?></option>
							</select>
						</td>
					</tr>
				</table>

				<?php submit_button( __( 'Download', 'shelterkit-pets' ) ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Build the file and send it as a download.
	 */
	public function handle_download(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You are not allowed to export pets.', 'shelterkit-pets' ) );
		}

		check_admin_referer( 'shelterkit_export' );

		$format = isset( $_POST['format'] ) && 'json' === $_POST['format'] ? 'json' : 'csv';
		$mode   = isset( $_POST['mode'] ) && Schema::FULL === $_POST['mode'] ? Schema::FULL : Schema::PORTABLE;

		$filters = array();
		foreach ( array( 'status', 'animal', 'provider' ) as $key ) {
			$value = isset( $_POST[ $key ] ) ? sanitize_key( wp_unslash( $_POST[ $key ] ) ) : '';
			if ( '' !== $value ) {
				$filters[ $key ] = $value;
			}
		}

		$ids  = Exporter::pet_ids( $filters );
		$body = 'json' === $format ? Exporter::to_json( $ids, $mode ) : Exporter::to_csv( $ids, $mode );

		$filename = sprintf(
			'shelterkit-pets-%s-%s.%s',
			$mode,
			gmdate( 'Y-m-d' ),
			$format
		);

		nocache_headers();
		header( 'Content-Type: ' . ( 'json' === $format ? 'application/json' : 'text/csv' ) . '; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Content-Length: ' . strlen( $body ) );

		echo $body; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- a CSV/JSON file body, not HTML; cells are escaped by Exporter::esc_csv_field().
		exit;
	}
}
