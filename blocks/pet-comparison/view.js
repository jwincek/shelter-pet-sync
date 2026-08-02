/**
 * Pet Comparison Block - View Script Module
 *
 * Entry point for the comparison block's client-side interactivity.
 * Imports the comparison store to register it with the Interactivity API.
 *
 * @package Shelter_Pets
 * @since 4.3.0
 */

import { state, actions, callbacks } from '../../assets/js/interactivity/comparison.js';

// Ensure the global store is loaded.
import '../../assets/js/store.js';

export { state, actions, callbacks };
