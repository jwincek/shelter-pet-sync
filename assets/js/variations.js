/**
 * Petstablished Block Variations
 *
 * Registers block variations for core blocks pre-configured with pet data bindings.
 * Users can insert "Pet Name", "Pet Breed", etc. without manual binding setup.
 *
 * @package
 * @since 2.0.0
 */

( function () {
	'use strict';

	const { registerBlockVariation } = wp.blocks;

	wp.domReady( function () {
		if ( ! window.petstablishedVariations ) {
			return;
		}

		const { simple, groups } = window.petstablishedVariations;

		// Register simple variations (single blocks with bindings).
		if ( simple && Array.isArray( simple ) ) {
			simple.forEach( function ( variation ) {
				registerBlockVariation( variation.block, {
					name: variation.name,
					title: variation.title,
					description: variation.description,
					icon: getIcon( variation.icon ),
					keywords: variation.keywords || [],
					category: 'petsync',
					scope: [ 'inserter', 'transform' ],
					attributes: variation.attributes || {},
					isActive( blockAttributes, variationAttributes ) {
						return isVariationActive(
							blockAttributes,
							variationAttributes,
							variation.isActive
						);
					},
					example: {
						attributes: getExampleAttributes( variation ),
					},
				} );
			} );
		}

		// Register group variations (compound blocks with inner blocks).
		if ( groups && Array.isArray( groups ) ) {
			groups.forEach( function ( variation ) {
				registerBlockVariation( variation.block, {
					name: variation.name,
					title: variation.title,
					description: variation.description,
					icon: getIcon( variation.icon ),
					keywords: variation.keywords || [],
					category: 'petsync',
					scope: variation.scope || [ 'inserter' ],
					attributes: variation.attributes || {},
					innerBlocks: buildInnerBlocks(
						variation.innerBlocks || []
					),
					isActive( blockAttributes ) {
						// Match by className for group variations.
						const className = variation.attributes?.className;
						if ( className && blockAttributes?.className ) {
							return blockAttributes.className.includes(
								className
							);
						}
						return false;
					},
					example: {
						attributes: variation.attributes || {},
						innerBlocks: buildExampleInnerBlocks(
							variation.innerBlocks || []
						),
					},
				} );
			} );
		}
	} );

	/**
	 * Get icon element or dashicon string.
	 * @param icon
	 */
	function getIcon( icon ) {
		// Dashicons are prefixed with 'dashicons-' in the admin.
		// For block editor, we can use the icon name directly or an SVG element.
		const petIcons = {
			pets: wp.element.createElement(
				'svg',
				{
					xmlns: 'http://www.w3.org/2000/svg',
					viewBox: '0 0 24 24',
					width: 24,
					height: 24,
				},
				wp.element.createElement( 'path', {
					d: 'M4.5 9.5a2.5 2.5 0 1 1 0-5 2.5 2.5 0 0 1 0 5zm5-5a2.5 2.5 0 1 1 0-5 2.5 2.5 0 0 1 0 5zm5 0a2.5 2.5 0 1 1 0-5 2.5 2.5 0 0 1 0 5zm5 5a2.5 2.5 0 1 1 0-5 2.5 2.5 0 0 1 0 5zM12 22c-3.5 0-6.5-2.5-6.5-6.5 0-2.5 1.5-5 4-6.5 1-.5 2-.5 2.5-.5s1.5 0 2.5.5c2.5 1.5 4 4 4 6.5 0 4-3 6.5-6.5 6.5z',
				} )
			),
		};

		if ( petIcons[ icon ] ) {
			return petIcons[ icon ];
		}

		// Return dashicon name for WordPress to handle.
		return icon;
	}

	/**
	 * Check if a variation is active based on binding key path.
	 * @param blockAttributes
	 * @param variationAttributes
	 * @param activeChecks
	 */
	function isVariationActive(
		blockAttributes,
		variationAttributes,
		activeChecks
	) {
		if ( ! activeChecks || ! Array.isArray( activeChecks ) ) {
			return false;
		}

		// Check each path (e.g., 'metadata.bindings.content.args.key').
		return activeChecks.every( function ( path ) {
			const blockValue = getNestedValue( blockAttributes, path );
			const varValue = getNestedValue( variationAttributes, path );
			return blockValue === varValue;
		} );
	}

	/**
	 * Get nested value from object using dot notation path.
	 * @param obj
	 * @param path
	 */
	function getNestedValue( obj, path ) {
		if ( ! obj || ! path ) {
			return undefined;
		}
		return path.split( '.' ).reduce( function ( current, key ) {
			return current && current[ key ] !== undefined
				? current[ key ]
				: undefined;
		}, obj );
	}

	/**
	 * Get example attributes with placeholder content.
	 * @param variation
	 */
	function getExampleAttributes( variation ) {
		const attrs = { ...( variation.attributes || {} ) };
		const key = getBindingKey( attrs );

		if ( key ) {
			attrs.content = getExampleContent( key );
		}

		return attrs;
	}

	/**
	 * Get the binding key from attributes.
	 * @param attributes
	 */
	function getBindingKey( attributes ) {
		const bindings = attributes?.metadata?.bindings;
		if ( ! bindings ) {
			return null;
		}

		// Check content, url, or first available binding.
		const attrs = [ 'content', 'url', 'text' ];
		for ( const attr of attrs ) {
			if ( bindings[ attr ]?.args?.key ) {
				return bindings[ attr ].args.key;
			}
		}

		// Return first found key.
		const firstBinding = Object.values( bindings )[ 0 ];
		return firstBinding?.args?.key || null;
	}

	/**
	 * Get example content for a binding key.
	 * @param key
	 */
	function getExampleContent( key ) {
		const examples = {
			name: 'Buddy',
			status: 'Available',
			animal: 'Dog',
			breed: 'Golden Retriever',
			age: 'Young',
			sex: 'Male',
			size: 'Large',
			color: 'Golden',
			coat: 'Medium',
			tagline: 'Dog · Golden Retriever · Young · Male',
			compatibility: 'Good with dogs, cats, kids',
			weight: '65 lbs',
			adoption_fee_formatted: '$250',
			description:
				'A friendly and energetic companion looking for a forever home.',
			story_title: 'Meet Buddy',
			adoption_title: 'Adopt Buddy',
			url: '#',
			adoption_form_url: '#',
			image: window.petstablishedVariations?.placeholderImage || '',
			// Boolean fields
			shots_current: 'Yes',
			spayed_neutered: 'Yes',
			housebroken: 'Yes',
			ok_with_dogs: 'Yes',
			ok_with_cats: 'Yes',
			ok_with_kids: 'Yes',
			has_special_needs: 'No',
			// Additional computed
			gallery_count: '5 photos',
			is_new: 'true',
			// Adoption stats
			available_count: '42',
			available_by_species: '23 Dogs, 12 Cats, 7 Rabbits',
			newest_pet_name: 'Buddy',
			total_pets: '58',
		};

		return examples[ key ] || '[' + key + ']';
	}

	/**
	 * Build innerBlocks array for registration.
	 * @param innerBlocksDef
	 */
	function buildInnerBlocks( innerBlocksDef ) {
		return innerBlocksDef.map( function ( def ) {
			const [ name, attributes ] = def;
			return [ name, attributes || {} ];
		} );
	}

	/**
	 * Build example innerBlocks with content.
	 * @param innerBlocksDef
	 */
	function buildExampleInnerBlocks( innerBlocksDef ) {
		return innerBlocksDef.map( function ( def ) {
			const [ name, attributes ] = def;
			const exampleAttrs = { ...( attributes || {} ) };
			const key = getBindingKey( exampleAttrs );

			if ( key ) {
				// Add example content based on block type.
				if ( name === 'core/button' ) {
					// Button keeps its text attribute.
				} else if ( name === 'core/image' ) {
					exampleAttrs.url = getExampleContent( key );
					// Add alt text for images.
					const altKey =
						exampleAttrs?.metadata?.bindings?.alt?.args?.key;
					if ( altKey ) {
						exampleAttrs.alt = getExampleContent( altKey );
					}
				} else {
					exampleAttrs.content = getExampleContent( key );
				}
			}

			return {
				name,
				attributes: exampleAttrs,
			};
		} );
	}
} )();
