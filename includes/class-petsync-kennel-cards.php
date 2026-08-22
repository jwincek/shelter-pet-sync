<?php
/**
 * Kennel cards — pick pets, print cards.
 *
 * SPIKE. Enough to put a real printed card in front of shelter staff and find
 * out what is actually wanted. Deliberately missing: saved selections, per-card
 * field toggles, QR codes, and any card size beyond the three below.
 *
 * The card's design is a `kennel-card` template part, not markup in this file,
 * so it is edited in the Site Editor with the blocks and bindings the plugin
 * already has. This screen only chooses pets and lays the rendered cards out
 * for a printer.
 *
 * @package ShelterKit_Pets
 * @since   1.0.0
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Petsync_Kennel_Cards {

	/**
	 * Nonce action for the one write on this otherwise read-only screen.
	 */
	private const PREVIEW_NONCE = 'petsync_preview_pet';

	private const PAGE_SLUG = 'petsync-kennel-cards';
	private const PART_SLUG = 'kennel-card';

	/**
	 * Printing kennel cards is a staff function, not a contributor one.
	 *
	 * This was `edit_posts`, which includes Contributors and Authors. The print
	 * sheet renders whatever pet IDs arrive in the query string, and while it
	 * guards the post TYPE it does not check post status — so a pet drafted by
	 * the sync (a withdrawn or adopted listing; see remove_stale_pets() and the
	 * dont_show_in_public_search mapping) could be rendered by someone with no
	 * right to read it. `edit_others_posts` is Editor and up, and those roles
	 * already hold read_private_posts, so the status question resolves itself
	 * rather than needing a second per-post check on every card.
	 *
	 * The CPT uses the default 'post' capability_type, so this maps to the
	 * standard role grants.
	 */
	private const CAPABILITY = 'edit_others_posts';

	/**
	 * Card sizes, as CSS class plus how many fit a Letter/A4 sheet.
	 *
	 * @return array<string, array{label: string, per_sheet: int}>
	 */
	private function get_sizes(): array {
		return array(
			'index' => array(
				'label'     => __( 'Index card (4×6) — 4 per sheet', 'shelterkit-pets' ),
				'per_sheet' => 4,
			),
			'half'  => array(
				'label'     => __( 'Half page — 2 per sheet', 'shelterkit-pets' ),
				'per_sheet' => 2,
			),
			'full'  => array(
				'label'     => __( 'Full page — 1 per sheet', 'shelterkit-pets' ),
				'per_sheet' => 1,
			),
		);
	}

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_page' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	public function register_page(): void {
		add_submenu_page(
			'edit.php?post_type=vcps_pet',
			__( 'Kennel Cards', 'shelterkit-pets' ),
			__( 'Kennel Cards', 'shelterkit-pets' ),
			self::CAPABILITY,
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);
	}

	public function enqueue( string $hook ): void {
		if ( ! str_contains( $hook, self::PAGE_SLUG ) ) {
			return;
		}

		// The card design is built from core blocks — wp:columns, wp:image,
		// wp:group — and their layout CSS is not loaded on an admin screen.
		// Without this the two-column card collapses into a single stack and
		// the printed sheet looks nothing like the design in the Site Editor.
		// Nothing catches this but looking: the markup is correct either way.
		wp_enqueue_style( 'wp-block-library' );

		// Theme presets, so a card styled with theme colours or spacing in the
		// Site Editor prints the way it previewed there.
		if ( function_exists( 'wp_enqueue_global_styles' ) ) {
			wp_enqueue_global_styles();
		}

		wp_enqueue_style(
			'petsync-kennel-cards',
			PETSYNC_URL . 'assets/css/kennel-cards.css',
			array( 'wp-block-library' ),
			PETSYNC_VERSION
		);
	}

	// === Screens ===

	public function render_page(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( esc_html__( 'You are not allowed to print kennel cards.', 'shelterkit-pets' ) );
		}

		// Read-only screen driven by a GET form: nothing is written, so there is
		// no state for a nonce to protect. The capability check above is what
		// matters, and every value read below is sanitised at the point of use.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only screen.
		$view = isset( $_GET['view'] ) ? sanitize_key( wp_unslash( $_GET['view'] ) ) : '';

		$printing = 'print' === $view;

		if ( $printing ) {
			$this->render_print_sheet();
			return;
		}

		$this->maybe_save_preview_pet();
		$this->render_selection();
	}

	/**
	 * Store which pet stands in while the card design is edited.
	 *
	 * The rest of this screen is deliberately read-only and GET-driven, so this
	 * is the one place on it that writes — and therefore the one place that
	 * needs a nonce.
	 */
	private function maybe_save_preview_pet(): void {
		if ( empty( $_POST['petsync_set_preview_pet'] ) ) {
			return;
		}

		check_admin_referer( self::PREVIEW_NONCE );

		// intval, not absint: absint( -17 ) is 17, a real and unrelated pet.
		// The same trap this codebase has already fixed three times.
		$pet_id = isset( $_POST['petsync_preview_pet'] )
			? (int) sanitize_text_field( wp_unslash( $_POST['petsync_preview_pet'] ) )
			: 0;

		if ( $pet_id > 0 && 'vcps_pet' === get_post_type( $pet_id ) ) {
			update_option( \Petsync\Core\Editor_Preview::OPTION, $pet_id );
		} else {
			delete_option( \Petsync\Core\Editor_Preview::OPTION );
		}
	}

	/**
	 * Prompt for the shelter's contact details, if the cards would print without.
	 *
	 * The card used to carry "Add your shelter's phone, email and address here"
	 * as its own text, so the instruction printed on every card until someone
	 * noticed — and on the live site, nobody did. A prompt belongs where the
	 * person who can act on it is standing, not on a card handed to the public.
	 */
	private function render_shelter_details_notice(): void {
		if ( ! class_exists( 'ShelterKit_Profile' ) || \ShelterKit_Profile::has_contact_details() ) {
			return;
		}

		$url = admin_url( 'edit.php?post_type=vcps_pet&page=shelterkit-profile' );
		?>
		<div class="notice notice-info">
			<p>
				<?php esc_html_e( 'These cards will print without your shelter\'s address or phone number.', 'shelterkit-pets' ); ?>
				<a href="<?php echo esc_url( $url ); ?>"><?php esc_html_e( 'Add your shelter details', 'shelterkit-pets' ); ?></a>
				<?php esc_html_e( '— entered once, and they appear on every card.', 'shelterkit-pets' ); ?>
			</p>
		</div>
		<?php
	}

	/**
	 * Choose the pet the Site Editor previews the card with.
	 *
	 * Worth being on this screen rather than in Settings: the pet you preview
	 * and the pets you print are the same list, and the reason to change it is
	 * always something you noticed on a card — a name that wraps, an animal
	 * with no photo, a fee that is blank.
	 *
	 * @param \WP_Post[] $pets Pets currently listed for printing.
	 */
	private function render_preview_picker( array $pets ): void {
		$current = \Petsync\Core\Editor_Preview::preview_pet();
		$stored  = (int) get_option( \Petsync\Core\Editor_Preview::OPTION, 0 );
		?>
		<form method="post" action="" class="petsync-kennel__preview">
			<?php wp_nonce_field( self::PREVIEW_NONCE ); ?>
			<label for="petsync_preview_pet">
				<?php esc_html_e( 'Design the card using:', 'shelterkit-pets' ); ?>
			</label>
			<select name="petsync_preview_pet" id="petsync_preview_pet">
				<option value="0">
					<?php
					printf(
						/* translators: %s: the pet chosen automatically. */
						esc_html__( 'First available pet (%s)', 'shelterkit-pets' ),
						esc_html( $current ? $current->post_title : __( 'none yet', 'shelterkit-pets' ) )
					);
					?>
				</option>
				<?php foreach ( $pets as $pet ) : ?>
					<option value="<?php echo (int) $pet->ID; ?>" <?php selected( $stored, $pet->ID ); ?>>
						<?php echo esc_html( $pet->post_title ); ?>
					</option>
				<?php endforeach; ?>
			</select>
			<button type="submit" name="petsync_set_preview_pet" value="1" class="button">
				<?php esc_html_e( 'Use this pet', 'shelterkit-pets' ); ?>
			</button>
			<p class="description">
				<?php esc_html_e( 'Editing the card design shows this pet\'s details, so you can see what a real card looks like. It changes nothing about what gets printed.', 'shelterkit-pets' ); ?>
				<?php esc_html_e( 'Pick an animal with a photo, a fee and health details if you want to see every field filled in.', 'shelterkit-pets' ); ?>
			</p>
		</form>
		<?php
	}

	/**
	 * Pet picker.
	 */
	private function render_selection(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only filter on a read-only screen.
		$status = isset( $_GET['pet_status'] ) ? sanitize_key( wp_unslash( $_GET['pet_status'] ) ) : 'available';

		$statuses = $this->get_status_options();
		$status   = isset( $statuses[ $status ] ) ? $status : 'available';

		$pets  = $this->get_printable_pets( $status );
		$sizes = $this->get_sizes();
		?>
		<div class="wrap petsync-kennel">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Kennel Cards', 'shelterkit-pets' ); ?></h1>
			<a href="<?php echo esc_url( $this->get_design_edit_url() ); ?>" class="page-title-action">
				<?php esc_html_e( 'Edit card design', 'shelterkit-pets' ); ?>
			</a>

			<p class="description">
				<?php esc_html_e( 'Choose the pets to print. Every card uses the Kennel Card design — edit that once and all cards follow it.', 'shelterkit-pets' ); ?>
			</p>

			<?php $this->render_shelter_details_notice(); ?>

			<?php $this->render_preview_picker( $pets ); ?>

			<?php if ( ! $this->get_card_template() ) : ?>
				<div class="notice notice-error">
					<p><?php esc_html_e( 'The Kennel Card design could not be loaded, so cards cannot be printed.', 'shelterkit-pets' ); ?></p>
				</div>
			<?php endif; ?>

			<form method="get" action="" class="petsync-kennel__filter">
				<input type="hidden" name="post_type" value="vcps_pet" />
				<input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE_SLUG ); ?>" />
				<label for="petsync-status"><?php esc_html_e( 'Show', 'shelterkit-pets' ); ?></label>
				<select name="pet_status" id="petsync-status">
					<?php foreach ( $statuses as $slug => $label ) : ?>
						<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $slug, $status ); ?>>
							<?php echo esc_html( $label ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<button type="submit" class="button"><?php esc_html_e( 'Apply', 'shelterkit-pets' ); ?></button>
			</form>

			<?php if ( ! $pets ) : ?>
				<div class="notice notice-warning">
					<p><?php esc_html_e( 'No pets match that status.', 'shelterkit-pets' ); ?></p>
				</div>
				<?php return; ?>
			<?php endif; ?>

			<p class="petsync-kennel__count">
				<?php
				printf(
					/* translators: %d: number of pets available to print. */
					esc_html( _n( '%d pet', '%d pets', count( $pets ), 'shelterkit-pets' ) ),
					count( $pets )
				);
				?>
			</p>

			<form method="get" action="">
				<input type="hidden" name="post_type" value="vcps_pet" />
				<input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE_SLUG ); ?>" />
				<input type="hidden" name="view" value="print" />

				<p>
					<label for="petsync-card-size"><strong><?php esc_html_e( 'Card size', 'shelterkit-pets' ); ?></strong></label><br />
					<select name="size" id="petsync-card-size">
						<?php foreach ( $sizes as $key => $size ) : ?>
							<option value="<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $size['label'] ); ?></option>
						<?php endforeach; ?>
					</select>
				</p>

				<p>
					<button type="button" class="button" data-petsync-toggle-all>
						<?php esc_html_e( 'Select all / none', 'shelterkit-pets' ); ?>
					</button>
				</p>

				<ul class="petsync-kennel__picker">
					<?php foreach ( $pets as $pet ) : ?>
						<li>
							<label>
								<input type="checkbox" name="pets[]" value="<?php echo esc_attr( (string) $pet->ID ); ?>" />
								<?php if ( has_post_thumbnail( $pet->ID ) ) : ?>
									<?php echo get_the_post_thumbnail( $pet->ID, array( 48, 48 ) ); ?>
								<?php else : ?>
									<span class="petsync-kennel__nophoto" aria-hidden="true"></span>
								<?php endif; ?>
								<span><?php echo esc_html( get_the_title( $pet->ID ) ); ?></span>
							</label>
						</li>
					<?php endforeach; ?>
				</ul>

				<p class="submit">
					<button type="submit" class="button button-primary">
						<?php esc_html_e( 'Print selected', 'shelterkit-pets' ); ?>
					</button>
				</p>
			</form>

			<script>
				document.querySelector( '[data-petsync-toggle-all]' )?.addEventListener( 'click', function () {
					const boxes = document.querySelectorAll( '.petsync-kennel__picker input[type=checkbox]' );
					const target = ! Array.from( boxes ).every( ( b ) => b.checked );
					boxes.forEach( ( b ) => { b.checked = target; } );
				} );
			</script>
		</div>
		<?php
	}

	/**
	 * The printable sheet.
	 */
	private function render_print_sheet(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only screen; no state is changed.
		// intval rather than absint: absint takes the absolute value, so ?pets[]=-5
		// would silently resolve to pet 5 — a real, unrelated animal — instead of
		// being rejected. Same reasoning as the gallery ability's ID handling.
		$ids = isset( $_GET['pets'] ) ? array_map( 'intval', (array) wp_unslash( $_GET['pets'] ) ) : array();
		$ids = array_values( array_filter( $ids, static fn( int $id ): bool => $id > 0 ) );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only screen; no state is changed.
		$size  = isset( $_GET['size'] ) ? sanitize_key( wp_unslash( $_GET['size'] ) ) : 'index';
		$sizes = $this->get_sizes();
		$size  = isset( $sizes[ $size ] ) ? $size : 'index';

		$back = add_query_arg(
			array(
				'post_type' => 'vcps_pet',
				'page'      => self::PAGE_SLUG,
			),
			admin_url( 'edit.php' )
		);
		?>
		<div class="wrap petsync-kennel petsync-kennel--print">
			<p class="petsync-kennel__toolbar">
				<a href="<?php echo esc_url( $back ); ?>" class="button"><?php esc_html_e( '← Choose different pets', 'shelterkit-pets' ); ?></a>
				<button type="button" class="button button-primary" onclick="window.print()"><?php esc_html_e( 'Print', 'shelterkit-pets' ); ?></button>
			</p>

			<?php
			/*
			 * Admin notices are moved by wp-admin/js/common.js, which looks for
			 * `.wp-header-end` and falls back to the first heading inside `.wrap`.
			 * This screen has no <h1>, so the first heading it found was a PET'S
			 * NAME — and the shelter's "some pets may no longer be available"
			 * notice was inserted inside that pet's card, pushing the card's own
			 * content past the bottom of the cell where it was clipped.
			 *
			 * The marker gives the notices somewhere to land. They are hidden in
			 * @media print regardless; this is about the on-screen preview, which
			 * is only useful while it shows what will actually print.
			 */
			?>
			<hr class="wp-header-end" />

			<?php if ( ! $ids ) : ?>
				<div class="notice notice-warning"><p><?php esc_html_e( 'No pets were selected.', 'shelterkit-pets' ); ?></p></div>
				<?php return; ?>
			<?php endif; ?>

			<?php
			// Guard the post type here rather than trusting the query string —
			// the IDs arrive from a GET parameter.
			$printable = array_values(
				array_filter( $ids, static fn( int $id ): bool => 'vcps_pet' === get_post_type( $id ) )
			);

			$per_sheet = max( 1, (int) ( $sizes[ $size ]['per_sheet'] ?? 4 ) );

			// Chunked into explicit pages rather than left as one long grid.
			// On screen each chunk is drawn at real paper size, so the preview
			// shows exactly where the sheets divide and how much room a card
			// actually has — the difference between the two was what hid the
			// clipped photos and footers until someone opened the print dialog.
			// In print each chunk simply breaks after itself, which makes
			// pagination deterministic instead of something the browser infers.
			foreach ( array_chunk( $printable, $per_sheet ) as $sheet ) :
				?>
				<div class="petsync-cards__page">
					<div class="petsync-cards petsync-cards--<?php echo esc_attr( $size ); ?>">
						<?php
						foreach ( $sheet as $id ) {
							echo '<div class="petsync-cards__cell">';
							echo $this->render_card( $id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- rendered blocks, escaped by the block renderer.
							echo '</div>';
						}
						?>
					</div>
				</div>
				<?php
			endforeach;
			?>
		</div>
		<?php
	}

	// === Rendering ===

	/**
	 * Render one pet through the kennel-card template part.
	 *
	 * The bindings source falls back to get_the_ID(), so establishing the post
	 * context is all that is needed for every bound field to resolve.
	 *
	 * @param int $pet_id Pet post ID.
	 * @return string Rendered HTML.
	 */
	private function render_card( int $pet_id ): string {
		$template = $this->get_card_template();

		if ( ! $template || '' === trim( (string) $template->content ) ) {
			return '';
		}

		global $post;
		$original = $post;

		$post = get_post( $pet_id ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- restored below.
		setup_postdata( $post );

		$html = do_blocks( $template->content );

		// Restore whatever the caller had, rather than whatever the main query
		// holds. wp_reset_postdata() re-reads $wp_query, so calling it after
		// putting $post back would discard the restore any time the two differ
		// — which is exactly the situation inside a secondary loop.
		$post = $original; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- restoring.

		if ( $original instanceof WP_Post ) {
			// Also restores $authordata, $pages and friends, which
			// setup_postdata() set on the way in.
			setup_postdata( $original );
		} else {
			wp_reset_postdata();
		}

		return $html;
	}

	/**
	 * The kennel-card part, preferring a Site Editor customization.
	 *
	 * Uses the plural API because that is what Petsync_Templates filters,
	 * and its handler already resolves a user's edit before falling back to the
	 * file shipped with the plugin.
	 *
	 * @return WP_Block_Template|null
	 */
	private function get_card_template(): ?WP_Block_Template {
		$found = get_block_templates( array( 'slug__in' => array( self::PART_SLUG ) ), 'wp_template_part' );

		foreach ( $found as $template ) {
			if ( self::PART_SLUG === $template->slug ) {
				return $template;
			}
		}

		return null;
	}

	/**
	 * Deep link into the Site Editor, on the card design itself.
	 *
	 * Drops the user straight into the editing canvas rather than the Site
	 * Editor's browsing UI, which is the whole reason a dedicated screen can
	 * use template parts without the Site Editor being in the way.
	 *
	 * @return string Admin URL.
	 */
	private function get_design_edit_url(): string {
		$template = $this->get_card_template();
		$id       = $template->id ?? ( get_stylesheet() . '//' . self::PART_SLUG );

		return add_query_arg(
			array(
				'postType' => 'wp_template_part',
				'postId'   => rawurlencode( $id ),
				'canvas'   => 'edit',
			),
			admin_url( 'site-editor.php' )
		);
	}

	/**
	 * Pets worth offering to print.
	 *
	 * Defaults to whatever the archive considers current, because a kennel card
	 * is for an animal presently in a kennel — printing the whole history is
	 * never what anyone wants, and with a few hundred pets an unfiltered list
	 * is unusable.
	 *
	 * @param string $status Status term slug, or 'all'.
	 * @return WP_Post[]
	 */
	private function get_printable_pets( string $status = 'available' ): array {
		$args = array(
			'post_type'      => 'vcps_pet',
			'post_status'    => 'publish',
			'posts_per_page' => 500,
			'orderby'        => 'title',
			'order'          => 'ASC',
		);

		if ( 'all' !== $status ) {
			// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- an admin screen filtering by status.
			$args['tax_query'] = array(
				array(
					'taxonomy' => 'pet_status',
					'field'    => 'slug',
					'terms'    => array( $status ),
				),
			);
		}

		return get_posts( $args );
	}

	/**
	 * Status terms actually in use, for the filter.
	 *
	 * @return array<string, string> Slug => label.
	 */
	private function get_status_options(): array {
		$options = array( 'all' => __( 'All statuses', 'shelterkit-pets' ) );

		$terms = get_terms(
			array(
				'taxonomy'   => 'pet_status',
				'hide_empty' => true,
			)
		);

		if ( ! is_wp_error( $terms ) ) {
			foreach ( $terms as $term ) {
				$options[ $term->slug ] = sprintf( '%s (%d)', $term->name, $term->count );
			}
		}

		return $options;
	}
}
