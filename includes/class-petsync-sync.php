<?php
/**
 * ShelterKit Pets Sync Handler
 *
 * Handles synchronization of pets from Petstablished API.
 *
 * @package ShelterKit_Pets
 * @since 1.0.0
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Petsync_Sync {

	private const API_BASE = 'https://petstablished.com/api/v2/public/pets';

	/**
	 * Slug identifying the shelter platform this sync imports from.
	 *
	 * Stamped onto every imported pet as `_pet_provider` so the identity lookup
	 * and stale-pet pruning below stay scoped to this provider's records. Record
	 * IDs are only unique within a provider, so without this scoping a second
	 * provider's pets could collide on ID or be drafted as "stale" by a sync
	 * that never saw them.
	 */
	public const PROVIDER = 'petstablished';

	private const SESSION_TRANSIENT = 'petsync_sync_session';

	private array $stats = array(
		'created'   => 0,
		'updated'   => 0,
		'unchanged' => 0,
		'removed'   => 0,
		'errors'    => array(),
	);

	public function __construct() {
		// Register with accepted_args=0 so do_action's default empty-string arg
		// doesn't override our $trigger default of 'cron'.
		add_action( 'petsync_scheduled_sync', array( $this, 'run_sync' ), 10, 0 );
		add_action( 'wp_ajax_petsync_start_sync', array( $this, 'ajax_start_sync' ) );
		add_action( 'wp_ajax_petsync_process_batch', array( $this, 'ajax_process_batch' ) );
		add_action( 'wp_ajax_petsync_finish_sync', array( $this, 'ajax_finish_sync' ) );
	}

	// === AJAX Handlers for JS-based sync ===

	public function ajax_start_sync(): void {
		check_ajax_referer( 'petsync_sync', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized' );
		}

		$settings = Petsync_Admin::get_settings();

		if ( empty( $settings['public_key'] ) ) {
			$this->record_error_run( 'manual', 'Public key not configured' );
			wp_send_json_error( 'Public key not configured' );
		}

		// Fetch all pets from API.
		$result = $this->fetch_pets( $settings['public_key'] );

		if ( is_wp_error( $result ) ) {
			$this->record_error_run( 'manual', $result->get_error_message() );
			wp_send_json_error( $result->get_error_message() );
		}

		$pets = $result['pets'];

		// Store pets in transient for batch processing.
		set_transient( 'petsync_sync_pets', $pets, HOUR_IN_SECONDS );
		set_transient( 'petsync_sync_in_progress', true, 10 * MINUTE_IN_SECONDS );

		// When the fetch was incomplete, flag it so ajax_finish_sync skips
		// stale-pet pruning — pets on the unfetched pages must not be drafted.
		if ( ! $result['complete'] ) {
			set_transient( 'petsync_sync_incomplete', true, HOUR_IN_SECONDS );
		}

		// Initialize the server-side session aggregator. Stats accumulate here
		// across batch calls so the final log entry doesn't depend on the
		// browser POSTing back honest numbers. Seed with any page-fetch errors.
		set_transient(
			self::SESSION_TRANSIENT,
			array(
				'started'       => time(),
				'trigger'       => 'manual',
				'running_stats' => array(
					'created'   => 0,
					'updated'   => 0,
					'unchanged' => 0,
					'errors'    => 0,
				),
				'errors'        => $result['errors'],
			),
			HOUR_IN_SECONDS
		);

		wp_send_json_success(
			array(
				'total'      => count( $pets ),
				'batchSize'  => $settings['batch_size'],
				'incomplete' => ! $result['complete'],
			)
		);
	}

	public function ajax_process_batch(): void {
		check_ajax_referer( 'petsync_sync', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized' );
		}

		$offset = absint( $_POST['offset'] ?? 0 );
		$limit  = absint( $_POST['limit'] ?? 10 );

		$pets = get_transient( 'petsync_sync_pets' );

		if ( ! $pets ) {
			wp_send_json_error( 'Sync session expired. Please start again.' );
		}

		$batch        = array_slice( $pets, $offset, $limit );
		$stats        = array(
			'created'   => 0,
			'updated'   => 0,
			'unchanged' => 0,
			'errors'    => 0,
		);
		$batch_errors = array();

		foreach ( $batch as $pet_data ) {
			$result = $this->process_single_pet( $pet_data );
			if ( $result === 'errors' ) {
				++$stats['errors'];
				$batch_errors[] = 'Pet ID ' . ( $pet_data['id'] ?? 'unknown' );
			} else {
				++$stats[ $result ];
			}
		}

		// Accumulate into the session aggregator so ajax_finish_sync can
		// build the audit log entry from server-side state.
		$session = get_transient( self::SESSION_TRANSIENT );
		if ( is_array( $session ) ) {
			foreach ( $stats as $key => $count ) {
				$session['running_stats'][ $key ] = ( $session['running_stats'][ $key ] ?? 0 ) + $count;
			}
			$session['errors'] = array_merge( $session['errors'] ?? array(), $batch_errors );
			set_transient( self::SESSION_TRANSIENT, $session, HOUR_IN_SECONDS );
		}

		wp_send_json_success(
			array(
				'processed' => count( $batch ),
				'stats'     => $stats,
			)
		);
	}

	public function ajax_finish_sync(): void {
		check_ajax_referer( 'petsync_sync', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Unauthorized' );
		}

		$pets       = get_transient( 'petsync_sync_pets' );
		$incomplete = (bool) get_transient( 'petsync_sync_incomplete' );
		$removed    = 0;

		// Only prune stale pets when the fetch was complete — otherwise pets on
		// the unfetched pages would be wrongly drafted.
		if ( $pets && ! $incomplete ) {
			$removed = $this->remove_stale_pets( $pets );
		}

		// Time-based, so it runs whether or not THIS fetch completed. That is
		// deliberate: the case it exists for is a provider that has gone away,
		// where every fetch is incomplete and the completeness guard above
		// would mean it never fires. The evidence here is weeks of absence, not
		// one bad response.
		$draft_days = (int) ( Petsync_Admin::get_settings()['stale_draft_days'] ?? 0 );
		if ( $draft_days > 0 ) {
			self::draft_stale_pets( $draft_days );
		}

		// Build the log entry from the server-side session aggregator.
		$session = get_transient( self::SESSION_TRANSIENT );
		if ( is_array( $session ) ) {
			$running = $session['running_stats'] ?? array();
			$errors  = $session['errors'] ?? array();
			$stats   = array(
				'created'   => (int) ( $running['created'] ?? 0 ),
				'updated'   => (int) ( $running['updated'] ?? 0 ),
				'unchanged' => (int) ( $running['unchanged'] ?? 0 ),
				'removed'   => $removed,
				'errors'    => (int) ( $running['errors'] ?? 0 ),
			);
			$outcome = ( $stats['errors'] > 0 || $incomplete || ! empty( $errors ) ) ? 'partial' : 'success';
			$started = (int) ( $session['started'] ?? time() );
			$ended   = time();

			Petsync_Sync_Log::record(
				Petsync_Sync_Log::build_entry(
					$started,
					$ended,
					'manual',
					$outcome,
					$stats,
					$errors
				)
			);

			// Preserve back-compat: keep writing last_sync_stats in its legacy shape.
			update_option(
				'petsync_last_sync_stats',
				array(
					'created'   => $stats['created'],
					'updated'   => $stats['updated'],
					'unchanged' => $stats['unchanged'],
					'removed'   => $stats['removed'],
					'errors'    => $errors,
				)
			);
		}

		// Clean up.
		delete_transient( 'petsync_sync_pets' );
		delete_transient( 'petsync_sync_in_progress' );
		delete_transient( 'petsync_sync_incomplete' );
		delete_transient( self::SESSION_TRANSIENT );

		update_option( 'petsync_last_sync', time() );

		wp_send_json_success(
			array(
				'removed' => $removed,
			)
		);
	}

	/**
	 * Record an immediate error-outcome entry for sync attempts that fail before
	 * any pets are processed (missing key, API fetch error, fatal exception).
	 */
	private function record_error_run( string $trigger, string $message, ?int $started = null ): void {
		$ended   = time();
		$started = $started ?? $ended;
		Petsync_Sync_Log::record(
			Petsync_Sync_Log::build_entry(
				$started,
				$ended,
				$trigger,
				'error',
				array(),
				array( $message )
			)
		);
	}

	// === Background sync for cron ===

	public function run_sync( string $trigger = 'cron' ): bool {
		$started  = time();
		$settings = Petsync_Admin::get_settings();

		// Sunday skip: when the user has chosen the 6pm-skip-Sunday schedule
		// and this cron run lands on a Sunday in the site timezone, record
		// the skip and bail. Recording it is intentional — proves to the
		// user that cron fired and made a deliberate decision.
		if ( $trigger === 'cron'
			&& $settings['sync_interval'] === Petsync_Admin::SCHEDULE_6PM_SKIP_SUNDAY
			&& wp_date( 'w' ) === '0'
		) {
			Petsync_Sync_Log::record(
				Petsync_Sync_Log::build_entry(
					$started,
					time(),
					$trigger,
					'success',
					array(),
					array(),
					'Skipped: Sunday'
				)
			);
			return true;
		}

		if ( empty( $settings['public_key'] ) ) {
			$this->record_error_run( $trigger, 'Public key not configured' );
			return false;
		}

		set_transient( 'petsync_sync_in_progress', true, 10 * MINUTE_IN_SECONDS );

		try {
			$result = $this->fetch_pets( $settings['public_key'] );

			if ( is_wp_error( $result ) ) {
				$message = $result->get_error_message();
				delete_transient( 'petsync_sync_in_progress' );
				$this->record_error_run( $trigger, $message, $started );
				return false;
			}

			$pets = $result['pets'];

			foreach ( $pets as $pet_data ) {
				$status = $this->process_single_pet( $pet_data );
				if ( $status === 'errors' ) {
					$this->stats['errors'][] = 'Pet ID ' . ( $pet_data['id'] ?? 'unknown' );
				} else {
					++$this->stats[ $status ];
				}
			}

			// Only prune stale pets when the fetch was complete — otherwise pets
			// on the unfetched pages would be wrongly drafted.
			if ( $result['complete'] ) {
				$this->stats['removed'] = $this->remove_stale_pets( $pets );
			}

			// Time-based, so it runs whether or not THIS fetch completed. That is
			// deliberate: the case it exists for is a provider that has gone away,
			// where every fetch is incomplete and the completeness guard above
			// would mean it never fires. The evidence here is weeks of absence, not
			// one bad response.
			$draft_days = (int) ( Petsync_Admin::get_settings()['stale_draft_days'] ?? 0 );
			if ( $draft_days > 0 ) {
				self::draft_stale_pets( $draft_days );
			}

			// Surface page-level fetch errors in the log.
			$this->stats['errors'] = array_merge( $this->stats['errors'], $result['errors'] );

			$ended   = time();
			$outcome = ( empty( $this->stats['errors'] ) && $result['complete'] ) ? 'success' : 'partial';

			Petsync_Sync_Log::record(
				Petsync_Sync_Log::build_entry(
					$started,
					$ended,
					$trigger,
					$outcome,
					array(
						'created'   => $this->stats['created'],
						'updated'   => $this->stats['updated'],
						'unchanged' => $this->stats['unchanged'],
						'removed'   => $this->stats['removed'],
						'errors'    => count( $this->stats['errors'] ),
					),
					$this->stats['errors']
				)
			);

			update_option( 'petsync_last_sync', $ended );
			update_option( 'petsync_last_sync_stats', $this->stats );
			delete_transient( 'petsync_sync_in_progress' );

			return true;
		} catch ( \Throwable $e ) {
			delete_transient( 'petsync_sync_in_progress' );
			$this->record_error_run( $trigger, 'Fatal: ' . $e->getMessage(), $started );
			return false;
		}
	}

	// === Core Methods ===

	/**
	 * Maximum number of pages to fetch (safety valve against infinite loops).
	 */
	private const MAX_PAGES = 50;

	/**
	 * Attempts per page before a page is considered failed (transient errors).
	 */
	private const FETCH_RETRIES = 3;

	/**
	 * Fetch all pets from the Petstablished API, paginating automatically.
	 *
	 * Resilient to transient failures: each page is retried (FETCH_RETRIES) with
	 * linear backoff, and if a later page still fails the run keeps the pets it
	 * already collected and reports itself INCOMPLETE rather than discarding
	 * everything. Callers MUST skip stale-pet pruning on an incomplete run —
	 * otherwise pets that merely live on an unfetched page would be wrongly
	 * drafted. Each record is reduced to the consumed-key subset so the batch
	 * transient stays small and free of upstream PII.
	 *
	 * @param string $public_key Petstablished public API key.
	 * @return array|WP_Error { pets: array[], complete: bool, errors: string[] }
	 *                        on any progress; WP_Error only if even page 1 fails.
	 */
	private function fetch_pets( string $public_key ): array|WP_Error {
		$all_pets     = array();
		$page_errors  = array();
		$consumed     = array_flip( self::get_consumed_api_keys() );
		$current_page = 1;
		$total_pages  = 1; // Updated after the first successful response.
		$complete     = true;

		while ( $current_page <= $total_pages ) {
			if ( $current_page > self::MAX_PAGES ) {
				$complete      = false;
				$page_errors[] = sprintf( 'Stopped at the %d-page safety limit before fetching all %d pages.', self::MAX_PAGES, $total_pages );
				break;
			}

			$page = $this->fetch_page( $public_key, $current_page );

			if ( is_wp_error( $page ) ) {
				// Couldn't fetch even the first page — nothing to sync at all.
				if ( empty( $all_pets ) ) {
					return $page;
				}
				// A later page failed after retries: keep what we have and mark
				// the run incomplete so the caller skips stale-pet pruning.
				$complete      = false;
				$page_errors[] = $page->get_error_message();
				break;
			}

			foreach ( $page['collection'] as $pet ) {
				$all_pets[] = array_intersect_key( $pet, $consumed );
			}

			$total_pages = max( 1, $page['total_pages'] );
			++$current_page;
		}

		return array(
			'pets'     => $all_pets,
			'complete' => $complete,
			'errors'   => $page_errors,
		);
	}

	/**
	 * Fetch a single page with retry/backoff on transient failures.
	 *
	 * Retries network errors, 429 (rate limit), 5xx, and malformed bodies;
	 * fails fast on 4xx client errors (e.g. a bad public key) where retrying
	 * cannot help.
	 *
	 * @param string $public_key Petstablished public API key.
	 * @param int    $page       1-based page number.
	 * @return array|WP_Error { collection: array[], total_pages: int } or error.
	 */
	private function fetch_page( string $public_key, int $page ): array|WP_Error {
		$url = add_query_arg(
			array(
				'public_key'        => $public_key,
				'search[animal]'    => 'Cat,Dog',
				'pagination[limit]' => 100,
				'pagination[page]'  => $page,
			),
			self::API_BASE
		);

		$error = new WP_Error( 'fetch_failed', sprintf( 'Page %d: fetch failed', $page ) );

		for ( $attempt = 1; $attempt <= self::FETCH_RETRIES; $attempt++ ) {
			$response = wp_remote_get( $url, array( 'timeout' => 30 ) );

			if ( is_wp_error( $response ) ) {
				$error = new WP_Error( 'http_error', sprintf( 'Page %d: %s', $page, $response->get_error_message() ) );
			} else {
				$code = (int) wp_remote_retrieve_response_code( $response );

				if ( 200 === $code ) {
					$data = json_decode( wp_remote_retrieve_body( $response ), true );
					if ( isset( $data['collection'] ) && is_array( $data['collection'] ) ) {
						return array(
							'collection'  => $data['collection'],
							'total_pages' => (int) ( $data['pagination']['total_pages'] ?? 1 ),
						);
					}
					$error = new WP_Error( 'invalid_response', sprintf( 'Page %d: malformed API response', $page ) );
				} elseif ( 429 !== $code && $code < 500 ) {
					// Client error (bad key/params) — retrying will not help.
					return new WP_Error( 'api_error', sprintf( 'Page %d: API returned status %d', $page, $code ) );
				} else {
					$error = new WP_Error( 'api_error', sprintf( 'Page %d: API returned status %d', $page, $code ) );
				}
			}

			// Linear backoff (1s, 2s, …) between attempts; none after the last.
			if ( $attempt < self::FETCH_RETRIES ) {
				sleep( $attempt );
			}
		}

		return $error;
	}

	private function process_single_pet( array $data ): string {
		$ps_id = $data['id'] ?? null;

		if ( ! $ps_id ) {
			return 'errors';
		}

		// Compute a hash of the incoming API payload for change detection.
		// We use a stable JSON encoding (keys sorted) so that identical data
		// always produces the same hash regardless of key ordering.
		$api_hash = $this->compute_api_hash( $data );

		// Find the existing pet by provider + record ID. Both halves matter:
		// record IDs are unique only within a provider, so matching on the ID
		// alone could bind this record to another platform's pet.
		$existing = get_posts(
			array(
				'post_type'   => 'vcps_pet',
				'post_status' => 'any',
				'numberposts' => 1,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- identity lookup; both keys are indexed in postmeta.
				'meta_query'  => array(
					array(
						'key'   => '_pet_ps_id',
						'value' => (string) $ps_id,
					),
					array(
						'key'   => '_pet_provider',
						'value' => self::PROVIDER,
					),
				),
			)
		);

		$post_id = $existing ? $existing[0]->ID : 0;

		// Honor Petstablished's "don't show in public search" flag: such pets
		// are imported as drafts so they never surface on the public wall, in
		// the publish-only abilities, or in feeds. They re-publish automatically
		// if the flag is later cleared (it is part of the change-detection hash).
		$is_private = ! empty( $data['dont_show_in_public_search'] );

		// Prepare post data.
		$post_data = array(
			'post_type'    => 'vcps_pet',
			'post_status'  => $is_private ? 'draft' : 'publish',
			'post_title'   => sanitize_text_field( $data['name'] ?? 'Unnamed Pet' ),
			'post_content' => wp_kses_post( $data['description'] ?? '' ),
		);

		if ( $post_id ) {
			// Fast-path: compare hash to skip entirely if nothing changed.
			$stored_hash = get_post_meta( $post_id, '_pet_api_hash', true );
			if ( $stored_hash === $api_hash ) {
				return 'unchanged';
			}

			// Something changed — full update.
			$post_data['ID'] = $post_id;
			wp_update_post( $post_data );
			$this->update_pet_meta( $post_id, $data );
			$this->update_pet_taxonomies( $post_id, $data );
			$this->maybe_set_featured_image( $post_id, $data );
			$this->store_api_snapshot( $post_id, $data, $api_hash );
			return 'updated';
		}

		$post_id = wp_insert_post( $post_data );
		if ( is_wp_error( $post_id ) ) {
			return 'errors';
		}

		$this->update_pet_meta( $post_id, $data );
		$this->update_pet_taxonomies( $post_id, $data );
		$this->maybe_set_featured_image( $post_id, $data );
		$this->store_api_snapshot( $post_id, $data, $api_hash );
		return 'created';
	}

	/**
	 * Compute a deterministic change-detection hash for a pet.
	 *
	 * Hashes ONLY the fields the sync consumes (get_consumed_api_keys) — what we
	 * store, display, or map onto the post/taxonomies — so churn in the ~210
	 * fields we ignore (owner PII, internal notes, euthanasia data, admin links,
	 * UI chrome) never triggers a needless re-process, while any change that
	 * actually affects the pet still does. Keys are sorted recursively so an
	 * identical payload always hashes identically.
	 *
	 * @param array $data Raw API response for a single pet.
	 * @return string SHA-256 hex hash.
	 */
	private function compute_api_hash( array $data ): string {
		$relevant   = array_intersect_key( $data, array_flip( self::get_consumed_api_keys() ) );
		$normalized = $this->ksort_recursive( $relevant );
		return hash( 'sha256', wp_json_encode( $normalized, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
	}

	/**
	 * Recursively sort array keys for deterministic hashing.
	 *
	 * @param mixed $value Value to normalize.
	 * @return mixed Normalized value.
	 */
	private function ksort_recursive( mixed $value ): mixed {
		if ( ! is_array( $value ) ) {
			return $value;
		}

		// Only sort associative arrays (objects); leave indexed arrays in order.
		if ( array_is_list( $value ) ) {
			return array_map( [ $this, 'ksort_recursive' ], $value );
		}

		ksort( $value );
		return array_map( [ $this, 'ksort_recursive' ], $value );
	}

	/**
	 * The raw Petstablished API keys worth persisting in the stored snapshot.
	 *
	 * The /public/pets endpoint returns the full internal record — owner PII,
	 * euthanasia data, internal notes, GPS coordinates, admin links. We persist
	 * ONLY the keys the display layer actually reads, so none of that PII is
	 * retained at rest or reachable through hydration. Derived from config:
	 *   - every api_field api_key (read by Pet_Hydrator into entity fields),
	 *   - every attribute_map source key (read by update_attribute_terms()),
	 * plus the few keys read directly by Pet_Hydrator computed fields.
	 *
	 * @return string[] Whitelisted API keys.
	 */
	public static function get_retained_api_keys(): array {
		$config = \Petsync\Core\Config::get_path( 'entities', 'entities.vcps_pet', [] );

		$keys = array();
		foreach ( $config['api_fields'] ?? array() as $field ) {
			if ( ! empty( $field['api_key'] ) ) {
				$keys[] = $field['api_key'];
			}
		}
		// Read straight from the snapshot by Pet_Hydrator compute_* methods:
		// images          → compute_image() / compute_gallery()
		// name            → compute_gallery() (image alt text)
		// id              → compute_bonded_pair_names() (own PS id)
		// date_aquired,
		// created_at      → compute_is_new() (intake date)
		// (group_id, grouped_pet_ids, siblings_names are already api_fields.)
		$computed_sources = array( 'id', 'name', 'images', 'date_aquired', 'created_at' );

		return array_values( array_unique( array_merge( $keys, $computed_sources ) ) );
	}

	/**
	 * Reduce a raw API response to the PII-free subset worth storing.
	 *
	 * @param array $data Raw API response for a single pet.
	 * @return array Whitelisted subset.
	 */
	public static function normalize_api_response( array $data ): array {
		return array_intersect_key( $data, array_flip( self::get_retained_api_keys() ) );
	}

	/**
	 * Every raw API key the sync actually consumes — for storage/display OR to
	 * build the post object and taxonomy terms.
	 *
	 * This is the change-detection set: the hash is computed over exactly these
	 * keys, so churn in fields we never use (owner PII, internal notes, admin
	 * links, euthanasia data, …) cannot trigger a needless re-process, while any
	 * change to a field that affects the post still does. Superset of the stored
	 * whitelist; the extras drive post_content / post_status / taxonomy terms but
	 * aren't persisted. Guarded by the config validator against drift.
	 *
	 * @return string[] Consumed API keys.
	 */
	public static function get_consumed_api_keys(): array {
		return array_values(
			array_unique(
				array_merge(
					self::get_retained_api_keys(),                    // stored + displayed
					array_keys( self::get_taxonomy_source_map() ),    // taxonomy term sources
					array(
						'description',                  // → post_content
						'dont_show_in_public_search',   // → post_status (draft)
						'secondary_breed',              // → appended pet_breed term
					)
					// name + secondary_color are already in the retained set.
				)
			)
		);
	}

	/**
	 * Store the normalized API snapshot and its change-detection hash.
	 *
	 * Only the display-relevant subset is persisted (see normalize_api_response);
	 * the third-party PII in the raw response is never stored. The hash is still
	 * computed over the full response upstream, so any upstream change — even to
	 * a field we don't store — still triggers a re-sync.
	 *
	 * @param int    $post_id Post ID.
	 * @param array  $data    Raw API response for this pet.
	 * @param string $hash    Pre-computed SHA-256 hash (of the full response).
	 */
	private function store_api_snapshot( int $post_id, array $data, string $hash ): void {
		$snapshot = self::normalize_api_response( $data );
		update_post_meta( $post_id, '_pet_api_response', wp_json_encode( $snapshot, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
		update_post_meta( $post_id, '_pet_api_hash', $hash );
	}

	/**
	 * Write only the essential meta fields.
	 *
	 * Display-only data is read from _pet_api_response at hydration time.
	 * Only the identity pair is stored as individual meta — the provider slug
	 * and that provider's record ID — because together they are the lookup key
	 * the sync uses to match API records to local posts (see the meta_query in
	 * process_single_pet) and to scope stale-pet pruning.
	 */
	private function update_pet_meta( int $post_id, array $data ): void {
		$ps_id = $data['id'] ?? null;
		if ( $ps_id ) {
			update_post_meta( $post_id, '_pet_ps_id', sanitize_text_field( (string) $ps_id ) );
			update_post_meta( $post_id, '_pet_provider', self::PROVIDER );
		}

		// Written whether or not the wider fetch completed: this records that
		// the provider still knows about THIS pet, which is a different fact
		// from whether the whole feed came back.
		update_post_meta( $post_id, '_pet_last_seen', (string) time() );
	}

	/**
	 * Sync all taxonomy terms from the API response.
	 *
	 * Handles:
	 * 1. Standard faceted taxonomies (animal, breed, age, etc.) for filtering.
	 * 2. Secondary breed / secondary color as additional terms.
	 * 3. Boolean attribute terms in pet_attribute taxonomy — replaces meta_query
	 *    filtering with much faster tax_query filtering.
	 */
	/**
	 * Single-value taxonomy mappings: raw API key → taxonomy.
	 *
	 * Lives in entities.json alongside attribute_terms, its direct sibling, so a
	 * future provider can remap which of its fields feed the standard taxonomies
	 * without touching PHP. Also consumed by get_consumed_api_keys() so the
	 * change-detection hash covers every key listed here.
	 *
	 * @return array<string, string> API key => taxonomy slug.
	 */
	private static function get_taxonomy_source_map(): array {
		return (array) \Petsync\Core\Config::get_path(
			'entities',
			'entities.vcps_pet.taxonomy_source_map',
			array()
		);
	}

	private function update_pet_taxonomies( int $post_id, array $data ): void {
		foreach ( self::get_taxonomy_source_map() as $api_key => $taxonomy ) {
			$value = $data[ $api_key ] ?? '';
			if ( $value ) {
				wp_set_object_terms( $post_id, sanitize_text_field( $value ), $taxonomy );
			}
		}

		// Secondary breed (appended).
		if ( ! empty( $data['secondary_breed'] ) ) {
			wp_set_object_terms( $post_id, sanitize_text_field( $data['secondary_breed'] ), 'pet_breed', true );
		}

		// Secondary color (appended).
		if ( ! empty( $data['secondary_color'] ) ) {
			wp_set_object_terms( $post_id, sanitize_text_field( $data['secondary_color'] ), 'pet_color', true );
		}

		// Boolean attributes → pet_attribute taxonomy terms. Derived from the
		// hydrated entity rather than from $data, so the term and the canonical
		// field cannot disagree — see CPT_Registry::sync_attribute_terms().
		\Petsync\Core\CPT_Registry::sync_attribute_terms( $post_id );
	}


	/**
	 * Set or update the featured image from the API's primary photo.
	 *
	 * On first sync: downloads and attaches the image.
	 * On subsequent syncs: compares the incoming API image URL against
	 * the stored URL. If the shelter updated the photo in Petstablished,
	 * the old attachment is deleted and replaced.
	 *
	 * @param int   $post_id Post ID.
	 * @param array $data    Raw API response for this pet.
	 */
	private function maybe_set_featured_image( int $post_id, array $data ): void {
		$new_image_url = $data['images'][0]['image']['url'] ?? '';

		if ( ! $new_image_url ) {
			return;
		}

		// Check if the image has changed by comparing against the stored source URL.
		$stored_source_url = get_post_meta( $post_id, '_pet_source_image_url', true );
		$has_thumbnail     = has_post_thumbnail( $post_id );

		if ( $has_thumbnail && $stored_source_url === $new_image_url ) {
			// Image hasn't changed — nothing to do.
			return;
		}

		// Image is new or changed — download it.
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$attachment_id = media_sideload_image( $new_image_url, $post_id, $data['name'] ?? '', 'id' );

		if ( is_wp_error( $attachment_id ) ) {
			return;
		}

		// If replacing an existing image, clean up the old attachment.
		if ( $has_thumbnail ) {
			$old_attachment_id = get_post_thumbnail_id( $post_id );
			if ( $old_attachment_id ) {
				wp_delete_attachment( (int) $old_attachment_id, true );
			}
		}

		set_post_thumbnail( $post_id, $attachment_id );
		update_post_meta( $post_id, '_pet_source_image_url', $new_image_url );
	}

	private function remove_stale_pets( array $api_pets ): int {
		$api_ids = array_column( $api_pets, 'id' );
		$removed = 0;

		// Scope to this provider's pets. A sync only knows which of its OWN
		// records disappeared upstream; pets imported from another platform are
		// legitimately absent from this response and must not be drafted.
		$local_pets = get_posts(
			array(
				'post_type'   => 'vcps_pet',
				'post_status' => 'publish',
				'numberposts' => -1,
				'fields'      => 'ids',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- provider scoping is required for correctness here.
				'meta_query'  => array(
					array(
						'key'   => '_pet_provider',
						'value' => self::PROVIDER,
					),
				),
			)
		);

		foreach ( $local_pets as $post_id ) {
			$ps_id = get_post_meta( $post_id, '_pet_ps_id', true );
			if ( $ps_id && ! in_array( (int) $ps_id, $api_ids, true ) ) {
				wp_update_post(
					array(
						'ID'          => $post_id,
						'post_status' => 'draft',
					)
				);
				++$removed;
			}
		}

		return $removed;
	}

	/**
	 * Provider pets that have not appeared in a sync for a while.
	 *
	 * The gap this closes: remove_stale_pets() only prunes when a fetch came
	 * back COMPLETE, which is right — pruning on a partial feed would draft
	 * live pets sitting on an unfetched page. But it means a provider that goes
	 * away entirely, or an account that lapses, leaves every pet published
	 * against a frozen snapshot with nothing raising a hand. The site-wide
	 * petsync_last_sync option does not help: cron keeps running and keeps
	 * failing, so "last sync" stays recent while the data rots.
	 *
	 * Pets with no _pet_last_seen at all are ignored rather than counted stale.
	 * They predate this field, and migration 8 backfills them — treating an
	 * absent timestamp as infinitely old would flag an entire catalogue on the
	 * first page load after an upgrade.
	 *
	 * @param int $days Age in days beyond which a pet counts as stale.
	 * @return int[] Post IDs.
	 */
	public static function get_stale_pets( int $days ): array {
		if ( $days < 1 ) {
			return array();
		}

		$cutoff = time() - ( $days * DAY_IN_SECONDS );

		return get_posts(
			array(
				'post_type'        => 'vcps_pet',
				'post_status'      => 'publish',
				'numberposts'      => -1,
				'fields'           => 'ids',
				'suppress_filters' => false,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- admin-only staleness check, not a request-path query.
				'meta_query'       => array(
					'relation' => 'AND',
					array(
						'key'     => '_pet_provider',
						'value'   => self::PROVIDER,
						'compare' => '=',
					),
					array(
						'key'     => '_pet_last_seen',
						'value'   => (string) $cutoff,
						'compare' => '<',
						'type'    => 'NUMERIC',
					),
				),
			)
		);
	}

	/**
	 * Draft pets the provider has stopped listing.
	 *
	 * Opt-in and off by default. It partially reverses the incomplete-fetch
	 * protection above — deliberately, but only when an admin has said so,
	 * because the failure mode of getting it wrong is a live animal
	 * disappearing from the site.
	 *
	 * @param int $days Threshold in days.
	 * @return int Number drafted.
	 */
	public static function draft_stale_pets( int $days ): int {
		$drafted = 0;

		foreach ( self::get_stale_pets( $days ) as $post_id ) {
			$result = wp_update_post(
				array(
					'ID'          => $post_id,
					'post_status' => 'draft',
				),
				true
			);

			if ( ! is_wp_error( $result ) ) {
				++$drafted;
			}
		}

		return $drafted;
	}
}
