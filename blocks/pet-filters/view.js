/**
 * Pet Filters Block - View Script Module
 *
 * Entry point for the filters block's client-side interactivity.
 * Imports and re-exports from the shared filters module.
 *
 * @package Shelter_Pets
 * @since 2.1.0
 */

// Import the filters store - this registers it with the Interactivity API
import { state, actions } from '../../assets/js/interactivity/filters.js';

// Also ensure the global store is loaded
import '../../assets/js/store.js';

export { state, actions };
