/**
 * Pet Gallery Block — View Script Module
 *
 * Entry point for the gallery block's client-side interactivity.
 * Imports from the shared gallery store module which registers
 * the petsync/gallery namespace with the Interactivity API.
 *
 * @package ShelterKit_Pets
 * @since 3.0.0
 */

// Import the gallery store — this registers it with the Interactivity API.
import { state, actions } from '../../assets/js/interactivity/gallery.js';

// Ensure the global store is loaded for favorites/comparison context.
import '../../assets/js/store.js';

export { state, actions };
