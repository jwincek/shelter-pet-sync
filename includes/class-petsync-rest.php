<?php
/**
 * Thin REST routes for client-facing ability execution.
 *
 * The WP 6.9 core Abilities REST API (/wp-abilities/v1/) requires an
 * authenticated user for ALL endpoints. This blocks anonymous front-end
 * visitors who need to toggle favorites and manage pet comparisons.
 *
 * This class registers plugin-scoped REST routes at:
 *   /petsync/v1/{namespace}/{ability}/run
 *
 * Each route delegates to the registered ability. The ability's own
 * permission_callback still runs — we only bypass the core controller's
 * authentication gate, not the per-ability authorization.
 *
 * Follows the WP 6.9 Abilities REST conventions:
 * - POST input is wrapped as { "input": { ... } }
 * - GET input is passed as URL-encoded `input` query parameter
 * - Endpoint path ends in /run (matching core pattern)
 *
 * @package ShelterKit_Pets
 * @since   1.0.0
 */

declare( strict_types = 1 );

class Petsync_REST {

	/**
	 * Abilities to expose via plugin REST routes.
	 *
	 * Only abilities called from client-side Interactivity stores need
	 * routes here. Server-only abilities (like filter-pets) do not.
	 *
	 * @var string[]
	 */
	private const CLIENT_ABILITIES = [
		'petsync/toggle-favorite',
		'petsync/get-favorites',
		'petsync/clear-favorites',
		'petsync/update-comparison',
		'petsync/get-comparison',
	];

	/**
	 * Register REST routes.
	 */
	public static function register_routes(): void {
		foreach ( self::CLIENT_ABILITIES as $ability_name ) {
			// Route: petsync/v1/petsync/toggle-favorite/run
			$route = $ability_name . '/run';

			register_rest_route(
				'petsync/v1',
				$route,
				[
					[
						'methods'             => \WP_REST_Server::CREATABLE,
						'callback'            => [ __CLASS__, 'handle_execute' ],
						'permission_callback' => [ __CLASS__, 'check_permission' ],
						'args'                => [
							'_ability' => [
								'type'    => 'string',
								'default' => $ability_name,
							],
							'input'    => [
								'required' => false,
								'default'  => null,
							],
						],
					],
					[
						'methods'             => \WP_REST_Server::READABLE,
						'callback'            => [ __CLASS__, 'handle_execute' ],
						'permission_callback' => [ __CLASS__, 'check_permission' ],
						'args'                => [
							'_ability' => [
								'type'    => 'string',
								'default' => $ability_name,
							],
							'input'    => [
								'required' => false,
								'default'  => null,
							],
						],
					],
				]
			);
		}
	}

	/**
	 * Permission check — delegates to the ability's own permission_callback.
	 *
	 * Some abilities routed through here are deliberately public: favorites and
	 * comparison are visitor features that must work for anonymous users, so
	 * their permission callback returns true rather than requiring a login.
	 *
	 * That combination — a writable route whose permission is effectively
	 * always-true — is the shape a CSRF review looks for, so the reasoning is
	 * recorded here rather than left to be re-derived.
	 *
	 * There is no cross-site write path to a logged-in user's data:
	 *
	 * 1. Those abilities persist through Petsync_Helpers, which writes user
	 *    meta ONLY when get_current_user_id() is non-zero, and otherwise sets a
	 *    cookie in the caller's own browser.
	 * 2. Core's rest_cookie_check_errors() (wp-includes/rest-api.php) rejects a
	 *    cookie-authenticated REST request carrying an INVALID nonce with a 403,
	 *    and for a request carrying NO nonce it calls wp_set_current_user( 0 )
	 *    and continues — its own comment reads "No nonce at all, so act as if
	 *    it's an unauthenticated request."
	 *
	 * So a forged cross-site request never reaches the user-meta branch: it
	 * either fails outright or is downgraded to anonymous, where the only thing
	 * it can affect is a cookie on the victim's own browser. Adding a nonce
	 * check here would not close a hole; it would break anonymous favorites,
	 * which are a feature rather than an oversight.
	 *
	 * Abilities that write CONTENT rather than visitor state are a different
	 * matter and carry real capability checks — see petsync/set-pet-gallery,
	 * which requires edit_posts plus edit_post on the specific pet.
	 *
	 * @param \WP_REST_Request $request
	 * @return bool|\WP_Error
	 */
	public static function check_permission( \WP_REST_Request $request ) {
		$ability = self::resolve_ability( $request );
		if ( is_wp_error( $ability ) ) {
			return $ability;
		}

		$input = self::get_input( $request );

		return $ability->check_permissions( $input );
	}

	/**
	 * Execute the ability and return the result.
	 *
	 * @param \WP_REST_Request $request
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function handle_execute( \WP_REST_Request $request ) {
		$ability = self::resolve_ability( $request );
		if ( is_wp_error( $ability ) ) {
			return $ability;
		}

		$input  = self::get_input( $request );
		$result = $ability->execute( $input );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response( $result );
	}

	/**
	 * Resolve the ability instance from the request.
	 *
	 * @param \WP_REST_Request $request
	 * @return \WP_Ability|\WP_Error
	 */
	private static function resolve_ability( \WP_REST_Request $request ) {
		$name = $request->get_param( '_ability' );

		if ( ! $name || ! in_array( $name, self::CLIENT_ABILITIES, true ) ) {
			return new \WP_Error(
				'rest_ability_not_found',
				__( 'Ability not found.', 'shelterkit-pets' ),
				[ 'status' => 404 ]
			);
		}

		if ( ! function_exists( 'wp_get_ability' ) ) {
			return new \WP_Error(
				'abilities_unavailable',
				__( 'Abilities API is not available.', 'shelterkit-pets' ),
				[ 'status' => 501 ]
			);
		}

		$ability = wp_get_ability( $name );
		if ( ! $ability ) {
			return new \WP_Error(
				'rest_ability_not_found',
				sprintf( /* translators: %s: ability name */ __( 'Ability "%s" is not registered.', 'shelterkit-pets' ), $name ),
				[ 'status' => 404 ]
			);
		}

		return $ability;
	}

	/**
	 * Extract ability input from the request.
	 *
	 * Follows core Abilities REST conventions:
	 * - POST: input is in the `input` key of the JSON body
	 * - GET: input is a URL-encoded `input` query parameter
	 *
	 * @param \WP_REST_Request $request
	 * @return mixed
	 */
	private static function get_input( \WP_REST_Request $request ) {
		$input = $request->get_param( 'input' );

		// GET requests may send input as URL-encoded JSON string.
		if ( is_string( $input ) ) {
			$decoded = json_decode( $input, true );
			if ( json_last_error() === JSON_ERROR_NONE ) {
				return $decoded;
			}
		}

		// Abilities with input_schema type:object fail validation on null.
		// Return empty array (≡ empty object) when no input is provided.
		return $input ?? [];
	}
}
