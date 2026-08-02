/**
 * Pet Listing Grid Block - View Script Module
 *
 * Entry point for the grid block's client-side interactivity.
 * Imports and re-exports from the shared grid module.
 *
 * @package Shelter_Pets
 * @since 2.1.0
 */

// Import the grid store - this registers it with the Interactivity API
import { state, actions, callbacks } from '../../assets/js/interactivity/grid.js';

// Also ensure the global store is loaded for favorites/comparison
import '../../assets/js/store.js';

export { state, actions, callbacks };
