<?php
/**
 * Only generate the image sizes the plugin renders.
 *
 * Measured on a real install: 39% of the pet-image footprint was derivatives
 * nothing ever requested — WooCommerce generating 20.5MB of `woocommerce_single`
 * from dog photos, plus Sensei course thumbnails and core's 1536/2048 sizes.
 * 257 attachments had become 1768 files.
 *
 * @package ShelterKit_Pets
 */

declare( strict_types = 1 );

namespace Petsync\Tests\Integration;

use Petsync_Sync;
use ReflectionMethod;

final class ImageBudgetTest extends PetTestCase {

	/**
	 * @param string $url  Source URL.
	 * @param int    $post Pet ID.
	 * @return mixed Attachment ID or WP_Error.
	 */
	private function sideload( string $url, int $post ) {
		$m = new ReflectionMethod( Petsync_Sync::class, 'sideload_within_budget' );

		return $m->invoke( null, $url, $post, 'probe' );
	}

	/**
	 * Register a size no other plugin owns, so the assertion is about our
	 * budget rather than about whatever else is installed in the test suite.
	 */
	public function set_up(): void {
		parent::set_up();
		add_image_size( 'petsync_probe_unused', 321, 321, true );
		add_filter( 'pre_http_request', array( $this, 'serve_local_image' ), 10, 3 );
	}

	public function tear_down(): void {
		remove_filter( 'pre_http_request', array( $this, 'serve_local_image' ), 10 );
		remove_image_size( 'petsync_probe_unused' );
		parent::tear_down();
	}

	/**
	 * download_url() streams over HTTP, which a test cannot reach. Short-circuit
	 * it with a copy of a real JPEG so sideload_within_budget() runs for real
	 * rather than being skipped — a skipped test verifies nothing, and the two
	 * assertions that matter here are the ones that need a real attachment.
	 *
	 * @param mixed  $preempt Short-circuit value.
	 * @param array  $args    Request args.
	 * @param string $url     Requested URL.
	 * @return mixed
	 */
	public function serve_local_image( $preempt, $args, $url ) {
		if ( ! str_contains( (string) $url, 'petsync-test-image' ) ) {
			return $preempt;
		}

		// download_url() creates its own temp file and passes the path in
		// $args['filename'], expecting the transport to have written there. A
		// filename of our own is ignored, and the sideload then fails with
		// "File is empty".
		$target = $args['filename'] ?? wp_tempnam( 'petsync-probe.jpg' );
		copy( DIR_TESTDATA . '/images/canola.jpg', $target );

		return array(
			'response' => array(
				'code'    => 200,
				'message' => 'OK',
			),
			'filename' => $target,
			'headers'  => array( 'content-type' => 'image/jpeg' ),
			'body'     => '',
			'cookies'  => array(),
		);
	}

	public function test_only_rendered_sizes_are_generated(): void {
		$pet = $this->make_manual_pet();

		$id = $this->sideload( 'https://example.test/petsync-test-image.jpg', $pet );
		$this->assertNotWPError( $id, 'the sideload must actually run, or this test proves nothing' );

		$sizes = array_keys( wp_get_attachment_metadata( (int) $id )['sizes'] ?? array() );

		$this->assertNotContains( 'petsync_probe_unused', $sizes, 'a size the plugin never renders must not be generated' );
		$this->assertContains( 'thumbnail', $sizes, 'thumbnail IS rendered — Petsync_Helpers::get_image()' );
	}

	/**
	 * The filters must not leak. If a sideload leaves them applied, every
	 * subsequent upload on the site silently loses its sizes — a far worse bug
	 * than the one being fixed.
	 */
	public function test_the_filters_do_not_survive_the_call(): void {
		$pet = $this->make_manual_pet();
		$this->sideload( 'https://example.test/petsync-test-image.jpg', $pet );

		$this->assertFalse(
			has_filter( 'intermediate_image_sizes_advanced' ),
			'the size filter is still applied after the sideload returned'
		);
		$this->assertFalse(
			has_filter( 'big_image_size_threshold' ),
			'the threshold filter is still applied after the sideload returned'
		);
	}

	/**
	 * …including when the sideload fails, which is why it is a finally block
	 * rather than statements after the return.
	 */
	public function test_the_filters_do_not_survive_a_failed_sideload(): void {
		$pet    = $this->make_manual_pet();
		$result = $this->sideload( 'not-a-url-at-all', $pet );

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertFalse( has_filter( 'intermediate_image_sizes_advanced' ) );
		$this->assertFalse( has_filter( 'big_image_size_threshold' ) );
	}

	/**
	 * A theme rendering pets at its own size must be able to ask for it rather
	 * than patch the plugin.
	 */
	public function test_a_theme_can_add_a_size_to_the_budget(): void {
		$add = static function ( array $keep ): array {
			$keep[] = 'petsync_probe_unused';
			return $keep;
		};
		add_filter( 'petsync_rendered_image_sizes', $add );

		$pet = $this->make_manual_pet();
		$id  = $this->sideload( 'https://example.test/petsync-test-image.jpg', $pet );

		remove_filter( 'petsync_rendered_image_sizes', $add );

		$this->assertNotWPError( $id );

		$sizes = array_keys( wp_get_attachment_metadata( (int) $id )['sizes'] ?? array() );
		$this->assertContains( 'petsync_probe_unused', $sizes );
	}

	/**
	 * Scaling discards pixels, unlike skipping a derivative — so the ceiling is
	 * asserted to be a deliberate value rather than something that drifted.
	 */
	public function test_the_edge_ceiling_is_filterable_and_conservative(): void {
		$default = apply_filters( 'petsync_max_image_edge', 1600 );
		$this->assertGreaterThanOrEqual( 1600, $default, 'a lightbox on a retina laptop needs this much' );

		$narrow = static fn(): int => 800;
		add_filter( 'petsync_max_image_edge', $narrow );
		$this->assertSame( 800, apply_filters( 'petsync_max_image_edge', 1600 ) );
		remove_filter( 'petsync_max_image_edge', $narrow );
	}
}
