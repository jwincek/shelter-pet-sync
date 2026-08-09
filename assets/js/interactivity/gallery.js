/**
 * Pet Gallery Interactivity Store
 *
 * Sole owner of lightbox functionality for pet images.
 * Each gallery instance maintains its own state via context:
 *   - images[]       : Array of { url, alt } objects
 *   - currentIndex   : Currently displayed image index
 *   - isOpen         : Whether the lightbox is visible
 *
 * Keyboard navigation (Escape, ArrowLeft, ArrowRight) is handled via
 * the data-wp-on--keydown directive on the lightbox element itself,
 * which naturally scopes to the focused lightbox without needing
 * document-level listeners.
 *
 * @package
 * @since 3.0.0
 */

import { store, getContext, getElement } from '@wordpress/interactivity';
import { overflowLock } from './favorites-modal.js';

const { state, actions } = store( 'petsync/gallery', {
	state: {
		get currentImageUrl() {
			const ctx = getContext();
			return ctx.images?.[ ctx.currentIndex ]?.url || '';
		},

		get currentImageAlt() {
			const ctx = getContext();
			return ctx.images?.[ ctx.currentIndex ]?.alt || '';
		},

		get currentNumber() {
			const ctx = getContext();
			return ( ctx.currentIndex || 0 ) + 1;
		},

		get totalImages() {
			const ctx = getContext();
			return ctx.images?.length || 0;
		},

		get hasMultipleImages() {
			const ctx = getContext();
			return ( ctx.images?.length || 0 ) > 1;
		},
	},

	actions: {
		open() {
			const ctx = getContext();
			const { ref } = getElement();

			// Read the clicked image index from the data-index attribute.
			const clickedIndex = parseInt( ref?.dataset?.index || '0', 10 );
			ctx.currentIndex = clickedIndex;
			ctx.isOpen = true;

			// Remember the trigger so close() can return focus to it.
			ctx._triggerElement = ref;

			// Prevent body scroll while lightbox is open. A modal dialog does not
			// reliably lock background scrolling on its own.
			overflowLock.lock();

			// No manual focus call: callbacks.syncLightbox calls showModal(),
			// which focuses the dialog and contains focus within it.
		},

		close() {
			const ctx = getContext();
			const trigger = ctx._triggerElement;
			ctx.isOpen = false;
			ctx._triggerElement = null;
			overflowLock.unlock();

			// Return focus to the element that opened the lightbox.
			if ( trigger ) {
				requestAnimationFrame( () => trigger.focus() );
			}
		},

		next() {
			const ctx = getContext();
			if ( ! ctx.images?.length || ctx.images.length <= 1 ) {
				return;
			}
			ctx.currentIndex = ( ctx.currentIndex + 1 ) % ctx.images.length;
		},

		prev() {
			const ctx = getContext();
			if ( ! ctx.images?.length || ctx.images.length <= 1 ) {
				return;
			}
			const len = ctx.images.length;
			ctx.currentIndex = ( ctx.currentIndex - 1 + len ) % len;
		},

		/**
		 * The dialog closed by a route other than actions.close() — Escape, or
		 * a form[method=dialog] submission. Without this the store would still
		 * believe the lightbox is open, callbacks.syncLightbox would see no
		 * change to make, and the lightbox could never be reopened.
		 *
		 * Guarded so the ordinary path does not recurse: actions.close() sets
		 * isOpen false, the watch calls dialog.close(), the browser fires
		 * `close`, and this returns immediately.
		 */
		handleDialogClose() {
			const ctx = getContext();
			if ( ! ctx.isOpen ) {
				return;
			}
			actions.close();
		},

		/**
		 * Keyboard handler — bound via data-wp-on--keydown on the lightbox.
		 * showModal() focuses the dialog and contains focus, so these reach it
		 * without document-level listeners.
		 *
		 * Escape is deliberately absent: a modal dialog dismisses on Escape
		 * natively, and handleDialogClose syncs the store afterwards. Handling
		 * it here as well would close it twice.
		 * @param event
		 */
		handleKeydown( event ) {
			const ctx = getContext();
			if ( ! ctx.isOpen ) {
				return;
			}

			switch ( event.key ) {
				case 'ArrowRight':
					actions.next();
					event.preventDefault();
					break;
				case 'ArrowLeft':
					actions.prev();
					event.preventDefault();
					break;
			}
		},

		handleBackdropClick( event ) {
			// Only close if clicking the backdrop itself, not the image.
			if ( event.target === event.currentTarget ) {
				actions.close();
			}
		},
	},

	callbacks: {
		/**
		 * Drive the native dialog from context.isOpen.
		 *
		 * The lightbox is a <dialog>, and only showModal() promotes an element
		 * into the browser's top layer. That is the whole point of the element
		 * choice: the top layer is outside the stacking-context tree, so the
		 * position:sticky ancestor that used to bury the lightbox cannot reach
		 * it. Toggling an attribute would not do this — the promotion happens
		 * as a side effect of the method call, so it has to be imperative.
		 *
		 * Both branches check dialog.open first, which makes this idempotent
		 * and keeps it from fighting the browser: showModal() on an already
		 * open dialog throws InvalidStateError.
		 */
		syncLightbox() {
			const { ref } = getElement();

			// The typeof guard is not redundant. Where <dialog> is unsupported
			// HTMLDialogElement is undefined, and `instanceof undefined` throws
			// a TypeError rather than returning false — which would take the
			// whole store down instead of degrading to a lightbox that does not
			// open. Theoretical on any browser this plugin supports, but the
			// failure mode is bad enough to be worth two words.
			if (
				typeof HTMLDialogElement === 'undefined' ||
				! ( ref instanceof HTMLDialogElement )
			) {
				return;
			}

			const ctx = getContext();

			if ( ctx.isOpen && ! ref.open ) {
				ref.showModal();
			} else if ( ! ctx.isOpen && ref.open ) {
				ref.close();
			}
		},
	},
} );

export { state, actions };
