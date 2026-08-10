<?php
/**
 * Which abilities are offered to MCP clients.
 *
 * meta.mcp.public is what the MCP Adapter plugin reads to decide whether an
 * ability appears on its default server. Core knows nothing about it —
 * WP_Ability::$meta is free-form and wp-includes/abilities-api/ has no mention
 * of MCP — so the flag is inert until someone installs the adapter, and cannot
 * break an existing install.
 *
 * What it CAN do is hand a writer to an agent. These pin the partition so that
 * cannot happen by accident: three declarations of the same underlying fact
 * (permission, readonly, mcp.public) that must agree, enforced rather than
 * maintained by hand — the failure the template namespace taught (#38).
 *
 * @package ShelterKit_Pets
 */

declare( strict_types = 1 );

namespace Petsync\Tests\Integration;

final class McpExposureTest extends PetTestCase {

	/**
	 * @return array<string, array<string, mixed>>
	 */
	private function abilities(): array {
		return \Petsync\Core\Config::get_item( 'abilities', 'abilities', array() );
	}

	/**
	 * @param array<string, mixed> $ability Ability declaration.
	 */
	private function is_mcp_public( array $ability ): bool {
		return true === ( $ability['meta']['mcp']['public'] ?? null );
	}

	/**
	 * @param array<string, mixed> $ability Ability declaration.
	 */
	private function is_readonly( array $ability ): bool {
		return true === ( $ability['meta']['annotations']['readonly'] ?? null );
	}

	/**
	 * The one that matters. An agent must never be handed something that
	 * writes — not favorites, not comparison state, and above all not
	 * set-pet-gallery, which edits content.
	 */
	public function test_nothing_that_writes_is_exposed(): void {
		foreach ( $this->abilities() as $name => $ability ) {
			if ( $this->is_mcp_public( $ability ) ) {
				$this->assertTrue(
					$this->is_readonly( $ability ),
					"$name is offered to MCP clients but is not annotated readonly"
				);
			}
		}
	}

	/**
	 * public_with_session abilities read per-visitor state — favorites and the
	 * comparison tray. An agent has no session worth reading, so exposing them
	 * would offer a tool that always answers empty.
	 */
	public function test_only_fully_public_abilities_are_exposed(): void {
		foreach ( $this->abilities() as $name => $ability ) {
			if ( $this->is_mcp_public( $ability ) ) {
				$this->assertSame(
					'public',
					$ability['permission'] ?? null,
					"$name is offered to MCP clients but its permission is not 'public'"
				);
			}
		}
	}

	/**
	 * The partition has to be complete in both directions, or the flag becomes
	 * something someone remembers rather than something the config states.
	 */
	public function test_every_public_readonly_ability_is_exposed(): void {
		foreach ( $this->abilities() as $name => $ability ) {
			if ( 'public' === ( $ability['permission'] ?? null ) && $this->is_readonly( $ability ) ) {
				$this->assertTrue(
					$this->is_mcp_public( $ability ),
					"$name is public and read-only but is not offered to MCP clients — deliberate, or forgotten?"
				);
			}
		}
	}

	public function test_the_exposed_set_is_exactly_what_is_expected(): void {
		$exposed = array_keys( array_filter( $this->abilities(), fn( $a ) => $this->is_mcp_public( $a ) ) );
		sort( $exposed );

		$this->assertSame(
			array(
				'petsync/batch-get-pets',
				'petsync/filter-pets',
				'petsync/get-adoption-stats',
				'petsync/get-filter-options',
				'petsync/get-pet',
				'petsync/list-pets',
			),
			$exposed,
			'the exposed set changed — adding one is a decision, not a detail'
		);
	}

	/**
	 * An exposed ability is a published tool. If it has no behavioural test,
	 * nothing would reveal it breaking — which is why #39 made testing the read
	 * surface a prerequisite rather than a follow-up.
	 */
	public function test_every_exposed_ability_has_a_behavioural_test(): void {
		$provider = (string) file_get_contents( PETSYNC_DIR . 'includes/abilities/class-provider.php' );
		$suite    = '';
		foreach ( glob( __DIR__ . '/*.php' ) ?: array() as $file ) {
			$suite .= (string) file_get_contents( $file );
		}

		foreach ( $this->abilities() as $name => $ability ) {
			if ( ! $this->is_mcp_public( $ability ) ) {
				continue;
			}

			$this->assertSame(
				1,
				preg_match( '/' . preg_quote( $name, '/' ) . "'\s*=>\s*'([^']+)'/", $provider, $m ),
				"$name has no callable mapping"
			);

			$fn = substr( (string) strrchr( $m[1], '\\' ), 1 );
			$this->assertMatchesRegularExpression(
				'/\b' . preg_quote( $fn, '/' ) . '\s*\(/',
				$suite,
				"$name is exposed to MCP clients but its handler $fn() is never called by any test"
			);
		}
	}
}
