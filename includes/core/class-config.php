<?php
/**
 * Config loader — reads and caches JSON configuration files.
 *
 * Supports $ref resolution for shared schema fragments, following
 * the Modern WordPress Plugin Development Guide §3.5.
 *
 * $ref resolution:
 * - A JSON object with a single "$ref" key is replaced with the contents
 *   of the referenced file (relative to the config directory).
 * - A JSON object with "$ref" alongside other keys merges the referenced
 *   schema properties into the current object (allOf-like behavior).
 * - References are resolved recursively but not circularly.
 *
 * @package ShelterKit_Pets
 * @since   1.0.0
 */

declare( strict_types = 1 );

namespace Petsync\Core;

class Config {

	/**
	 * Cache of loaded configurations.
	 *
	 * @var array<string, array>
	 */
	private static array $cache = [];

	/**
	 * Path to config directory.
	 *
	 * @var string|null
	 */
	private static ?string $config_path = null;

	/**
	 * Tracks which $ref files are currently being resolved (cycle detection).
	 *
	 * @var array<string, true>
	 */
	private static array $resolving = [];

	/**
	 * Initialize the config loader with path.
	 *
	 * @param string $path Path to config directory.
	 */
	public static function init( string $path ): void {
		self::$config_path = trailingslashit( $path );
	}

	/**
	 * Get a config file by name.
	 *
	 * @param string $name Config file name (without .json extension).
	 * @return array The parsed config data.
	 */
	public static function get( string $name ): array {
		if ( isset( self::$cache[ $name ] ) ) {
			return self::$cache[ $name ];
		}

		$file = self::$config_path . $name . '.json';
		if ( ! file_exists( $file ) ) {
			return [];
		}

		$contents = file_get_contents( $file );
		if ( false === $contents ) {
			return [];
		}

		$data = json_decode( $contents, true );
		if ( ! is_array( $data ) ) {
			return [];
		}

		// Resolve $ref references.
		$data = self::resolve_refs( $data );

		self::$cache[ $name ] = $data;
		return $data;
	}

	/**
	 * Get a specific top-level item from a config file.
	 *
	 * @param string $name    Config file name.
	 * @param string $key     Key to retrieve.
	 * @param mixed  $default Default value if key not found.
	 * @return mixed The config value or default.
	 */
	public static function get_item( string $name, string $key, mixed $default = null ): mixed {
		$config = self::get( $name );
		return $config[ $key ] ?? $default;
	}

	/**
	 * Get a nested item using dot notation.
	 *
	 * @param string $name    Config file name.
	 * @param string $path    Dot-notation path (e.g., 'entities.vcps_pet.fields').
	 * @param mixed  $default Default value if path not found.
	 * @return mixed The config value or default.
	 */
	public static function get_path( string $name, string $path, mixed $default = null ): mixed {
		$config = self::get( $name );
		$keys   = explode( '.', $path );

		foreach ( $keys as $key ) {
			if ( ! is_array( $config ) || ! isset( $config[ $key ] ) ) {
				return $default;
			}
			$config = $config[ $key ];
		}

		return $config;
	}

	/**
	 * Clear the config cache.
	 *
	 * @param string|null $name Optional specific config to clear.
	 */
	public static function clear_cache( ?string $name = null ): void {
		if ( null === $name ) {
			self::$cache = [];
		} else {
			unset( self::$cache[ $name ] );
		}
	}

	/**
	 * Recursively resolve $ref references in a config array.
	 *
	 * Supports two patterns:
	 *
	 * 1. Pure replacement — object has ONLY a $ref key:
	 *    { "$ref": "schemas/pagination.json" }
	 *    → replaced entirely with the contents of that file.
	 *
	 * 2. Merge — object has $ref alongside other keys:
	 *    { "$ref": "schemas/pagination.json", "properties": { "extra": ... } }
	 *    → referenced file's properties are merged under the current object.
	 *
	 * @param array $data The data to process.
	 * @return array Data with $ref values resolved.
	 */
	private static function resolve_refs( array $data ): array {
		foreach ( $data as $key => $value ) {
			if ( ! is_array( $value ) ) {
				continue;
			}

			if ( isset( $value['$ref'] ) ) {
				$ref_path = self::$config_path . $value['$ref'];

				// Cycle detection.
				if ( isset( self::$resolving[ $ref_path ] ) ) {
					continue;
				}

				if ( ! file_exists( $ref_path ) ) {
					// Remove the $ref key and keep any sibling properties.
					unset( $value['$ref'] );
					$data[ $key ] = self::resolve_refs( $value );
					continue;
				}

				self::$resolving[ $ref_path ] = true;

				$ref_contents = json_decode( file_get_contents( $ref_path ), true );
				if ( ! is_array( $ref_contents ) ) {
					unset( self::$resolving[ $ref_path ] );
					continue;
				}

				// Resolve any nested $refs in the referenced file.
				$ref_contents = self::resolve_refs( $ref_contents );

				unset( self::$resolving[ $ref_path ] );

				// Strip the $id meta key from referenced schemas.
				unset( $ref_contents['$id'] );

				if ( count( $value ) === 1 ) {
					// Pure replacement: { "$ref": "..." } → contents.
					$data[ $key ] = $ref_contents;
				} else {
					// Merge: sibling keys override/extend the referenced schema.
					unset( $value['$ref'] );

					// Deep merge: if both have "properties", merge them.
					$data[ $key ] = self::deep_merge( $ref_contents, $value );
				}
			} else {
				// Recurse into nested arrays.
				$data[ $key ] = self::resolve_refs( $value );
			}
		}

		return $data;
	}

	/**
	 * Deep merge two arrays. Values in $override take precedence.
	 * Both arrays' sub-arrays are merged recursively.
	 *
	 * @param array $base     Base array.
	 * @param array $override Override array.
	 * @return array Merged result.
	 */
	private static function deep_merge( array $base, array $override ): array {
		foreach ( $override as $key => $value ) {
			if ( is_array( $value ) && isset( $base[ $key ] ) && is_array( $base[ $key ] ) ) {
				// If both are associative arrays, merge recursively.
				if ( self::is_assoc( $value ) && self::is_assoc( $base[ $key ] ) ) {
					$base[ $key ] = self::deep_merge( $base[ $key ], $value );
				} else {
					// Sequential arrays: override replaces.
					$base[ $key ] = $value;
				}
			} else {
				$base[ $key ] = $value;
			}
		}

		return $base;
	}

	/**
	 * Check if an array is associative (string keys).
	 */
	private static function is_assoc( array $arr ): bool {
		return array_keys( $arr ) !== range( 0, count( $arr ) - 1 );
	}
}
