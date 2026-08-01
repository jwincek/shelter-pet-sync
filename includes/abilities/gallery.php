<?php
/**
 * Gallery ability callbacks.
 *
 * The first editorial write ability in the plugin — everything else that writes
 * (favorites, comparison) is visitor-scoped session state. Setting a pet's
 * gallery is a content operation, so it sits behind `edit_posts` and gives
 * automation the same capability the editor sidebar has.
 *
 * @package Petstablished_Sync
 * @since 1.0.0
 */

declare( strict_types = 1 );

namespace Petstablished\Abilities\Gallery;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Petstablished\Core\Config;
use Petstablished\Core\Pet_Hydrator;
use WP_Error;

/**
 * Replace a pet's hand-curated gallery.
 *
 * Storage is the same `_pet_gallery_ids` meta the editor writes, so the two
 * paths cannot drift. Passing an empty array clears the manual gallery, which
 * for an imported pet means its provider images become visible again.
 *
 * @param array $input {
 *     @type int   $id             Pet post ID.
 *     @type int[] $attachment_ids Attachment IDs, in display order.
 * }
 * @return array|WP_Error Updated gallery, or an error.
 */
function set_gallery( array $input ): array|WP_Error {
	$id = absint( $input['id'] ?? 0 );

	if ( ! $id || 'vcps_pet' !== get_post_type( $id ) ) {
		return new WP_Error(
			'invalid_pet',
			__( 'No pet found with that ID.', 'shelter-pets' ),
			[ 'status' => 404 ]
		);
	}

	// The ability's own permission covers edit_posts in general; this covers
	// the specific post, which is what matters when roles are restricted.
	if ( ! current_user_can( 'edit_post', $id ) ) {
		return new WP_Error(
			'cannot_edit_pet',
			__( 'You are not allowed to edit this pet.', 'shelter-pets' ),
			[ 'status' => 403 ]
		);
	}

	$requested = is_array( $input['attachment_ids'] ?? null ) ? $input['attachment_ids'] : [];
	$valid     = [];
	$skipped   = [];

	foreach ( $requested as $attachment_id ) {
		// intval rather than absint: absint takes the absolute value, so -5
		// would silently resolve to attachment 5 — a real, unrelated image
		// rather than a rejected input.
		$attachment_id = (int) $attachment_id;

		if ( $attachment_id <= 0 ) {
			continue;
		}

		// Only real image attachments. Anything else would render as a broken
		// image on the front end, so it is reported back rather than stored.
		if ( wp_attachment_is_image( $attachment_id ) ) {
			$valid[] = $attachment_id;
		} else {
			$skipped[] = $attachment_id;
		}
	}

	$config = Config::get_path( 'entities', 'entities.vcps_pet', [] );
	$prefix = $config['meta_prefix'] ?? '_pet_';

	if ( $valid ) {
		update_post_meta( $id, $prefix . 'gallery_ids', $valid );
	} else {
		delete_post_meta( $id, $prefix . 'gallery_ids' );
	}

	// The hydrator memoises per request; without this the response would
	// describe the gallery as it was before this call.
	Pet_Hydrator::flush_cache();
	$pet = Pet_Hydrator::get( $id, 'full' );

	return [
		'id'      => $id,
		'gallery' => $pet['gallery'] ?? [],
		'count'   => count( $pet['gallery'] ?? [] ),
		'skipped' => $skipped,
	];
}
