/**
 * Pet Slider Block - View Script Module
 *
 * Entry point for the slider block's client-side interactivity.
 * Imports and re-exports from the shared slider module.
 *
 * @package ShelterKit_Pets
 * @since 2.1.0
 */

// Import the slider store - this registers it with the Interactivity API
import { state, actions, SliderAutoplay } from '../../assets/js/interactivity/slider.js';

// Also ensure the global store is loaded for favorites/comparison
import '../../assets/js/store.js';

export { state, actions, SliderAutoplay };
