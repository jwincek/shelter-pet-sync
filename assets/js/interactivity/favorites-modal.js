/**
 * Pet Favorites Modal Store
 *
 * Cards are rendered imperatively by the syncCards callback rather than
 * via data-wp-each, which has a context-scoping bug where all cloned
 * elements share the last item's context.pet value.
 *
 * SSR cards (from PHP) are plain HTML with no Interactivity directives.
 * On hydration, syncCards takes ownership and re-renders from the
 * reactive favorites signal. Event delegation on the grid handles
 * unfavorite clicks and bonded pair popover toggles.
 *
 * @package
 * @since 4.3.0
 */

import { store, getContext, getElement } from '@wordpress/interactivity';
import { doToggleFavorite } from '../store.js';
import { announce, storage, executeAbility } from '../utils.js';

const getGlobalState = () => store( 'petsync' ).state;
const getGlobalActions = () => store( 'petsync' ).actions;

/* ─── Body overflow lock counter ───
 * Shared between the favorites modal, gallery lightbox, and any future
 * overlays. Each lock() call increments; each unlock() decrements.
 * Body overflow is only restored when the count reaches zero. */
const overflowLock = {
	_count: 0,
	lock() {
		this._count++;
		if ( this._count === 1 ) {
			document.body.style.overflow = 'hidden';
		}
	},
	unlock() {
		this._count = Math.max( 0, this._count - 1 );
		if ( this._count === 0 ) {
			document.body.style.overflow = '';
		}
	},
};

// Export so the gallery lightbox can share the same counter.
export { overflowLock };

/* ─── Refresh guards ───
 * isRefreshing: prevents concurrent refreshFavorites calls.
 * clearInFlight: blocks refreshFavorites entirely while a clear is pending,
 *   preventing a get-favorites response from restoring cleared pets.
 * stateGeneration: bumped by clearAllFavorites so any in-flight refresh
 *   that started before the clear discards its stale response. */
let isRefreshing = false;
let clearInFlight = false;
let stateGeneration = 0;

/* ─── Card DOM builder ───
 * Constructs cards using DOM APIs exclusively — no innerHTML, no string
 * concatenation. Text is always set via textContent or setAttribute,
 * which cannot be interpreted as HTML. This eliminates DOM-based XSS
 * vectors even if pet data is attacker-controlled. */

const SVG_NS = 'http://www.w3.org/2000/svg';
const HEART_PATH =
	'M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z';

function el( tag, className, attrs ) {
	const node = document.createElement( tag );
	if ( className ) {
		node.className = className;
	}
	if ( attrs ) {
		for ( const [ k, v ] of Object.entries( attrs ) ) {
			if ( k.startsWith( 'data-' ) ) {
				node.dataset[
					k
						.slice( 5 )
						.replace( /-([a-z])/g, ( _, c ) => c.toUpperCase() )
				] = v;
			} else {
				node.setAttribute( k, v );
			}
		}
	}
	return node;
}

function createHeartSvg() {
	const svg = document.createElementNS( SVG_NS, 'svg' );
	svg.setAttribute( 'viewBox', '0 0 24 24' );
	svg.setAttribute( 'width', '16' );
	svg.setAttribute( 'height', '16' );
	svg.setAttribute( 'fill', 'currentColor' );
	svg.setAttribute( 'stroke', 'none' );
	svg.setAttribute( 'aria-hidden', 'true' );
	const path = document.createElementNS( SVG_NS, 'path' );
	path.setAttribute( 'd', HEART_PATH );
	svg.appendChild( path );
	return svg;
}

/**
 * Sanitize a URL for use in href/src attributes.
 * Only allows http, https, and mailto protocols.
 * @param url
 */
function safeUrl( url ) {
	if ( ! url ) {
		return '#';
	}
	try {
		const parsed = new URL( url, window.location.origin );
		if ( [ 'http:', 'https:', 'mailto:' ].includes( parsed.protocol ) ) {
			return parsed.href;
		}
	} catch ( e ) {
		// Relative URLs are fine.
		if ( /^\/[^/]/.test( url ) ) {
			return url;
		}
	}
	return '#';
}

function buildCardElement( pet ) {
	const meta = [ pet.breed, pet.age, pet.sex ]
		.filter( Boolean )
		.join( ' \u00b7 ' );
	const partners = pet.bonded_pair_names || pet.bondedPartners || [];
	const isNew = pet.is_new || pet.isNew || false;
	const specialNeeds =
		pet.has_special_needs === 'yes' || pet.specialNeeds === true;
	const isBondedPair = pet.is_bonded_pair || pet.isBondedPair || false;
	const name = pet.name || '';
	const url = safeUrl( pet.url );
	const image = pet.image ? safeUrl( pet.image ) : '';
	const size = pet.size || '';

	// Card root.
	const card = el( 'article', 'pet-favorites-modal__card', {
		'data-pet-id': pet.id,
	} );

	// Image link.
	const imageLink = el( 'a', 'pet-favorites-modal__card-link js-card-nav', {
		href: url,
	} );
	if ( image ) {
		const img = el( 'img', 'pet-favorites-modal__card-image', {
			src: image,
			alt: name,
			loading: 'lazy',
		} );
		imageLink.appendChild( img );
	} else {
		imageLink.appendChild(
			el( 'div', 'pet-favorites-modal__card-placeholder' )
		);
	}
	card.appendChild( imageLink );

	// Content.
	const content = el( 'div', 'pet-favorites-modal__card-content' );

	// Name.
	const h3 = el( 'h3', 'pet-favorites-modal__card-name' );
	const nameLink = el( 'a', 'js-card-nav', { href: url } );
	nameLink.textContent = name;
	h3.appendChild( nameLink );
	content.appendChild( h3 );

	// Meta (breed · age · sex).
	if ( meta ) {
		const metaP = el( 'p', 'pet-favorites-modal__card-meta' );
		metaP.textContent = meta;
		content.appendChild( metaP );
	}

	// Size.
	if ( size ) {
		const sizeP = el( 'p', 'pet-favorites-modal__card-detail' );
		sizeP.textContent = size;
		content.appendChild( sizeP );
	}

	// Badges.
	const badgesDiv = el( 'div', 'pet-favorites-modal__card-badges' );

	if ( isNew ) {
		const badge = el(
			'span',
			'pet-favorites-modal__badge pet-favorites-modal__badge--new'
		);
		badge.textContent = 'New';
		badgesDiv.appendChild( badge );
	}

	if ( specialNeeds ) {
		const badge = el(
			'span',
			'pet-favorites-modal__badge pet-favorites-modal__badge--special'
		);
		badge.textContent = 'Special Needs';
		badgesDiv.appendChild( badge );
	}

	if ( isBondedPair && partners.length ) {
		const anchor = el(
			'span',
			'pet-favorites-modal__badge-popover-anchor'
		);

		const toggle = el(
			'button',
			'pet-favorites-modal__badge pet-favorites-modal__badge--bonded js-bonded-toggle',
			{
				type: 'button',
				'aria-expanded': 'false',
			}
		);
		toggle.textContent = 'Bonded Pair';
		anchor.appendChild( toggle );

		const popover = el( 'div', 'pet-favorites-modal__bonded-popover', {
			hidden: '',
			role: 'tooltip',
		} );
		popover.appendChild(
			el( 'div', 'pet-favorites-modal__bonded-popover-arrow' )
		);

		const label = el( 'p', 'pet-favorites-modal__bonded-popover-label' );
		label.textContent = 'Must adopt together with:';
		popover.appendChild( label );

		const list = el( 'ul', 'pet-favorites-modal__bonded-popover-list' );
		for ( const partner of partners ) {
			const li = document.createElement( 'li' );
			if ( partner.url ) {
				const a = el( 'a', 'pet-favorites-modal__bonded-popover-link', {
					href: safeUrl( partner.url ),
				} );
				a.textContent = partner.name || '';
				li.appendChild( a );
			} else {
				const span = document.createElement( 'span' );
				span.textContent = partner.name || '';
				li.appendChild( span );
			}
			list.appendChild( li );
		}
		popover.appendChild( list );
		anchor.appendChild( popover );
		badgesDiv.appendChild( anchor );
	}

	content.appendChild( badgesDiv );

	// Actions.
	const actionsDiv = el( 'div', 'pet-favorites-modal__card-actions' );
	const unfavBtn = el(
		'button',
		'pet-favorites-modal__card-unfavorite js-unfavorite',
		{
			type: 'button',
			'data-pet-id': pet.id,
			'data-pet-name': name,
			'aria-label': `Remove ${ name } from favorites`,
		}
	);
	unfavBtn.appendChild( createHeartSvg() );
	actionsDiv.appendChild( unfavBtn );
	content.appendChild( actionsDiv );

	card.appendChild( content );
	return card;
}

/* ─── Event delegation (re-bound when grid element changes) ─── */

let boundGrid = null;

/* Track active popover close listeners so we can clean them up
 * when the modal closes (prevents orphaned document listeners). */
let activePopoverCleanup = null;

function cleanupPopover() {
	if ( activePopoverCleanup ) {
		activePopoverCleanup();
		activePopoverCleanup = null;
	}
}

/* Track in-flight unfavorite requests to prevent double-tap. */
const inFlightUnfavorites = new Set();

function bindGridDelegation( grid ) {
	// Re-bind if the grid element changed (e.g. after router navigation).
	if ( boundGrid === grid ) {
		return;
	}
	boundGrid = grid;

	grid.addEventListener( 'click', ( e ) => {
		// ── Unfavorite button ──
		const unfavBtn = e.target.closest( '.js-unfavorite' );
		if ( unfavBtn ) {
			e.preventDefault();
			const petId = Number( unfavBtn.dataset.petId );
			const petName = unfavBtn.dataset.petName || '';

			// Prevent double-tap while async operation is in flight.
			if ( ! petId || inFlightUnfavorites.has( petId ) ) {
				return;
			}
			inFlightUnfavorites.add( petId );
			unfavBtn.disabled = true;

			// Remember adjacent cards for focus management after removal.
			const card = unfavBtn.closest( '.pet-favorites-modal__card' );
			const nextCard = card?.nextElementSibling;
			const prevCard = card?.previousElementSibling;

			// Drive the doToggleFavorite generator manually.
			const gen = doToggleFavorite( petId, petName );
			const step = ( result ) => {
				if ( result.done ) {
					inFlightUnfavorites.delete( petId );
					// Move focus to next card, previous card, or close button.
					const nextBtn =
						nextCard?.querySelector( '.js-unfavorite' ) ||
						prevCard?.querySelector( '.js-unfavorite' );
					if ( nextBtn ) {
						nextBtn.focus();
					} else {
						const closeBtn = document.querySelector(
							'.pet-favorites-modal__close'
						);
						if ( closeBtn ) {
							closeBtn.focus();
						}
					}
					return;
				}
				Promise.resolve( result.value ).then(
					( v ) => step( gen.next( v ) ),
					( err ) => {
						inFlightUnfavorites.delete( petId );
						unfavBtn.disabled = false;
						step( gen.throw( err ) );
					}
				);
			};
			step( gen.next() );
			return;
		}

		// ── Bonded pair popover toggle ──
		const toggle = e.target.closest( '.js-bonded-toggle' );
		if ( toggle ) {
			e.stopPropagation();
			const anchor = toggle.closest(
				'.pet-favorites-modal__badge-popover-anchor'
			);
			const popover = anchor?.querySelector(
				'.pet-favorites-modal__bonded-popover'
			);
			if ( ! popover ) {
				return;
			}

			const wasOpen = ! popover.hidden;
			popover.hidden = wasOpen;
			toggle.setAttribute( 'aria-expanded', String( ! wasOpen ) );
			toggle.classList.toggle( 'is-expanded', ! wasOpen );

			// Clean up any previously open popover listener.
			cleanupPopover();

			if ( ! wasOpen ) {
				requestAnimationFrame( () => {
					popover.scrollIntoView( {
						behavior: 'smooth',
						block: 'nearest',
					} );
				} );
				const close = () => {
					popover.hidden = true;
					toggle.setAttribute( 'aria-expanded', 'false' );
					toggle.classList.remove( 'is-expanded' );
					document.removeEventListener( 'click', close, true );
					activePopoverCleanup = null;
				};
				activePopoverCleanup = close;
				requestAnimationFrame( () => {
					document.addEventListener( 'click', close, true );
				} );
			}
			return;
		}

		// ── Card navigation link — close modal ──
		const nav = e.target.closest( '.js-card-nav' );
		if ( nav ) {
			const ctx = getContext();
			ctx.isOpen = false;
			overflowLock.unlock();
			return;
		}

		// ── Bonded partner link — close modal on navigation ──
		const bondedLink = e.target.closest(
			'.pet-favorites-modal__bonded-popover-link'
		);
		if ( bondedLink ) {
			const ctx = getContext();
			ctx.isOpen = false;
			overflowLock.unlock();
		}
	} );
}

/* ─── Modal cleanup helper ─── */

function resetModalState( modalState ) {
	// Reset confirmation state (now state-driven, not DOM-driven).
	if ( modalState ) {
		modalState.clearConfirming = false;
		clearTimeout( modalState._clearTimer );
		modalState._clearTimer = null;
	}

	// Clean up any open popover's document listener.
	cleanupPopover();
}

/* ─── Store ─── */

const { state, actions, callbacks } = store( 'petsync/favorites-modal', {
	state: {
		get triggerFill() {
			return getGlobalState().favorites.length > 0
				? 'currentColor'
				: 'none';
		},

		// Clear-all confirmation — fully state-driven.
		clearConfirming: false,
		_clearTimer: null,

		get clearButtonText() {
			return state.clearConfirming
				? state._clearConfirmLabel || 'Tap again to confirm'
				: state._clearDefaultLabel || 'Clear all favorites';
		},
	},

	actions: {
		toggleModal() {
			const ctx = getContext();
			ctx.isOpen = ! ctx.isOpen;

			if ( ctx.isOpen ) {
				overflowLock.lock();
				actions.refreshFavorites();
				requestAnimationFrame( () => {
					const close = document.querySelector(
						'.pet-favorites-modal__close'
					);
					if ( close ) {
						close.focus();
					}
				} );
			} else {
				overflowLock.unlock();
				resetModalState( state );
				const trigger = document.querySelector(
					'.pet-favorites-modal__trigger'
				);
				if ( trigger ) {
					trigger.focus();
				}
			}
		},

		closeModal() {
			const ctx = getContext();
			ctx.isOpen = false;
			overflowLock.unlock();
			resetModalState( state );
			const trigger = document.querySelector(
				'.pet-favorites-modal__trigger'
			);
			if ( trigger ) {
				trigger.focus();
			}
		},

		closeAndNavigate() {
			const ctx = getContext();
			ctx.isOpen = false;
			overflowLock.unlock();
			resetModalState( state );
		},

		handleOverlayClick( event ) {
			if (
				event.target.classList.contains(
					'pet-favorites-modal__overlay'
				)
			) {
				actions.closeModal();
			}
		},

		handleKeydown( event ) {
			if ( event.key === 'Escape' ) {
				actions.closeModal();
			}
		},

		*refreshFavorites() {
			// Don't refresh while a clear is in-flight — the server may
			// not have processed the clear yet, so get-favorites would
			// return stale data.
			if ( isRefreshing || clearInFlight ) {
				return;
			}
			isRefreshing = true;

			const gen = stateGeneration;

			try {
				const result = yield executeAbility(
					'petsync/get-favorites',
					null,
					{ method: 'GET' }
				);

				// Abort if a clear happened while we were waiting.
				if ( gen !== stateGeneration || clearInFlight ) {
					return;
				}

				getGlobalState().favorites = result.favorites;

				if ( result.pets?.length ) {
					getGlobalActions().cachePets( result.pets );
				}

				const gs = getGlobalState();
				const validFavorites = [ ...gs.favorites ].filter(
					( id ) => !! ( gs.pets[ id ] || gs.pets[ String( id ) ] )
				);
				if ( validFavorites.length !== gs.favorites.length ) {
					gs.favorites = validFavorites;
				}
				storage.set( 'favorites', gs.favorites );
			} catch ( error ) {
				console.error( 'Failed to refresh favorites:', error );
			} finally {
				isRefreshing = false;
			}
		},

		handleClearClick() {
			// Second tap — execute the clear.
			if ( state.clearConfirming ) {
				resetModalState( state );
				actions.clearAllFavorites();
				return;
			}

			// First tap — enter confirm state, revert after 3 seconds.
			state.clearConfirming = true;

			// Read i18n labels from the button's data attributes on first use.
			if ( ! state._clearConfirmLabel ) {
				const btn = document.querySelector(
					'.pet-favorites-modal__clear-btn'
				);
				if ( btn ) {
					state._clearConfirmLabel =
						btn.dataset.confirmText || 'Tap again to confirm';
					state._clearDefaultLabel =
						btn.dataset.defaultText || 'Clear all favorites';
				}
			}

			clearTimeout( state._clearTimer );
			state._clearTimer = setTimeout( () => {
				state.clearConfirming = false;
			}, 3000 );
		},

		*clearAllFavorites() {
			const oldFavorites = [ ...getGlobalState().favorites ];
			if ( ! oldFavorites.length ) {
				return;
			}

			// Block refreshFavorites from running until the server confirms.
			clearInFlight = true;
			stateGeneration++;

			// Optimistic update + immediate localStorage persist.
			getGlobalState().favorites = [];
			storage.set( 'favorites', [] );
			oldFavorites.forEach( ( id ) => {
				const pet =
					getGlobalState().pets[ id ] ||
					getGlobalState().pets[ String( id ) ];
				if ( pet ) {
					pet.favorited = false;
				}
			} );
			announce( 'All favorites cleared' );

			// Close the modal — nothing left to show.
			actions.closeModal();

			try {
				// Single batch server call instead of N individual toggles.
				yield executeAbility( 'petsync/clear-favorites' );

				// Re-assert the cleared state in case a stale toggle-favorite
				// response (from a previous heart-button click) arrived and
				// re-added a pet via the toggle's add-if-absent logic.
				getGlobalState().favorites = [];
				storage.set( 'favorites', [] );
			} catch ( error ) {
				console.error( 'Failed to clear favorites:', error );
				getGlobalState().favorites = oldFavorites;
				storage.set( 'favorites', oldFavorites );
				oldFavorites.forEach( ( id ) => {
					const pet =
						getGlobalState().pets[ id ] ||
						getGlobalState().pets[ String( id ) ];
					if ( pet ) {
						pet.favorited = true;
					}
				} );
				getGlobalActions().notify(
					"Failed to clear favorites — they've been restored"
				);
			} finally {
				clearInFlight = false;
			}
		},
	},

	callbacks: {
		init() {
			storage.set( 'favorites', getGlobalState().favorites );
		},

		syncBadgeVisibility() {
			const { ref } = getElement();
			if ( getGlobalState().favorites.length > 0 ) {
				ref.removeAttribute( 'hidden' );
			} else {
				ref.setAttribute( 'hidden', '' );
			}
		},

		syncEmptyVisibility() {
			const { ref } = getElement();
			if ( getGlobalState().favorites.length > 0 ) {
				ref.setAttribute( 'hidden', '' );
			} else {
				ref.removeAttribute( 'hidden' );
			}
		},

		syncGridVisibility() {
			const { ref } = getElement();
			if ( getGlobalState().favorites.length > 0 ) {
				ref.removeAttribute( 'hidden' );
			} else {
				ref.setAttribute( 'hidden', '' );
			}
		},

		/**
		 * Imperatively sync the card DOM to match the current favorites.
		 *
		 * Reads getGlobalState().favorites and getGlobalState().pets to
		 * create Preact signal subscriptions. When either changes, this
		 * callback re-runs and reconciles the DOM:
		 *
		 * - New favorites → card created via buildCardHtml
		 * - Removed favorites → card removed from DOM
		 * - Existing cards → left untouched (stable DOM)
		 *
		 * This replaces data-wp-each which had a context-scoping bug
		 * where all cloned template elements shared the last item's
		 * context.pet value.
		 */
		syncCards() {
			const { ref } = getElement();

			// Read reactive signals to create subscriptions.
			const gs = getGlobalState();
			const favoriteIds = [ ...gs.favorites ];
			const petsCache = gs.pets;

			// Bind event delegation (re-binds if ref changed after navigation).
			bindGridDelegation( ref );

			// Build desired pet list (only pets that exist in the cache).
			// Try both numeric and string keys — PHP state uses string keys,
			// but favorites array and cachePet may use numbers.
			// Explicitly read each field from the reactive proxy — spread
			// ({ ...proxy }) is unreliable because the proxy's ownKeys trap
			// may not return properties that haven't been subscribed to.
			const desiredPets = favoriteIds
				.map( ( id ) => {
					const p = petsCache[ id ] || petsCache[ String( id ) ];
					if ( ! p ) {
						return null;
					}
					return {
						id: p.id,
						name: p.name,
						url: p.url,
						image: p.image,
						breed: p.breed,
						age: p.age,
						sex: p.sex,
						size: p.size,
						has_special_needs: p.has_special_needs,
						is_new: p.is_new,
						is_bonded_pair: p.is_bonded_pair,
						bonded_pair_names: p.bonded_pair_names,
						// Legacy key variants (some data sources use camelCase).
						isNew: p.isNew,
						specialNeeds: p.specialNeeds,
						isBondedPair: p.isBondedPair,
						bondedPartners: p.bondedPartners,
					};
				} )
				.filter( Boolean );

			// Clear all existing cards and rebuild from current data.
			// Favourites lists are small (typically < 20 pets), so the
			// performance cost of a full rebuild is negligible and eliminates
			// all stale-data issues (missing images, wrong badges, etc.).
			while ( ref.firstChild ) {
				ref.removeChild( ref.firstChild );
			}

			for ( const pet of desiredPets ) {
				ref.appendChild( buildCardElement( pet ) );
			}
		},
	},
} );

export { state, actions, callbacks };
