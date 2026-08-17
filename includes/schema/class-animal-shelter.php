<?php
/**
 * Describe the site as an AnimalShelter in structured data.
 *
 * schema.org's AnimalShelter is Thing > Organization > LocalBusiness >
 * AnimalShelter and defines no properties of its own — it is an address-and-
 * hours type. So this is a TYPE REFINEMENT on an organisation, and nothing
 * about the animals. There is no schema.org type for an adoptable animal, and
 * Google has no pet-adoption rich result, so per-animal markup would buy
 * nothing; see #43.
 *
 * The hard part is not building the node. It is not emitting a second one.
 * Yoast, Rank Math, SEOPress, Slim SEO and The SEO Framework all already emit an
 * Organization, and two competing descriptions of the same entity are worse than
 * none. So where a plugin exposes a way in, this refines what it already emits
 * and adds nothing of its own.
 *
 * All three adapters below were read from the installed source, not from
 * documentation.
 *
 * @package ShelterKit_Pets
 * @since   1.3.0
 */

declare( strict_types = 1 );

namespace Petsync\Schema;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Animal_Shelter {

	/**
	 * Stored in petsync_settings. Off by default: this changes what search
	 * engines are told about a site, and a plugin should not do that unasked.
	 */
	public const SETTING = 'animal_shelter_schema';

	/**
	 * How each SEO plugin lets us in.
	 *
	 * `constant` detects it — a version constant is defined before `wp_head`
	 * and needs no admin includes, unlike is_plugin_active().
	 *
	 * `shape` says what its filter hands over:
	 *   'type'  — the @type string itself (SEOPress). One filter, one word.
	 *   'graph' — a sequential list of nodes (Slim SEO, The SEO Framework, and
	 *             almost certainly Yoast and Rank Math), so the Organization
	 *             node has to be found and specialised in place.
	 *
	 * @return array<string, array{label: string, constant: string, hook: string, shape: string}>
	 */
	public static function adapters(): array {
		return array(
			'seopress'      => array(
				'label'    => 'SEOPress',
				'constant' => 'SEOPRESS_VERSION',
				'hook'     => 'seopress_get_tag_schema_knowledge_type',
				'shape'    => 'type',
			),
			'slim-seo'      => array(
				'label'    => 'Slim SEO',
				'constant' => 'SLIM_SEO_VER',
				'hook'     => 'slim_seo_schema_graph',
				'shape'    => 'graph',
			),
			'seo-framework' => array(
				'label'    => 'The SEO Framework',
				'constant' => 'THE_SEO_FRAMEWORK_VERSION',
				'hook'     => 'the_seo_framework_schema_graph_data',
				'shape'    => 'graph',
			),
		);
	}

	/**
	 * The adapter for whichever SEO plugin is active, or null.
	 *
	 * @return array{label: string, constant: string, hook: string, shape: string}|null
	 */
	public static function active_adapter(): ?array {
		foreach ( self::adapters() as $adapter ) {
			if ( defined( $adapter['constant'] ) ) {
				return $adapter;
			}
		}
		return null;
	}

	/**
	 * Whether SOME SEO plugin is present that we have no adapter for.
	 *
	 * Emitting our own node alongside one we cannot see would create the
	 * duplicate this whole design exists to avoid, so that case stays silent.
	 */
	public static function unknown_seo_plugin_active(): bool {
		if ( null !== self::active_adapter() ) {
			return false;
		}

		foreach ( array( 'WPSEO_VERSION', 'RANK_MATH_VERSION', 'AIOSEO_VERSION', 'SEOPRESS_PRO_VERSION' ) as $constant ) {
			if ( defined( $constant ) ) {
				return true;
			}
		}

		return false;
	}

	public static function is_enabled(): bool {
		$settings = get_option( 'petsync_settings', array() );

		return ! empty( $settings[ self::SETTING ] );
	}

	public static function register(): void {
		if ( ! self::is_enabled() ) {
			return;
		}

		$adapter = self::active_adapter();

		if ( null === $adapter ) {
			// Nothing to refine. Emit our own only when no SEO plugin at all is
			// present — an unrecognised one means staying quiet.
			if ( ! self::unknown_seo_plugin_active() ) {
				add_action( 'wp_head', array( self::class, 'print_own_node' ), 20 );
			}
			return;
		}

		if ( 'type' === $adapter['shape'] ) {
			add_filter( $adapter['hook'], array( self::class, 'refine_type' ), 20 );
			return;
		}

		add_filter( $adapter['hook'], array( self::class, 'refine_graph' ), 20 );
	}

	// ─── Adapters ───────────────────────────────────────────────────────────

	/**
	 * SEOPress hands over the @type string itself, defaulting to 'Organization'.
	 *
	 * Left alone if the shelter has already chosen something other than the
	 * default — that is their setting, not ours to override.
	 *
	 * @param mixed $type Current @type.
	 * @return mixed
	 */
	public static function refine_type( $type ) {
		return ( 'Organization' === $type ) ? 'AnimalShelter' : $type;
	}

	/**
	 * Specialise the Organization node in a graph, in place.
	 *
	 * AnimalShelter is a subtype of Organization, so this narrows the claim
	 * rather than contradicting it — nothing already stated becomes untrue.
	 *
	 * @param mixed $graph Sequential list of nodes.
	 * @return mixed
	 */
	public static function refine_graph( $graph ) {
		if ( ! is_array( $graph ) ) {
			return $graph;
		}

		foreach ( $graph as $i => $node ) {
			if ( ! is_array( $node ) || ! isset( $node['@type'] ) ) {
				continue;
			}

			$type = $node['@type'];

			if ( 'Organization' === $type ) {
				$graph[ $i ]['@type'] = 'AnimalShelter';
				return $graph;
			}

			// Some plugins emit @type as a list.
			if ( is_array( $type ) && in_array( 'Organization', $type, true ) ) {
				$graph[ $i ]['@type'] = array_values(
					array_unique(
						array_map(
							static fn( $t ) => 'Organization' === $t ? 'AnimalShelter' : $t,
							$type
						)
					)
				);
				return $graph;
			}
		}

		return $graph;
	}

	// ─── Our own node, when nothing else emits one ──────────────────────────

	/**
	 * The AnimalShelter node, or null when there is not enough to say.
	 *
	 * A half-populated entity is worse than none: it tells a search engine this
	 * is a local business while withholding the address that makes the claim
	 * useful. The name alone does not count, because it falls back to the site
	 * title and is therefore never empty.
	 *
	 * @return array<string, mixed>|null
	 */
	public static function data(): ?array {
		if ( ! class_exists( 'ShelterKit_Profile' ) || ! \ShelterKit_Profile::has_contact_details() ) {
			return null;
		}

		$profile = \ShelterKit_Profile::all();

		$address = array_filter(
			array(
				'@type'           => 'PostalAddress',
				'streetAddress'   => $profile['street_address'],
				'addressLocality' => $profile['locality'],
				'addressRegion'   => $profile['region'],
				'postalCode'      => $profile['postal_code'],
				'addressCountry'  => $profile['country'],
			),
			static fn( string $v ): bool => '' !== trim( $v )
		);

		$node = array(
			'@context'  => 'https://schema.org',
			'@type'     => 'AnimalShelter',
			'name'      => $profile['name'],
			'url'       => '' !== $profile['url'] ? $profile['url'] : home_url( '/' ),
			'telephone' => $profile['phone'],
			'email'     => $profile['email'],
		);

		// Only include the address when it has more than its own @type.
		if ( count( $address ) > 1 ) {
			$node['address'] = $address;
		}

		return array_filter(
			$node,
			static fn( $v ): bool => is_array( $v ) ? ! empty( $v ) : '' !== trim( (string) $v )
		);
	}

	/**
	 * Print the node. JSON-LD is a script context, so this is encoded, never
	 * concatenated — JSON_HEX_TAG so a stray "</script>" in a shelter's name
	 * cannot close the block.
	 */
	public static function print_own_node(): void {
		$data = self::data();

		if ( null === $data ) {
			return;
		}

		printf(
			'<script type="application/ld+json">%s</script>' . "\n",
			wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_UNESCAPED_UNICODE )
		);
	}
}
