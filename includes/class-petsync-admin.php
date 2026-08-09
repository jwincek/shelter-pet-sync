<?php
/**
 * ShelterKit Pets Admin - Settings & Sync UI
 *
 * @package ShelterKit_Pets
 * @since 1.0.0
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Petsync_Admin {

	public const OPTION_NAME   = 'petsync_settings';
	public const PAGE_SLUG     = 'shelterkit-pets';
	public const LOG_PAGE_SLUG = 'shelterkit-pets-log';

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_notices', array( $this, 'display_notices' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( PETSYNC_FILE ), array( $this, 'add_settings_link' ) );
	}

	public function add_menu(): void {
		add_submenu_page(
			'edit.php?post_type=vcps_pet',
			__( 'Petstablished Sync', 'shelterkit-pets' ),
			__( 'Sync Settings', 'shelterkit-pets' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_settings_page' )
		);

		add_submenu_page(
			'edit.php?post_type=vcps_pet',
			__( 'Petstablished Sync Log', 'shelterkit-pets' ),
			__( 'Sync Log', 'shelterkit-pets' ),
			'manage_options',
			self::LOG_PAGE_SLUG,
			array( $this, 'render_sync_log_page' )
		);
	}

	public function register_settings(): void {
		register_setting(
			self::PAGE_SLUG,
			self::OPTION_NAME,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
				'default'           => self::get_defaults(),
			)
		);

		// API Settings Section.
		add_settings_section(
			'api_settings',
			__( 'API Configuration', 'shelterkit-pets' ),
			fn() => printf( '<p>%s</p>', esc_html__( 'Connect to your Petstablished account.', 'shelterkit-pets' ) ),
			self::PAGE_SLUG
		);

		add_settings_field( 'public_key', __( 'Public Key', 'shelterkit-pets' ), array( $this, 'render_public_key_field' ), self::PAGE_SLUG, 'api_settings' );

		// Sync Settings Section.
		add_settings_section(
			'sync_settings',
			__( 'Sync Options', 'shelterkit-pets' ),
			fn() => printf( '<p>%s</p>', esc_html__( 'Configure automatic synchronization.', 'shelterkit-pets' ) ),
			self::PAGE_SLUG
		);

		add_settings_field( 'auto_sync', __( 'Auto Sync', 'shelterkit-pets' ), array( $this, 'render_auto_sync_field' ), self::PAGE_SLUG, 'sync_settings' );
		add_settings_field( 'sync_interval', __( 'Sync Interval', 'shelterkit-pets' ), array( $this, 'render_sync_interval_field' ), self::PAGE_SLUG, 'sync_settings' );
		add_settings_field( 'batch_size', __( 'Batch Size', 'shelterkit-pets' ), array( $this, 'render_batch_size_field' ), self::PAGE_SLUG, 'sync_settings' );

		// Uninstall Section.
		add_settings_section(
			'uninstall_settings',
			__( 'Uninstall', 'shelterkit-pets' ),
			fn() => printf( '<p>%s</p>', esc_html__( 'What happens when the plugin is deleted from the Plugins screen.', 'shelterkit-pets' ) ),
			self::PAGE_SLUG
		);

		add_settings_field( 'delete_data_on_uninstall', __( 'Data Removal', 'shelterkit-pets' ), array( $this, 'render_delete_data_field' ), self::PAGE_SLUG, 'uninstall_settings' );
	}

	public const SCHEDULE_6PM_SKIP_SUNDAY = 'daily_6pm_skip_sunday';

	public static function get_defaults(): array {
		return array(
			'public_key'               => '',
			'auto_sync'                => true,
			'sync_interval'            => self::SCHEDULE_6PM_SKIP_SUNDAY,
			'batch_size'               => 10,
			'delete_data_on_uninstall' => false,
		);
	}

	/**
	 * Allowed values for the sync_interval setting.
	 *
	 * The legacy WP-Cron recurrences (hourly/twicedaily/daily) are kept as
	 * fallbacks. SCHEDULE_6PM_SKIP_SUNDAY is also implemented on top of the
	 * 'daily' recurrence — the Sunday skip and the 18:00 anchor live in code.
	 */
	private static function allowed_intervals(): array {
		return array( self::SCHEDULE_6PM_SKIP_SUNDAY, 'hourly', 'twicedaily', 'daily' );
	}

	public static function get_settings(): array {
		return wp_parse_args( get_option( self::OPTION_NAME, array() ), self::get_defaults() );
	}

	public function sanitize_settings( $input ): array {
		$sanitized                             = array();
		$sanitized['public_key']               = sanitize_text_field( $input['public_key'] ?? '' );
		$sanitized['auto_sync']                = ! empty( $input['auto_sync'] );
		$sanitized['sync_interval']            = in_array( $input['sync_interval'] ?? '', self::allowed_intervals(), true )
			? $input['sync_interval'] : self::SCHEDULE_6PM_SKIP_SUNDAY;
		$sanitized['batch_size']               = absint( $input['batch_size'] ?? 10 );
		$sanitized['batch_size']               = max( 1, min( 50, $sanitized['batch_size'] ) );
		$sanitized['delete_data_on_uninstall'] = ! empty( $input['delete_data_on_uninstall'] );

		// Reschedule cron if auto_sync or interval changed.
		$old = self::get_settings();
		if ( $sanitized['auto_sync'] !== $old['auto_sync'] || $sanitized['sync_interval'] !== $old['sync_interval'] ) {
			self::reschedule_cron( $sanitized['auto_sync'], $sanitized['sync_interval'] );
		}

		return $sanitized;
	}

	/**
	 * Clear any existing scheduled sync and (if enabled) re-register one.
	 *
	 * Centralized so activation and settings-save use the same logic. The
	 * SCHEDULE_6PM_SKIP_SUNDAY pseudo-interval is implemented on top of WP's
	 * built-in 'daily' recurrence — anchored to 18:00 in the site timezone,
	 * with the Sunday short-circuit handled by Petsync_Sync::run_sync().
	 */
	public static function reschedule_cron( bool $auto_sync, string $interval ): void {
		wp_clear_scheduled_hook( 'petsync_scheduled_sync' );
		if ( ! $auto_sync ) {
			return;
		}

		if ( $interval === self::SCHEDULE_6PM_SKIP_SUNDAY ) {
			wp_schedule_event( self::next_6pm_timestamp(), 'daily', 'petsync_scheduled_sync' );
		} else {
			wp_schedule_event( time(), $interval, 'petsync_scheduled_sync' );
		}
	}

	/**
	 * UTC timestamp of the next 18:00 in the site timezone.
	 *
	 * Uses wall-clock math (DateTimeImmutable::setTime) so DST transitions
	 * shift the gap to 23h/25h without breaking the next-day anchor.
	 */
	public static function next_6pm_timestamp(): int {
		$tz  = wp_timezone();
		$now = new \DateTimeImmutable( 'now', $tz );
		$six = $now->setTime( 18, 0, 0 );
		if ( $six <= $now ) {
			$six = $six->modify( '+1 day' );
		}
		return $six->getTimestamp();
	}

	// Field Renderers.

	public function render_public_key_field(): void {
		$settings = self::get_settings();
		printf(
			'<input type="text" name="%s[public_key]" value="%s" class="regular-text">
			<p class="description">%s</p>',
			esc_attr( self::OPTION_NAME ),
			esc_attr( $settings['public_key'] ),
			esc_html__( 'Your Petstablished public key (found in account settings).', 'shelterkit-pets' )
		);
	}

	public function render_auto_sync_field(): void {
		$settings = self::get_settings();
		printf(
			'<label><input type="checkbox" name="%s[auto_sync]" value="1" %s> %s</label>',
			esc_attr( self::OPTION_NAME ),
			checked( $settings['auto_sync'], true, false ),
			esc_html__( 'Automatically sync pets on a schedule', 'shelterkit-pets' )
		);
	}

	public function render_delete_data_field(): void {
		$settings = self::get_settings();
		printf(
			'<label><input type="checkbox" name="%s[delete_data_on_uninstall]" value="1" %s> %s</label><p class="description">%s</p>',
			esc_attr( self::OPTION_NAME ),
			checked( $settings['delete_data_on_uninstall'], true, false ),
			esc_html__( 'Delete all data when this plugin is deleted', 'shelterkit-pets' ),
			esc_html__( 'Removes imported pets, pet taxonomies, template customizations, settings, and visitors’ saved favorites and comparison lists. Leave off to keep everything in place for a reinstall. Pets can be re-imported from Petstablished at any time.', 'shelterkit-pets' )
		);
	}

	public function render_sync_interval_field(): void {
		$settings  = self::get_settings();
		$intervals = array(
			self::SCHEDULE_6PM_SKIP_SUNDAY => __( 'Daily at 6pm (skip Sundays)', 'shelterkit-pets' ),
			'hourly'                       => __( 'Hourly', 'shelterkit-pets' ),
			'twicedaily'                   => __( 'Twice Daily', 'shelterkit-pets' ),
			'daily'                        => __( 'Daily', 'shelterkit-pets' ),
		);
		echo '<select name="' . esc_attr( self::OPTION_NAME ) . '[sync_interval]">';
		foreach ( $intervals as $value => $label ) {
			printf( '<option value="%s" %s>%s</option>', esc_attr( $value ), selected( $settings['sync_interval'], $value, false ), esc_html( $label ) );
		}
		echo '</select>';
	}

	public function render_batch_size_field(): void {
		$settings = self::get_settings();
		printf(
			'<input type="number" name="%s[batch_size]" value="%d" min="1" max="50" class="small-text">
			<p class="description">%s</p>',
			esc_attr( self::OPTION_NAME ),
			(int) $settings['batch_size'],
			esc_html__( 'Number of pets to process per batch (1-50). Lower values for shared hosting.', 'shelterkit-pets' )
		);
	}

	public function render_settings_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings   = self::get_settings();
		$last_sync  = get_option( 'petsync_last_sync' );
		$sync_stats = get_option( 'petsync_last_sync_stats', array() );
		$is_syncing = get_transient( 'petsync_sync_in_progress' );
		$next_cron  = $settings['auto_sync'] ? wp_next_scheduled( 'petsync_scheduled_sync' ) : false;
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Petstablished Sync', 'shelterkit-pets' ); ?></h1>

			<!-- Sync Status Card -->
			<div class="card" style="max-width: 600px; margin-bottom: 20px;">
				<h2><?php esc_html_e( 'Sync Status', 'shelterkit-pets' ); ?></h2>
				
				<div id="sync-status">
					<?php if ( $last_sync ) : ?>
						<p>
							<strong><?php esc_html_e( 'Last sync:', 'shelterkit-pets' ); ?></strong>
							<?php echo esc_html( human_time_diff( $last_sync ) . ' ' . __( 'ago', 'shelterkit-pets' ) ); ?>
							<br>
							<small><?php echo esc_html( wp_date( 'F j, Y g:i a', $last_sync ) ); ?></small>
						</p>
					<?php else : ?>
						<p><?php esc_html_e( 'No sync has been performed yet.', 'shelterkit-pets' ); ?></p>
					<?php endif; ?>

					<?php if ( $next_cron ) : ?>
						<p>
							<strong><?php esc_html_e( 'Next scheduled run:', 'shelterkit-pets' ); ?></strong>
							<?php echo esc_html( wp_date( 'F j, Y g:i a', $next_cron ) ); ?>
							<?php if ( $settings['sync_interval'] === self::SCHEDULE_6PM_SKIP_SUNDAY ) : ?>
								<br><small><?php esc_html_e( 'Sundays are skipped.', 'shelterkit-pets' ); ?></small>
							<?php endif; ?>
						</p>
					<?php endif; ?>
				</div>

				<!-- Progress Bar (hidden by default) -->
				<div id="sync-progress" style="display: none; margin: 15px 0;">
					<div style="background: #e0e0e0; border-radius: 4px; overflow: hidden;">
						<div id="sync-progress-bar" style="background: #0073aa; height: 20px; width: 0%; transition: width 0.3s;"></div>
					</div>
					<p id="sync-progress-text" style="margin: 5px 0; font-size: 13px;"></p>
				</div>

				<!-- Results (shown after sync) -->
				<div id="sync-results" style="display: none; margin: 15px 0; padding: 10px; background: #d4edda; border-radius: 4px;">
				</div>

				<p>
					<button type="button" id="sync-button" class="button button-primary" <?php echo empty( $settings['public_key'] ) ? 'disabled' : ''; ?>>
						<?php esc_html_e( 'Sync Now', 'shelterkit-pets' ); ?>
					</button>
					
					<?php
					$pet_count = wp_count_posts( 'vcps_pet' );
					$total     = ( $pet_count->publish ?? 0 ) + ( $pet_count->draft ?? 0 );
					?>
					<span id="pet-count" style="margin-left: 10px;">
						<?php printf( /* translators: %d: number of pets */ esc_html__( '%d pets in database', 'shelterkit-pets' ), (int) $total ); ?>
					</span>
				</p>

				<?php if ( empty( $settings['public_key'] ) ) : ?>
					<p class="description" style="color: #d63638;">
						<?php esc_html_e( 'Please enter your Public Key below to enable syncing.', 'shelterkit-pets' ); ?>
					</p>
				<?php endif; ?>
			</div>

			<!-- Settings Form -->
			<form method="post" action="options.php">
				<?php
				settings_fields( self::PAGE_SLUG );
				do_settings_sections( self::PAGE_SLUG );
				submit_button();
				?>
			</form>
		</div>

		<script>
		(function() {
			const syncButton = document.getElementById('sync-button');
			const progressDiv = document.getElementById('sync-progress');
			const progressBar = document.getElementById('sync-progress-bar');
			const progressText = document.getElementById('sync-progress-text');
			const resultsDiv = document.getElementById('sync-results');
			const statusDiv = document.getElementById('sync-status');

			let totalPets = 0;
			let processed = 0;
			let batchSize = <?php echo (int) $settings['batch_size']; ?>;
			let stats = { created: 0, updated: 0, unchanged: 0 };

			syncButton.addEventListener('click', startSync);

			async function startSync() {
				syncButton.disabled = true;
				syncButton.textContent = '<?php esc_html_e( 'Starting...', 'shelterkit-pets' ); ?>';
				progressDiv.style.display = 'block';
				resultsDiv.style.display = 'none';
				progressBar.style.width = '0%';
				progressText.textContent = '<?php esc_html_e( 'Fetching pets from Petstablished...', 'shelterkit-pets' ); ?>';

				try {
					const startRes = await fetch(ajaxurl, {
						method: 'POST',
						headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
						body: new URLSearchParams({
							action: 'petsync_start_sync',
							nonce: '<?php echo esc_js( wp_create_nonce( 'petsync_sync' ) ); ?>'
						})
					});
					const startData = await startRes.json();

					if (!startData.success) {
						throw new Error(startData.data || 'Failed to start sync');
					}

					totalPets = startData.data.total;
					batchSize = startData.data.batchSize;
					processed = 0;
					stats = { created: 0, updated: 0, unchanged: 0 };

					progressText.textContent = `<?php esc_html_e( 'Found', 'shelterkit-pets' ); ?> ${totalPets} <?php esc_html_e( 'pets. Processing...', 'shelterkit-pets' ); ?>`;

					// Process in batches
					while (processed < totalPets) {
						await processBatch();
					}

					// Finish sync
					await finishSync();

				} catch (error) {
					progressText.textContent = '<?php esc_html_e( 'Error:', 'shelterkit-pets' ); ?> ' + error.message;
					progressText.style.color = '#d63638';
				}

				syncButton.disabled = false;
				syncButton.textContent = '<?php esc_html_e( 'Sync Now', 'shelterkit-pets' ); ?>';
			}

			async function processBatch() {
				const res = await fetch(ajaxurl, {
					method: 'POST',
					headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
					body: new URLSearchParams({
						action: 'petsync_process_batch',
						nonce: '<?php echo esc_js( wp_create_nonce( 'petsync_sync' ) ); ?>',
						offset: processed,
						limit: batchSize
					})
				});
				const data = await res.json();

				if (!data.success) {
					throw new Error(data.data || 'Batch processing failed');
				}

				processed += data.data.processed;
				stats.created += data.data.stats.created || 0;
				stats.updated += data.data.stats.updated || 0;
				stats.unchanged += data.data.stats.unchanged || 0;

				const percent = Math.round((processed / totalPets) * 100);
				progressBar.style.width = percent + '%';
				progressText.textContent = `<?php esc_html_e( 'Processing:', 'shelterkit-pets' ); ?> ${processed} / ${totalPets} (${percent}%)`;
			}

			async function finishSync() {
				progressText.textContent = '<?php esc_html_e( 'Finishing up...', 'shelterkit-pets' ); ?>';

				const res = await fetch(ajaxurl, {
					method: 'POST',
					headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
					body: new URLSearchParams({
						action: 'petsync_finish_sync',
						nonce: '<?php echo esc_js( wp_create_nonce( 'petsync_sync' ) ); ?>'
					})
				});
				const data = await res.json();

				progressBar.style.width = '100%';
				progressDiv.style.display = 'none';

				resultsDiv.innerHTML = `
					<strong><?php esc_html_e( 'Sync Complete!', 'shelterkit-pets' ); ?></strong><br>
					<?php esc_html_e( 'Created:', 'shelterkit-pets' ); ?> ${stats.created} |
					<?php esc_html_e( 'Updated:', 'shelterkit-pets' ); ?> ${stats.updated} |
					<?php esc_html_e( 'Unchanged:', 'shelterkit-pets' ); ?> ${stats.unchanged}
					${data.data?.removed ? ` | <?php esc_html_e( 'Removed:', 'shelterkit-pets' ); ?> ${data.data.removed}` : ''}
				`;
				resultsDiv.style.display = 'block';

				// Update status text
				statusDiv.innerHTML = '<p><strong><?php esc_html_e( 'Last sync:', 'shelterkit-pets' ); ?></strong> <?php esc_html_e( 'Just now', 'shelterkit-pets' ); ?></p>';
			}
		})();
		</script>
		<?php
	}

	public function render_sync_log_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$entries = Petsync_Sync_Log::all();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Sync Log', 'shelterkit-pets' ); ?></h1>
			<p class="description">
				<?php
				printf(
					/* translators: %d: maximum number of log entries kept. */
					esc_html__( 'Records of the most recent %d sync attempts. Newest first.', 'shelterkit-pets' ),
					(int) Petsync_Sync_Log::MAX_ENTRIES
				);
				?>
			</p>

			<?php if ( empty( $entries ) ) : ?>
				<div class="card" style="max-width: 600px;">
					<p><?php esc_html_e( 'No syncs have been recorded yet.', 'shelterkit-pets' ); ?></p>
					<p>
						<a class="button" href="<?php echo esc_url( admin_url( 'edit.php?post_type=vcps_pet&page=' . self::PAGE_SLUG ) ); ?>">
							<?php esc_html_e( 'Go to Sync Settings', 'shelterkit-pets' ); ?>
						</a>
					</p>
				</div>
			<?php else : ?>
				<style>
					.ps-sync-log .col-when     { width: 22%; }
					.ps-sync-log .col-trigger  { width: 10%; }
					.ps-sync-log .col-outcome  { width: 10%; }
					.ps-sync-log .col-counts   { width: 38%; }
					.ps-sync-log .col-details  { width: 20%; }
					.ps-badge {
						display: inline-block;
						padding: 2px 8px;
						border-radius: 10px;
						font-size: 11px;
						font-weight: 600;
						text-transform: uppercase;
						letter-spacing: 0.03em;
					}
					.ps-badge-trigger-manual { background: #e5e5e5; color: #2c3338; }
					.ps-badge-trigger-cron   { background: #d6e9fb; color: #0a4b78; }
					.ps-badge-outcome-success { background: #d4edda; color: #155724; }
					.ps-badge-outcome-partial { background: #fff3cd; color: #856404; }
					.ps-badge-outcome-error   { background: #f8d7da; color: #721c24; }
					.ps-sync-log .counts code {
						background: none;
						padding: 0;
						font-size: 13px;
					}
					.ps-sync-log .detail-row td {
						background: #f6f7f7;
						padding: 12px 16px;
					}
					.ps-sync-log .detail-row dl {
						margin: 0;
						display: grid;
						grid-template-columns: 140px 1fr;
						row-gap: 4px;
						column-gap: 12px;
					}
					.ps-sync-log .detail-row dt { font-weight: 600; }
					.ps-sync-log .detail-row dd { margin: 0; }
					.ps-sync-log .detail-row pre {
						margin: 4px 0 0;
						padding: 8px;
						background: #fff;
						border: 1px solid #dcdcde;
						border-radius: 3px;
						white-space: pre-wrap;
						font-size: 12px;
					}
					.ps-sync-log .note {
						display: inline-block;
						margin-left: 8px;
						font-style: italic;
						color: #50575e;
					}
				</style>
				<table class="wp-list-table widefat striped ps-sync-log">
					<thead>
						<tr>
							<th class="col-when"><?php esc_html_e( 'When', 'shelterkit-pets' ); ?></th>
							<th class="col-trigger"><?php esc_html_e( 'Trigger', 'shelterkit-pets' ); ?></th>
							<th class="col-outcome"><?php esc_html_e( 'Outcome', 'shelterkit-pets' ); ?></th>
							<th class="col-counts"><?php esc_html_e( 'Counts', 'shelterkit-pets' ); ?></th>
							<th class="col-details"><?php esc_html_e( 'Details', 'shelterkit-pets' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $entries as $entry ) : ?>
							<?php
							$id        = (string) ( $entry['id'] ?? '' );
							$started   = (int) ( $entry['started'] ?? 0 );
							$ended     = (int) ( $entry['ended'] ?? $started );
							$duration  = (int) ( $entry['duration'] ?? max( 0, $ended - $started ) );
							$trigger   = (string) ( $entry['trigger'] ?? 'manual' );
							$outcome   = (string) ( $entry['outcome'] ?? 'success' );
							$stats     = is_array( $entry['stats'] ?? null ) ? $entry['stats'] : array();
							$errors    = is_array( $entry['errors'] ?? null ) ? $entry['errors'] : array();
							$note      = isset( $entry['note'] ) ? (string) $entry['note'] : '';
							$detail_id = 'ps-detail-' . sanitize_html_class( $id );
							?>
							<tr>
								<td class="col-when">
									<?php echo esc_html( wp_date( 'M j, Y g:i a', $started ) ); ?>
									<br><small>
									<?php
										/* translators: %d: duration in seconds. */
										printf( esc_html__( '%ds', 'shelterkit-pets' ), (int) $duration );
									?>
									</small>
								</td>
								<td class="col-trigger">
									<span class="ps-badge ps-badge-trigger-<?php echo esc_attr( $trigger ); ?>">
										<?php echo esc_html( $trigger ); ?>
									</span>
								</td>
								<td class="col-outcome">
									<span class="ps-badge ps-badge-outcome-<?php echo esc_attr( $outcome ); ?>">
										<?php echo esc_html( $outcome ); ?>
									</span>
									<?php if ( $note ) : ?>
										<span class="note"><?php echo esc_html( $note ); ?></span>
									<?php endif; ?>
								</td>
								<td class="col-counts counts">
									<code>
										C: <?php echo (int) ( $stats['created'] ?? 0 ); ?> ·
										U: <?php echo (int) ( $stats['updated'] ?? 0 ); ?> ·
										N: <?php echo (int) ( $stats['unchanged'] ?? 0 ); ?> ·
										R: <?php echo (int) ( $stats['removed'] ?? 0 ); ?> ·
										E: <?php echo (int) ( $stats['errors'] ?? 0 ); ?>
									</code>
								</td>
								<td class="col-details">
									<button type="button" class="button-link ps-detail-toggle" data-target="<?php echo esc_attr( $detail_id ); ?>">
										<?php esc_html_e( 'Show details', 'shelterkit-pets' ); ?>
									</button>
								</td>
							</tr>
							<tr id="<?php echo esc_attr( $detail_id ); ?>" class="detail-row" style="display:none;">
								<td colspan="5">
									<dl>
										<dt><?php esc_html_e( 'Started', 'shelterkit-pets' ); ?></dt>
										<dd><?php echo esc_html( wp_date( 'F j, Y g:i:s a', $started ) ); ?></dd>
										<dt><?php esc_html_e( 'Ended', 'shelterkit-pets' ); ?></dt>
										<dd><?php echo esc_html( wp_date( 'F j, Y g:i:s a', $ended ) ); ?></dd>
										<dt><?php esc_html_e( 'Duration', 'shelterkit-pets' ); ?></dt>
										<dd>
										<?php
											/* translators: %d: duration in seconds. */
											printf( esc_html__( '%d seconds', 'shelterkit-pets' ), (int) $duration );
										?>
										</dd>
										<dt><?php esc_html_e( 'Created', 'shelterkit-pets' ); ?></dt>
										<dd><?php echo (int) ( $stats['created'] ?? 0 ); ?></dd>
										<dt><?php esc_html_e( 'Updated', 'shelterkit-pets' ); ?></dt>
										<dd><?php echo (int) ( $stats['updated'] ?? 0 ); ?></dd>
										<dt><?php esc_html_e( 'Unchanged', 'shelterkit-pets' ); ?></dt>
										<dd><?php echo (int) ( $stats['unchanged'] ?? 0 ); ?></dd>
										<dt><?php esc_html_e( 'Removed', 'shelterkit-pets' ); ?></dt>
										<dd><?php echo (int) ( $stats['removed'] ?? 0 ); ?></dd>
										<dt><?php esc_html_e( 'Errors', 'shelterkit-pets' ); ?></dt>
										<dd>
											<?php echo (int) ( $stats['errors'] ?? 0 ); ?>
											<?php if ( ! empty( $errors ) ) : ?>
												<pre><?php echo esc_html( implode( "\n", $errors ) ); ?></pre>
											<?php endif; ?>
										</dd>
										<?php if ( $note ) : ?>
											<dt><?php esc_html_e( 'Note', 'shelterkit-pets' ); ?></dt>
											<dd><?php echo esc_html( $note ); ?></dd>
										<?php endif; ?>
									</dl>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<script>
				(function() {
					document.querySelectorAll('.ps-detail-toggle').forEach(function(btn) {
						btn.addEventListener('click', function() {
							const row = document.getElementById(btn.dataset.target);
							if (!row) return;
							const isHidden = row.style.display === 'none';
							row.style.display = isHidden ? '' : 'none';
							btn.textContent = isHidden
								? <?php echo wp_json_encode( __( 'Hide details', 'shelterkit-pets' ) ); ?>
								: <?php echo wp_json_encode( __( 'Show details', 'shelterkit-pets' ) ); ?>;
						});
					});
				})();
				</script>
			<?php endif; ?>
		</div>
		<?php
	}

	public function enqueue_assets( $hook ): void {
		if ( ! str_contains( $hook, self::PAGE_SLUG ) ) {
			return;
		}

		wp_enqueue_style(
			'petstablished-admin',
			PETSYNC_URL . 'assets/css/admin.css',
			array(),
			PETSYNC_VERSION
		);
	}

	public function display_notices(): void {
		if ( ! isset( $_GET['petsync_sync'] ) ) {
			return;
		}

		$status  = sanitize_text_field( wp_unslash( $_GET['petsync_sync'] ) );
		$message = '';
		$type    = 'success';

		switch ( $status ) {
			case 'started':
				$message = __( 'Sync started. This may take a few minutes.', 'shelterkit-pets' );
				$type    = 'info';
				break;
			case 'complete':
				$message = __( 'Sync completed successfully.', 'shelterkit-pets' );
				break;
			case 'error':
				$message = __( 'Sync failed. Please check your API credentials.', 'shelterkit-pets' );
				$type    = 'error';
				break;
		}

		if ( $message ) {
			printf( '<div class="notice notice-%s is-dismissible"><p>%s</p></div>', esc_attr( $type ), esc_html( $message ) );
		}
	}

	public function add_settings_link( $links ): array {
		$url  = admin_url( 'edit.php?post_type=vcps_pet&page=' . self::PAGE_SLUG );
		$link = sprintf( '<a href="%s">%s</a>', esc_url( $url ), esc_html__( 'Settings', 'shelterkit-pets' ) );
		array_unshift( $links, $link );
		return $links;
	}
}
