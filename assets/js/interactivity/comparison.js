/**
 * Pet Comparison Store
 *
 * Dedicated store for the pet-comparison block (the comparison page).
 * Delegates to the root petstablished store for shared state and
 * extracts comparison-specific actions that were previously mixed
 * into the root store.
 *
 * @package
 * @since 4.3.0
 */

import { store, getContext } from '@wordpress/interactivity';
import {
	announce,
	storage,
	executeAbility,
	copyToClipboard,
} from '../utils.js';
import { doToggleFavorite } from '../store.js';

const getGlobalState = () => store( 'petsync' ).state;
const getGlobalActions = () => store( 'petsync' ).actions;

/**
 * Resolve pet ID from context — used by per-pet actions.
 */
function getPetIdFromContext() {
	const ctx = getContext();
	return {
		petId: ctx.petId ?? null,
		petName: ctx.petName ?? '',
	};
}

/**
 * Get the pet archive URL from context or DOM.
 */
function getArchiveUrl() {
	try {
		const ctx = getContext();
		if ( ctx.archiveUrl ) {
			return ctx.archiveUrl;
		}
	} catch {
		/* no context available */
	}
	return (
		document.querySelector( '[data-pets-archive-url]' )?.dataset
			.petsArchiveUrl || '/pets/'
	);
}

const { state, actions, callbacks } = store( 'petsync/comparison', {
	state: {
		/**
		 * Whether the current pet (from context) is favorited.
		 */
		get isFavorited() {
			const { petId } = getPetIdFromContext();
			if ( ! petId ) {
				return false;
			}
			return getGlobalState().favorites.includes( petId );
		},
	},

	actions: {
		/**
		 * Toggle favorite for the pet in this context.
		 */
		*toggleFavorite() {
			const { petId, petName } = getPetIdFromContext();
			if ( ! petId ) {
				return;
			}
			yield* doToggleFavorite( petId, petName );
		},

		/**
		 * Copy the current comparison URL to clipboard.
		 */
		*copyCompareUrl() {
			try {
				const result = yield executeAbility(
					'petsync/get-comparison',
					null,
					{ method: 'GET' }
				);
				const copied = yield copyToClipboard( result.shareUrl );
				if ( copied ) {
					getGlobalState().notification = 'Link copied!';
					setTimeout(
						() => ( getGlobalState().notification = null ),
						3000
					);
					announce( 'Comparison link copied' );
				}
			} catch ( error ) {
				console.error( 'Copy failed:', error );
			}
		},

		/**
		 * Clear comparison and redirect to the pet archive.
		 */
		*clearAndRedirect() {
			try {
				yield getGlobalActions().clearComparison();
			} catch {
				/* continue to redirect */
			}

			// Hard reload — the comparison block is outside the grid's
			// router region, so router navigation can't remove it.
			window.location.href = getArchiveUrl();
		},

		/**
		 * Remove a pet from comparison and refresh the comparison page.
		 * Redirects to archive if fewer than 2 pets remain.
		 *
		 * Uses hard reloads because the comparison block sits outside the
		 * grid's router region (it's a sibling block in the template).
		 * Router navigation only patches content inside its own region,
		 * so it can't update or remove the comparison block.
		 */
		*removeAndRefresh() {
			const { petId, petName } = getPetIdFromContext();
			if ( ! petId ) {
				return;
			}

			const gs = getGlobalState();
			gs.comparison = gs.comparison.filter( ( id ) => id !== petId );
			if ( gs.pets[ petId ] ) {
				gs.pets[ petId ].compared = false;
			}
			announce( `${ petName || 'Pet' } removed from comparison` );

			try {
				const result = yield executeAbility(
					'petsync/update-comparison',
					{ action: 'remove', id: petId }
				);
				gs.comparison = result.ids;
				storage.set( 'comparison', gs.comparison );
			} catch ( error ) {
				console.error( 'Failed to remove from comparison:', error );
			}

			if ( gs.comparison.length < 2 ) {
				window.location.href = getArchiveUrl();
				return;
			}

			// Reload with updated ?compare= URL.
			const url = new URL( window.location.href );
			url.searchParams.set( 'compare', gs.comparison.join( ',' ) );
			window.location.href = url.toString();
		},
	},

	callbacks: {
		/**
		 * Initialize — sync favorites and comparison with server.
		 */
		init() {
			// This sync exists to pick up state the browser cannot know — a
			// logged-in user's saved list. It must only ever ADD information.
			//
			// It used to assign the result unconditionally, which broke the
			// shared-link path the Share button creates: arriving at
			// ?compare=… seeds the right list server-side, then this request —
			// which cannot carry the URL — comes back empty and overwrites it.
			// The count rendered (0) beside a full table, and the empty array
			// was persisted, wiping the visitor's OWN saved comparison.
			//
			// Two guards, because they cover different cases:
			//
			//   comparisonFromUrl — the server ranks the URL above user meta
			//   above the cookie. When the URL supplied the list, no answer
			//   from here can improve on it, including a non-empty one from a
			//   logged-in user's meta.
			//
			//   result.ids.length — otherwise, adopt the server's list only if
			//   it actually says something. An empty answer means "nothing
			//   stored for you", which is not a reason to discard what we have.
			//
			// Clearing remains possible: actions.clearComparison() writes an
			// empty list deliberately, and this guard does not touch it.
			if ( ! getGlobalState().comparisonFromUrl ) {
				executeAbility( 'petsync/get-comparison', null, {
					method: 'GET',
				} )
					.then( ( result ) => {
						getGlobalState().comparisonMax = result.max;

						if ( ! result.ids.length ) {
							return;
						}

						getGlobalState().comparison = result.ids;
						storage.set(
							'comparison',
							getGlobalState().comparison
						);
					} )
					.catch( () => {} );
			}

			executeAbility( 'petsync/get-favorites', null, { method: 'GET' } )
				.then( ( result ) => {
					// Same rule as above: an empty answer is not a reason to
					// discard a list the visitor already has.
					if ( ! result.favorites.length ) {
						return;
					}

					getGlobalState().favorites = result.favorites;
					storage.set( 'favorites', getGlobalState().favorites );
				} )
				.catch( () => {} );
		},
	},
} );

export { state, actions, callbacks };
