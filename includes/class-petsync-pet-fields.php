<?php
/**
 * Pet fields panel — manual entry for pets that have no provider.
 *
 * Registers the editor sidebar panels that let a shelter fill in the fields a
 * sync would otherwise have supplied. Without this the plugin only works for
 * shelters that already have a Petstablished account; with it, a pet can be
 * created by hand and every block renders.
 *
 * The field list, grouping and control types all come from entities.json
 * (`editable_fields`), so adding a field is a config change rather than a code
 * change here or in the JavaScript.
 *
 * @package ShelterKit_Pets
 * @since   1.0.0
 */

declare( strict_types = 1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Petsync_Pet_Fields {

	private const HANDLE = 'petsync-pet-fields';

	public function __construct() {
		add_action( 'enqueue_block_editor_assets', array( $this, 'enqueue' ) );
	}

	/**
	 * Enqueue the panel, but only on the pet editor.
	 *
	 * enqueue_block_editor_assets fires for every block editor screen —
	 * posts, pages, the site editor — so without this guard the panel script
	 * would load everywhere and do nothing.
	 */
	public function enqueue(): void {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

		if ( ! $screen || 'vcps_pet' !== $screen->post_type || 'post' !== $screen->base ) {
			return;
		}

		wp_enqueue_script(
			self::HANDLE,
			PETSYNC_URL . 'assets/js/pet-fields.js',
			array( 'wp-plugins', 'wp-editor', 'wp-element', 'wp-components', 'wp-data', 'wp-core-data', 'wp-i18n', 'wp-block-editor' ),
			PETSYNC_VERSION,
			true
		);

		wp_localize_script( self::HANDLE, 'shelterPetsFields', $this->get_panel_config() );
	}

	/**
	 * Build the config the panel renders from.
	 *
	 * @return array
	 */
	private function get_panel_config(): array {
		$entity   = \Petsync\Core\Config::get_path( 'entities', 'entities.vcps_pet', array() );
		$prefix   = $entity['meta_prefix'] ?? '_pet_';
		$editable = $entity['editable_fields'] ?? array();
		$groups   = $entity['editable_field_groups'] ?? array();

		// Group and field labels come from entities.json (dynamic), so they
		// can't be wrapped in __() — the string extractor only sees literals,
		// and a non-literal __() call yields no POT entry anyway. Same trade
		// the abilities provider makes for ability labels.
		$group_list = array();
		foreach ( $groups as $slug => $label ) {
			$group_list[] = array(
				'slug'  => $slug,
				'label' => $label,
			);
		}

		$field_list = array();
		foreach ( $editable as $name => $config ) {
			$field_list[] = array(
				'name'    => $name,
				'group'   => $config['group'] ?? 'basics',
				'control' => $config['control'] ?? 'text',
				'label'   => $config['label'] ?? $name,
				'metaKey' => $prefix . $name,
			);
		}

		$post = get_post();

		return array(
			'postType' => 'vcps_pet',
			'groups'   => $group_list,
			'fields'   => $field_list,
			// Provenance for the pet being edited. Passed here rather than
			// exposed through REST: it is protected internal meta, it cannot
			// change during an edit session, and the hydrator deliberately
			// keeps the registered `fields` out of public output.
			'provider' => $post ? (string) get_post_meta( $post->ID, $prefix . 'provider', true ) : '',
		);
		// User-facing strings deliberately live in assets/js/pet-fields.js as
		// literals rather than being passed from here: the extractor only sees
		// literals, and a format string arriving as data cannot be checked by
		// @wordpress/valid-sprintf either.
	}
}
