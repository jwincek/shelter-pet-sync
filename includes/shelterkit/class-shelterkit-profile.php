<?php
/**
 * Who the shelter is.
 *
 * SHARED FILE — byte-identical in every ShelterKit plugin. Loaded by
 * ShelterKit_Profile_Versions, which picks the highest copy present. Edit here,
 * then copy across; do not namespace it and do not rename it.
 *
 * Until this existed, nothing in the family stored a shelter's own name, phone
 * or address. The kennel card carried the contact line as literal text in its
 * design, which is why the shipped placeholder — "Add your shelter's phone,
 * email and address here" — reached production and printed on real cards handed
 * to the public. The card's org name never had that problem, because it came
 * from wp:site-title: it was data, not a string in a layout.
 *
 * One option, shared. Pets renders the screen if it is the newest copy; Events
 * or Donations do the same if they are. Any one of them works installed alone.
 *
 * @package ShelterKit
 * @version 1.0.0
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'ShelterKit_Profile' ) ) {

	class ShelterKit_Profile {

		public const VERSION = '1.0.0';

		/**
		 * Deliberately not prefixed with any one plugin's name. The profile
		 * belongs to the shelter, not to Pets — and an option name is stored
		 * data, so this cannot be changed later without a migration in every
		 * plugin that reads it.
		 */
		public const OPTION = 'shelterkit_organization';

		private const CAPABILITY = 'manage_options';
		private const NONCE      = 'shelterkit_profile_save';

		/**
		 * Field => sanitiser. The list IS the schema: everything else here
		 * iterates it, so adding a field is one line.
		 *
		 * Deliberately omitted for now: opening hours and geo coordinates.
		 * Both want a structured shape rather than a string — schema.org's
		 * openingHoursSpecification is a list of day/time objects — and
		 * inventing that shape before the consumer exists risks storing
		 * something #67 then has to migrate. A text field would be worse than
		 * nothing: it would look answered.
		 *
		 * @return array<string, callable>
		 */
		public static function fields(): array {
			return array(
				'name'           => 'sanitize_text_field',
				'street_address' => 'sanitize_text_field',
				'locality'       => 'sanitize_text_field',
				'region'         => 'sanitize_text_field',
				'postal_code'    => 'sanitize_text_field',
				'country'        => 'sanitize_text_field',
				'phone'          => 'sanitize_text_field',
				'email'          => 'sanitize_email',
				'url'            => 'esc_url_raw',
			);
		}

		/**
		 * Human labels, kept beside the schema so a new field cannot be added
		 * without one.
		 *
		 * @return array<string, string>
		 */
		public static function labels(): array {
			return array(
				'name'           => __( 'Shelter name', 'shelterkit-pets' ),
				'street_address' => __( 'Street address', 'shelterkit-pets' ),
				'locality'       => __( 'Town or city', 'shelterkit-pets' ),
				'region'         => __( 'State or county', 'shelterkit-pets' ),
				'postal_code'    => __( 'Postal code', 'shelterkit-pets' ),
				'country'        => __( 'Country', 'shelterkit-pets' ),
				'phone'          => __( 'Phone', 'shelterkit-pets' ),
				'email'          => __( 'Email', 'shelterkit-pets' ),
				'url'            => __( 'Website', 'shelterkit-pets' ),
			);
		}

		/**
		 * The whole profile, every declared field present as a string.
		 *
		 * Never returns a partial array: a caller reading ['phone'] on a shelter
		 * that has not filled it in gets '' rather than a notice.
		 *
		 * @return array<string, string>
		 */
		public static function all(): array {
			$stored = get_option( self::OPTION, array() );
			$stored = is_array( $stored ) ? $stored : array();

			$out = array();
			foreach ( array_keys( self::fields() ) as $field ) {
				$out[ $field ] = isset( $stored[ $field ] ) ? (string) $stored[ $field ] : '';
			}

			// The shelter's name falls back to the site title, which is what the
			// kennel card already used and is right far more often than blank.
			if ( '' === $out['name'] ) {
				$out['name'] = (string) get_bloginfo( 'name' );
			}

			return $out;
		}

		/**
		 * One field.
		 *
		 * @param string $field Field name.
		 */
		public static function get( string $field ): string {
			return self::all()[ $field ] ?? '';
		}

		/**
		 * Whether enough is filled in to be worth showing.
		 *
		 * The name alone does not count — it falls back to the site title, so it
		 * is never empty and would make this always true.
		 */
		public static function has_contact_details(): bool {
			foreach ( array( 'street_address', 'phone', 'email' ) as $field ) {
				if ( '' !== self::get( $field ) ) {
					return true;
				}
			}
			return false;
		}

		/**
		 * A one-line address, skipping whatever is missing.
		 *
		 * Built here rather than in each consumer so a shelter that fills in
		 * only a town does not get stray commas on its kennel cards.
		 */
		public static function address_line(): string {
			$profile = self::all();
			$parts   = array_filter(
				array(
					$profile['street_address'],
					$profile['locality'],
					trim( $profile['region'] . ' ' . $profile['postal_code'] ),
					$profile['country'],
				),
				static fn( string $p ): bool => '' !== trim( $p )
			);

			return implode( ', ', $parts );
		}

		/**
		 * Save, sanitising per field. Unknown keys are dropped rather than
		 * stored, so a stray form field cannot accumulate in the option.
		 *
		 * Expects ALREADY-UNSLASHED input: superglobals are unslashed where they
		 * are read, so this takes ordinary data and can be called from a CLI
		 * command or an importer without double-processing.
		 *
		 * @param array<string, mixed> $input Unslashed input.
		 */
		public static function save( array $input ): void {
			$clean = array();

			foreach ( self::fields() as $field => $sanitiser ) {
				if ( ! isset( $input[ $field ] ) ) {
					continue;
				}
				$clean[ $field ] = call_user_func( $sanitiser, $input[ $field ] );
			}

			update_option( self::OPTION, $clean );
		}

		// ─── Admin screen ───────────────────────────────────────────────────

		/**
		 * Register the screen under a parent menu the host plugin provides.
		 *
		 * @param string $parent_slug Parent menu slug.
		 */
		public static function add_settings_page( string $parent_slug ): void {
			add_submenu_page(
				$parent_slug,
				__( 'Shelter Details', 'shelterkit-pets' ),
				__( 'Shelter Details', 'shelterkit-pets' ),
				self::CAPABILITY,
				'shelterkit-profile',
				array( self::class, 'render_page' )
			);
		}

		public static function render_page(): void {
			if ( ! current_user_can( self::CAPABILITY ) ) {
				wp_die( esc_html__( 'You are not allowed to edit the shelter details.', 'shelterkit-pets' ) );
			}

			$saved = false;

			if ( ! empty( $_POST['shelterkit_profile_submit'] ) ) {
				check_admin_referer( self::NONCE );

				// Unslashed here, at the point the superglobal is read, rather
				// than inside save() — which therefore takes ordinary data and
				// can be called from anywhere.
				// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- save() sanitises per field against its own schema.
				$posted = isset( $_POST['shelterkit_profile'] ) ? (array) wp_unslash( $_POST['shelterkit_profile'] ) : array();

				self::save( $posted );
				$saved = true;
			}

			$profile = self::all();
			$labels  = self::labels();
			?>
			<div class="wrap">
				<h1><?php esc_html_e( 'Shelter Details', 'shelterkit-pets' ); ?></h1>

				<p class="description">
					<?php esc_html_e( 'Entered once and used everywhere the shelter identifies itself — printed kennel cards, and anything else in the ShelterKit family that needs an address.', 'shelterkit-pets' ); ?>
				</p>

				<?php if ( $saved ) : ?>
					<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Shelter details saved.', 'shelterkit-pets' ); ?></p></div>
				<?php endif; ?>

				<form method="post" action="">
					<?php wp_nonce_field( self::NONCE ); ?>
					<table class="form-table" role="presentation">
						<?php foreach ( $labels as $field => $label ) : ?>
							<tr>
								<th scope="row">
									<label for="shelterkit_<?php echo esc_attr( $field ); ?>"><?php echo esc_html( $label ); ?></label>
								</th>
								<td>
									<input
										type="<?php echo 'email' === $field ? 'email' : ( 'url' === $field ? 'url' : 'text' ); ?>"
										id="shelterkit_<?php echo esc_attr( $field ); ?>"
										name="shelterkit_profile[<?php echo esc_attr( $field ); ?>]"
										value="<?php echo esc_attr( $profile[ $field ] ); ?>"
										class="regular-text"
									>
									<?php if ( 'name' === $field ) : ?>
										<p class="description"><?php esc_html_e( 'Leave blank to use the site title.', 'shelterkit-pets' ); ?></p>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</table>

					<?php submit_button( __( 'Save shelter details', 'shelterkit-pets' ), 'primary', 'shelterkit_profile_submit' ); ?>
				</form>
			</div>
			<?php
		}
	}
}
