<?php
/**
 * Hydration must not regress into an N+1.
 *
 * A 99-pet archive once issued 202 queries against a documented "~5". Batch
 * priming covered the pets' own meta and terms, but the featured image, the
 * gallery and bonded partners are separate posts and each cost a query per
 * pet. The failure mode is silent: correct output, and a page that gets slower
 * as a shelter takes in more animals.
 *
 * These assert an upper bound rather than an exact count, so ordinary changes
 * do not churn the test — but a per-pet query reappearing will blow through it.
 *
 * @package ShelterKit_Pets
 */

declare( strict_types = 1 );

namespace Petsync\Tests\Integration;

use Petsync\Core\Pet_Hydrator;

final class HydrationQueryCountTest extends PetTestCase {

	private const PETS = 25;

	/**
	 * Pets with a featured image, a gallery, and bonded partners — all three
	 * things that reach outside the pet's own row.
	 *
	 * @return int[] Pet post IDs.
	 */
	private function seed_pets(): array {
		$ids = array();

		for ( $i = 0; $i < self::PETS; $i++ ) {
			$id = $this->make_synced_pet(
				array(
					'id'              => 700000 + $i,
					'name'            => "Query Pet {$i}",
					// Every pet bonded to the next, so partner resolution runs.
					'group_id'        => 'g' . intdiv( $i, 2 ),
					'grouped_pet_ids' => array( 700000 + $i, 700000 + ( $i ^ 1 ) ),
				)
			);

			$attachment = self::factory()->attachment->create_object(
				array(
					'file'           => "pet-{$i}.jpg",
					'post_parent'    => $id,
					'post_mime_type' => 'image/jpeg',
				)
			);
			set_post_thumbnail( $id, $attachment );
			update_post_meta( $id, $this->prefix . 'gallery_ids', array( $attachment ) );

			$ids[] = $id;
		}

		return $ids;
	}

	/**
	 * @return int Queries issued while hydrating.
	 */
	private function measure( string $profile ): int {
		$this->seed_pets();

		// Realistic order: cold caches, THEN the query that primes the post
		// cache, then hydrate. Flushing after the query would count get_post()
		// calls no real request makes.
		wp_cache_flush();
		Pet_Hydrator::flush_cache();

		$posts = get_posts(
			array(
				'post_type'      => 'vcps_pet',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
			)
		);

		$before = get_num_queries();
		Pet_Hydrator::hydrate_many( $posts, $profile );

		return get_num_queries() - $before;
	}

	public function test_grid_hydration_does_not_scale_with_pet_count(): void {
		$queries = $this->measure( 'grid' );

		$this->assertLessThan(
			self::PETS,
			$queries,
			'hydrating ' . self::PETS . " pets took {$queries} queries — that is at least one per pet, so a cache is not being primed"
		);
	}

	public function test_full_hydration_does_not_scale_with_pet_count(): void {
		$queries = $this->measure( 'full' );

		$this->assertLessThan( self::PETS, $queries );
	}

	/**
	 * The specific regression: featured images are separate posts, and their
	 * meta holds the image sizes get_the_post_thumbnail_url() needs.
	 */
	public function test_featured_images_are_primed(): void {
		$ids = $this->seed_pets();

		wp_cache_flush();
		Pet_Hydrator::flush_cache();
		$posts = get_posts(
			array(
				'post_type'      => 'vcps_pet',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
			)
		);
		Pet_Hydrator::hydrate_many( $posts, 'grid' );

		$before = get_num_queries();
		foreach ( $ids as $id ) {
			get_the_post_thumbnail_url( $id, 'medium_large' );
		}

		$this->assertSame(
			0,
			get_num_queries() - $before,
			'thumbnail attachments and their meta should already be in cache after hydration'
		);
	}

	public function test_bonded_partners_resolve_without_a_query_each(): void {
		$this->seed_pets();

		wp_cache_flush();
		Pet_Hydrator::flush_cache();
		$posts = get_posts(
			array(
				'post_type'      => 'vcps_pet',
				'post_status'    => 'publish',
				'posts_per_page' => -1,
			)
		);

		$before   = get_num_queries();
		$entities = Pet_Hydrator::hydrate_many( $posts, 'full' );
		$queries  = get_num_queries() - $before;

		$bonded = array_filter( $entities, static fn( $e ) => ! empty( $e['bonded_pair_names'] ) );

		$this->assertNotEmpty( $bonded, 'the fixture should produce bonded pets, or this proves nothing' );
		$this->assertLessThan( count( $bonded ), $queries, 'partner resolution should be batched, not one query per pet' );
	}
}
