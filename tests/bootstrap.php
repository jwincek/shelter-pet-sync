<?php
/**
 * Bootstrap for the pure-function unit suite.
 *
 * These tests deliberately do NOT load WordPress. They cover logic that is
 * self-contained — tristate normalisation and the compatibility summary —
 * so the handful of WordPress functions those methods touch are stubbed and
 * only the class under test is loaded.
 *
 * Anything reaching the database, hooks, or the config loader belongs in the
 * integration suite instead.
 *
 * @package Petstablished_Sync
 */

declare( strict_types = 1 );

// The class carries no ABSPATH guard, but define it anyway so anything pulled
// in alongside behaves.
defined( 'ABSPATH' ) || define( 'ABSPATH', dirname( __DIR__ ) . '/' );

// ─── Minimal WordPress stubs ────────────────────────────────────────────────
// Unqualified calls inside the plugin's namespace fall back to these globals.

if ( ! function_exists( '__' ) ) {
	function __( string $text, string $domain = 'default' ): string {
		return $text;
	}
}

if ( ! function_exists( '_n' ) ) {
	function _n( string $single, string $plural, int $number, string $domain = 'default' ): string {
		return 1 === $number ? $single : $plural;
	}
}

if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( string $text ): string {
		return htmlspecialchars( $text, ENT_QUOTES );
	}
}

require_once dirname( __DIR__ ) . '/includes/core/class-pet-hydrator.php';
