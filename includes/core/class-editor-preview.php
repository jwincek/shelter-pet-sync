<?php
/**
 * A pet to stand in for "the pet" while designing in the editor.
 *
 * Editing the kennel-card template part means editing a design that has no
 * subject: there is no queried pet, so every bound field and every pet block
 * renders its nothing-to-show branch and the card comes out blank. You end up
 * designing against empty boxes, and an inserted block looks the same whether
 * it resolved or not.
 *
 * The obvious fix does not work. Core's block-renderer endpoint accepts only
 * `post_id`, cast to (int), and no block context at all — see
 * class-wp-rest-block-renderer-controller.php:114. A template part's postId is
 * a string like `theme//kennel-card`, which casts to 0. So the server cannot
 * detect the template-part context from the block's context, because it is
 * never sent one.
 *
 * What it can see is the route. A `/wp/v2/block-renderer/petsync/…` request
 * with no `post_id` is an editor preview with no post, which is exactly the
 * case worth filling in. Setting the global post once for that request is
 * enough: every pet block and binding already falls back to get_the_ID(), so
 * they all resolve without ~20 render.php files each learning a new rule.
 *
 * @package ShelterKit_Pets
 * @since   1.2.0
 */

declare( strict_types = 1 );

namespace Petsync\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Editor_Preview {

	/**
	 * Chosen preview pet. 0 means "whichever pet the Kennel Cards screen would
	 * list first", so the pet you preview is the pet you would print.
	 */
	public const OPTION = 'petsync_preview_pet_id';

	/**
	 * Only routes under this prefix are treated as previews, so no core or
	 * third-party block's preview is affected by any of this.
	 */
	private const ROUTE_PREFIX = '/wp/v2/block-renderer/petsync/';

	/**
	 * Set during the preview request only, so the teardown restores exactly what
	 * it replaced rather than guessing.
	 *
	 * @var \WP_Post|null
	 */
	private static ?\WP_Post $previous_post = null;

	private static bool $active = false;

	public static function register(): void {
		add_filter( 'rest_pre_dispatch', array( self::class, 'maybe_set_preview_post' ), 10, 3 );

		// rest_request_after_callbacks, NOT rest_post_dispatch. The latter fires
		// in WP_REST_Server::serve_request(), which an internal
		// rest_do_request() never reaches — so the pet would stay in the global
		// post for the rest of that process. Both of these fire inside
		// dispatch(), so they pair correctly however the request arrived.
		add_filter( 'rest_request_after_callbacks', array( self::class, 'restore_post' ), 10, 3 );
	}

	/**
	 * Whether a request is a pet-block preview with no post of its own.
	 *
	 * @param \WP_REST_Request $request The request.
	 */
	private static function is_pet_block_preview( \WP_REST_Request $request ): bool {
		if ( ! str_starts_with( $request->get_route(), self::ROUTE_PREFIX ) ) {
			return false;
		}

		// A post editor sends the post it is editing. Only the contexts that
		// have no post — the Site Editor's templates and template parts — are
		// missing it, and those are the ones worth standing in for. Casting the
		// same way the controller does, so a string id reads as absent here too.
		return (int) $request->get_param( 'post_id' ) < 1;
	}

	/**
	 * Stand a real pet in for the missing one, for this request only.
	 *
	 * @param mixed            $result  Response to replace the requested version with.
	 * @param \WP_REST_Server  $server  Server instance.
	 * @param \WP_REST_Request $request The request.
	 * @return mixed Untouched — this filter is used as a hook, not to short-circuit.
	 */
	public static function maybe_set_preview_post( $result, $server, $request ) {
		if ( null !== $result || ! self::is_pet_block_preview( $request ) ) {
			return $result;
		}

		$pet = self::preview_pet();
		if ( ! $pet ) {
			return $result;
		}

		global $post;

		self::$previous_post = $post instanceof \WP_Post ? $post : null;
		self::$active        = true;

		$post = $pet; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- restored on rest_request_after_callbacks.
		setup_postdata( $post );

		return $result;
	}

	/**
	 * Put back whatever was there. A REST request can dispatch more than one
	 * handler, so leaving a pet in the global post would leak into whatever
	 * runs next.
	 *
	 * @param mixed                                            $response Response.
	 * @param array{callback: callable}|array<string, mixed>   $handler  Route handler.
	 * @param \WP_REST_Request                                 $request  Request.
	 * @return mixed Untouched.
	 */
	public static function restore_post( $response, $handler, $request ) {
		if ( ! self::$active ) {
			return $response;
		}

		global $post;

		$post = self::$previous_post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- restoring.

		if ( self::$previous_post instanceof \WP_Post ) {
			setup_postdata( self::$previous_post );
		} else {
			wp_reset_postdata();
		}

		self::$previous_post = null;
		self::$active        = false;

		return $response;
	}

	/**
	 * The pet to preview with.
	 *
	 * A stored choice if it still resolves to a published pet, otherwise the
	 * first pet the Kennel Cards screen would offer — so "the pet I preview"
	 * and "the first pet I would print" are the same animal, and the preview
	 * does not silently follow a pet that has been adopted and unpublished.
	 */
	public static function preview_pet(): ?\WP_Post {
		$chosen = (int) get_option( self::OPTION, 0 );

		if ( $chosen > 0 ) {
			$post = get_post( $chosen );
			if ( $post instanceof \WP_Post && 'vcps_pet' === $post->post_type && 'publish' === $post->post_status ) {
				return $post;
			}
		}

		return self::default_preview_pet();
	}

	/**
	 * The first pet the Kennel Cards screen lists, matching its own ordering.
	 */
	private static function default_preview_pet(): ?\WP_Post {
		$found = get_posts(
			array(
				'post_type'      => 'vcps_pet',
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'orderby'        => 'title',
				'order'          => 'ASC',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- one row, admin-side preview only.
				'tax_query'      => array(
					array(
						'taxonomy' => 'pet_status',
						'field'    => 'slug',
						'terms'    => array( 'available' ),
					),
				),
			)
		);

		if ( $found ) {
			return $found[0];
		}

		// A shelter with nothing available should still be able to design the
		// card, so fall back to any published pet before giving up.
		$any = get_posts(
			array(
				'post_type'      => 'vcps_pet',
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);

		return $any ? $any[0] : null;
	}
}
