/**
 * Pet Compare Bar Block - View Script Module
 *
 * Entry point for the compare bar block's client-side interactivity.
 * Imports and re-exports from the shared compare-bar module.
 *
 * @package Shelter_Pets
 * @since 2.1.0
 */

// Import the compare-bar store - this registers it with the Interactivity API
import { state, actions, callbacks } from '../../assets/js/interactivity/compare-bar.js';

// Also ensure the global store is loaded
import '../../assets/js/store.js';

export { state, actions, callbacks };
