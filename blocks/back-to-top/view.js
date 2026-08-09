/**
 * Back to Top — Interactivity Store
 *
 * Shows the button after scrolling past a configurable threshold.
 * Uses a passive scroll listener for performance.
 *
 * @package ShelterKit_Pets
 * @since 4.3.0
 */

import { store, getContext } from '@wordpress/interactivity';

const { state, actions, callbacks } = store( 'petsync/back-to-top', {
	state: {
		isVisible: false,
	},

	actions: {
		scrollToTop( event ) {
			event.preventDefault();
			window.scrollTo( { top: 0, behavior: 'smooth' } );
		},
	},

	callbacks: {
		init() {
			const ctx = getContext();
			const threshold = ctx.threshold || 400;

			const update = () => {
				state.isVisible = window.scrollY > threshold;
			};

			window.addEventListener( 'scroll', update, { passive: true } );
			update();
		},
	},
} );

export { state, actions, callbacks };
