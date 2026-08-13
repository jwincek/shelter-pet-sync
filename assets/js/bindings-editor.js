/**
 * Editor-side preview for petsync/pet-data bindings.
 *
 * Bindings resolve in two places. On the front end the PHP source runs and
 * every bound field fills in. In the editor, the JS source runs — and this
 * plugin had none, so a bound heading, image or paragraph showed its saved
 * content, which for the kennel card is empty. Designing the card meant
 * designing against blank boxes.
 *
 * This is the client half of #74; the server half stands a pet in for
 * ServerSideRender'd blocks, which resolve over REST rather than here.
 *
 * @param wp
 * @package
 * @since   1.2.0
 */

( function ( wp ) {
	'use strict';

	if ( ! wp || ! wp.blocks || ! wp.blocks.registerBlockBindingsSource ) {
		// registerBlockBindingsSource landed in WP 6.7. On anything older the
		// editor simply keeps showing the saved content, which is what it did
		// before this file existed — degraded, not broken.
		return;
	}

	const data = window.petsyncPreviewPet || {};
	const fields = data.fields || {};

	wp.blocks.registerBlockBindingsSource( {
		name: 'petsync/pet-data',
		label: data.label || 'Pet Data',
		usesContext: [ 'postId', 'postType' ],

		/**
		 * @param {Object} args          Source arguments.
		 * @param {Object} args.context  Block context.
		 * @param {Object} args.bindings Bound attribute => { args: { key } }.
		 * @return {Object} Attribute => value.
		 */
		getValues( args ) {
			const context = args.context || {};
			const bindings = args.bindings || {};
			const values = {};

			// A pet being edited in the post editor is its own subject, and the
			// server resolves it. Standing the preview pet in there would show
			// the wrong animal on a real pet's page. Returning nothing leaves
			// the editor exactly as it behaved before.
			if (
				'vcps_pet' === context.postType &&
				parseInt( context.postId, 10 ) > 0
			) {
				return values;
			}

			Object.keys( bindings ).forEach( function ( attribute ) {
				const binding = bindings[ attribute ] || {};
				const key = ( binding.args || {} ).key;

				if (
					key &&
					Object.prototype.hasOwnProperty.call( fields, key )
				) {
					values[ attribute ] = fields[ key ];
				}
			} );

			return values;
		},

		/**
		 * The preview is a stand-in for a pet that is not the subject of what is
		 * being edited, so it must not be editable in place — typing over the
		 * name in the card design would look like it renamed the animal.
		 */
		canUserEditValue() {
			return false;
		},
	} );
} )( window.wp );
