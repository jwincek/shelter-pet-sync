<?php
/**
 * There is one flush, and it clears everything.
 *
 * The bug this guards against was not a stale method — it was that adding a
 * cache property required remembering a second place. Pet_Hydrator gained
 * $ps_id_map and $ps_id_checked for the N+1 fix; flush_cache() was updated and
 * a sibling clear_cache() was not, so it cleared two of four caches while
 * presenting itself as a full flush. Nothing called it, which is exactly why
 * nobody noticed.
 *
 * Rather than pin the four names, this reflects over the class and fails if any
 * memoised property survives a flush. A new cache added without clearing it
 * fails here instead of silently returning stale data inside a long-running
 * sync, WP-CLI run, or import.
 *
 * @package ShelterKit_Pets
 */

declare( strict_types = 1 );

namespace Petsync\Tests\Integration;

use Petsync\Core\Pet_Hydrator;
use ReflectionClass;
use ReflectionProperty;

final class CacheFlushTest extends PetTestCase {

	/**
	 * Deliberately NOT cleared: it holds entities.json, which cannot change
	 * mid-request. Anything else added to this list needs the same argument.
	 *
	 * @var string[]
	 */
	private const NOT_A_REQUEST_CACHE = array( 'entity_config' );

	/**
	 * @return ReflectionProperty[] Static properties that memoise per-request data.
	 */
	private function cache_properties(): array {
		$out = array();
		foreach ( ( new ReflectionClass( Pet_Hydrator::class ) )->getProperties( ReflectionProperty::IS_STATIC ) as $prop ) {
			if ( ! in_array( $prop->getName(), self::NOT_A_REQUEST_CACHE, true ) ) {
				$out[] = $prop;
			}
		}
		return $out;
	}

	public function test_every_request_cache_is_emptied_by_the_only_flush(): void {
		$pet = $this->make_manual_pet();
		update_post_meta( $pet, $this->prefix . 'ps_id', '4242' );
		update_post_meta( $pet, $this->prefix . 'provider', \Petsync_Sync::PROVIDER );

		// Populate as much as a real request would.
		Pet_Hydrator::get( $pet, 'full' );
		Pet_Hydrator::get_api_data( $pet );

		$props = $this->cache_properties();
		$this->assertNotEmpty( $props, 'reflection found no cache properties — the guard would be vacuous' );

		Pet_Hydrator::flush_cache();

		foreach ( $props as $prop ) {
			$value = $prop->getValue();
			$this->assertTrue(
				array() === $value || null === $value,
				"Pet_Hydrator::\${$prop->getName()} survived flush_cache(). Every per-request "
				. 'cache must be cleared there, or a long-running process reads stale data.'
			);
		}
	}

	/**
	 * The specific pair whose omission caused the original divergence. Named
	 * explicitly so the reason survives even if the reflection above is ever
	 * relaxed.
	 */
	public function test_the_ps_id_map_is_among_them(): void {
		$names = array_map( static fn( ReflectionProperty $p ) => $p->getName(), $this->cache_properties() );

		$this->assertContains( 'ps_id_map', $names );
		$this->assertContains( 'ps_id_checked', $names );
	}

	/**
	 * A second flush would drift from the first, which is what happened before.
	 */
	public function test_there_is_only_one_flush_method(): void {
		$flushers = array();
		foreach ( ( new ReflectionClass( Pet_Hydrator::class ) )->getMethods() as $m ) {
			if ( preg_match( '/(flush|clear|reset).*cache/i', $m->getName() ) ) {
				$flushers[] = $m->getName();
			}
		}

		$this->assertSame(
			array( 'flush_cache' ),
			$flushers,
			'a second cache-clearing method will drift from the first — that is the bug this file exists for'
		);
	}

	/**
	 * Flushing has to actually make the hydrator see writes made after the first
	 * read, which is the whole reason the method exists.
	 */
	public function test_a_flush_makes_later_writes_visible(): void {
		$pet = $this->make_manual_pet();
		update_post_meta( $pet, $this->prefix . 'weight', '10 lb' );

		// Creating a pet hydrates it: wp_after_insert_post derives the
		// pet_attribute terms from the entity (#49), which warms the cache
		// before this meta was written. Start from a known-cold cache.
		Pet_Hydrator::flush_cache();

		$this->assertSame( '10 lb', Pet_Hydrator::get( $pet, 'full' )['weight'] );

		update_post_meta( $pet, $this->prefix . 'weight', '20 lb' );
		$this->assertSame( '10 lb', Pet_Hydrator::get( $pet, 'full' )['weight'], 'memoised within a request' );

		Pet_Hydrator::flush_cache();
		$this->assertSame( '20 lb', Pet_Hydrator::get( $pet, 'full' )['weight'] );
	}
}
