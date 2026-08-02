<?php
/**
 * Shared base for pet integration tests.
 *
 * @package Shelter_Pets
 */

declare( strict_types = 1 );

namespace Petsync\Tests\Integration;

use Petsync\Core\CPT_Registry;
use Petsync\Core\Pet_Hydrator;
use WP_UnitTestCase;

abstract class PetTestCase extends WP_UnitTestCase {

	protected string $prefix = '_pet_';

	public function set_up(): void {
		parent::set_up();

		// WP_UnitTestCase::set_up() calls reset_post_types(), which
		// _unregister_post_type()s every non-core type — and
		// unregister_post_type() drops that type's registered meta with it
		// (see wp-includes/post.php, unregister_meta_key). `init` does not
		// fire again, so without re-registering here the post type and all
		// its meta vanish after the first test, taking the sanitize callbacks
		// and REST schemas with them. The symptom is confusing: reads and
		// writes still work, because get/update_post_meta do not require
		// registration — only sanitisation silently stops happening.
		CPT_Registry::register_post_types();
		CPT_Registry::register_taxonomies();
		CPT_Registry::register_meta();

		// Hydration is memoised per request. A test writes and reads back
		// within one request, so every test starts from a clean slate.
		Pet_Hydrator::flush_cache();
	}

	/**
	 * A pet with no provider — as if created by hand in the editor.
	 *
	 * @param array<string, mixed> $args Overrides for wp_insert_post.
	 * @return int Post ID.
	 */
	protected function make_manual_pet( array $args = array() ): int {
		return self::factory()->post->create(
			array_merge(
				array(
					'post_type'   => 'vcps_pet',
					'post_status' => 'publish',
					'post_title'  => 'Manual Pet',
				),
				$args
			)
		);
	}

	/**
	 * A pet as the sync would leave it: provider, record ID, and a snapshot.
	 *
	 * @param array<string, mixed> $api   Snapshot payload.
	 * @param array<string, mixed> $args  Overrides for wp_insert_post.
	 * @return int Post ID.
	 */
	protected function make_synced_pet( array $api = array(), array $args = array() ): int {
		$api = array_merge(
			array(
				'id'   => 900001,
				'name' => 'Synced Pet',
			),
			$api
		);

		$id = self::factory()->post->create(
			array_merge(
				array(
					'post_type'   => 'vcps_pet',
					'post_status' => 'publish',
					'post_title'  => $api['name'],
				),
				$args
			)
		);

		update_post_meta( $id, $this->prefix . 'ps_id', (string) $api['id'] );
		update_post_meta( $id, $this->prefix . 'provider', \Petsync_Sync::PROVIDER );
		update_post_meta( $id, $this->prefix . 'api_response', wp_json_encode( $api ) );

		Pet_Hydrator::flush_cache();

		return $id;
	}
}
