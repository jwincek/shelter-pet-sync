/**
 * Pet Listing Grid Block - Editor Controls
 * 
 * Adds InspectorControls for configuring which filters to display.
 * Uses vanilla JS with wp.element.createElement (no JSX/build required).
 */

( function( wp ) {
	const { createElement: el, Fragment } = wp.element;
	const { __ } = wp.i18n;
	const { addFilter } = wp.hooks;
	const { createHigherOrderComponent } = wp.compose;
	const { InspectorControls } = wp.blockEditor;
	const { 
		PanelBody, 
		ToggleControl,
		RangeControl,
		SelectControl,
	} = wp.components;

	/**
	 * Add inspector controls to the block editor.
	 */
	const withInspectorControls = createHigherOrderComponent( function( BlockEdit ) {
		return function( props ) {
			if ( props.name !== 'petsync/pet-listing-grid' ) {
				return el( BlockEdit, props );
			}

			const { attributes, setAttributes } = props;
			const {
				columns = 3,
				showFilters = true,
				showSearch = true,
				showResultsCount = true,
				badgeType = 'animal',
				// Taxonomy filters
				filterAnimal = true,
				filterBreed = true,
				filterAge = true,
				filterSex = true,
				filterSize = true,
				// Compatibility filters
				showCompatibilityFilters = true,
				filterGoodWithDogs = true,
				filterGoodWithCats = true,
				filterGoodWithKids = true,
				filterShotsCurrent = true,
				filterSpayedNeutered = true,
				filterHousebroken = true,
				filterSpecialNeeds = false,
				compatibilityStyle = 'chips',
			} = attributes;

			return el(
				Fragment,
				null,
				el( BlockEdit, props ),
				el(
					InspectorControls,
					null,
					// Display Settings
					el(
						PanelBody,
						{
							title: __( 'Display Settings', 'shelterkit-pets' ),
							initialOpen: true,
						},
						el( RangeControl, {
							label: __( 'Columns', 'shelterkit-pets' ),
							value: columns,
							onChange: function( value ) {
								setAttributes( { columns: value } );
							},
							min: 1,
							max: 6,
							step: 1,
						} ),
						el( SelectControl, {
							label: __( 'Badge Type', 'shelterkit-pets' ),
							value: badgeType,
							options: [
								{ label: __( 'Animal Type', 'shelterkit-pets' ), value: 'animal' },
								{ label: __( 'Age', 'shelterkit-pets' ), value: 'age' },
								{ label: __( 'New (7 days)', 'shelterkit-pets' ), value: 'new' },
								{ label: __( 'None', 'shelterkit-pets' ), value: 'none' },
							],
							onChange: function( value ) {
								setAttributes( { badgeType: value } );
							},
						} ),
						el( ToggleControl, {
							label: __( 'Show Results Count', 'shelterkit-pets' ),
							checked: showResultsCount,
							onChange: function( value ) {
								setAttributes( { showResultsCount: value } );
							},
						} )
					),
					// Filter Controls
					el(
						PanelBody,
						{
							title: __( 'Filter Settings', 'shelterkit-pets' ),
							initialOpen: false,
						},
						el( ToggleControl, {
							label: __( 'Show Search', 'shelterkit-pets' ),
							help: __( 'Search pets by name or breed', 'shelterkit-pets' ),
							checked: showSearch,
							onChange: function( value ) {
								setAttributes( { showSearch: value } );
							},
						} ),
						el( ToggleControl, {
							label: __( 'Show Filters', 'shelterkit-pets' ),
							checked: showFilters,
							onChange: function( value ) {
								setAttributes( { showFilters: value } );
							},
						} ),
						showFilters && el(
							Fragment,
							null,
							el( 'p', {
								className: 'components-base-control__label',
								style: { marginTop: '16px', marginBottom: '8px', fontWeight: '600' }
							}, __( 'Basic Filters', 'shelterkit-pets' ) ),
							el( ToggleControl, {
								label: __( 'Animal Type', 'shelterkit-pets' ),
								checked: filterAnimal,
								onChange: function( value ) {
									setAttributes( { filterAnimal: value } );
								},
							} ),
							el( ToggleControl, {
								label: __( 'Breed', 'shelterkit-pets' ),
								checked: filterBreed,
								onChange: function( value ) {
									setAttributes( { filterBreed: value } );
								},
							} ),
							el( ToggleControl, {
								label: __( 'Age', 'shelterkit-pets' ),
								checked: filterAge,
								onChange: function( value ) {
									setAttributes( { filterAge: value } );
								},
							} ),
							el( ToggleControl, {
								label: __( 'Sex', 'shelterkit-pets' ),
								checked: filterSex,
								onChange: function( value ) {
									setAttributes( { filterSex: value } );
								},
							} ),
							el( ToggleControl, {
								label: __( 'Size', 'shelterkit-pets' ),
								checked: filterSize,
								onChange: function( value ) {
									setAttributes( { filterSize: value } );
								},
							} )
						)
					),
					// Compatibility Filters
					showFilters && el(
						PanelBody,
						{
							title: __( 'Compatibility Filters', 'shelterkit-pets' ),
							initialOpen: false,
						},
						el( ToggleControl, {
							label: __( 'Show Compatibility Filters', 'shelterkit-pets' ),
							help: __( 'Filters for "good with" and health status', 'shelterkit-pets' ),
							checked: showCompatibilityFilters,
							onChange: function( value ) {
								setAttributes( { showCompatibilityFilters: value } );
							},
						} ),
						showCompatibilityFilters && el(
							Fragment,
							null,
							el( SelectControl, {
								label: __( 'Filter Style', 'shelterkit-pets' ),
								value: compatibilityStyle,
								options: [
									{ label: __( 'Chips (pill buttons)', 'shelterkit-pets' ), value: 'chips' },
									{ label: __( 'Checkboxes (grouped)', 'shelterkit-pets' ), value: 'checkboxes' },
								],
								onChange: function( value ) {
									setAttributes( { compatibilityStyle: value } );
								},
							} ),
							el( 'p', {
								className: 'components-base-control__label',
								style: { marginTop: '16px', marginBottom: '8px', fontWeight: '600' }
							}, __( 'Good With', 'shelterkit-pets' ) ),
							el( ToggleControl, {
								label: __( 'Dogs', 'shelterkit-pets' ),
								checked: filterGoodWithDogs,
								onChange: function( value ) {
									setAttributes( { filterGoodWithDogs: value } );
								},
							} ),
							el( ToggleControl, {
								label: __( 'Cats', 'shelterkit-pets' ),
								checked: filterGoodWithCats,
								onChange: function( value ) {
									setAttributes( { filterGoodWithCats: value } );
								},
							} ),
							el( ToggleControl, {
								label: __( 'Kids', 'shelterkit-pets' ),
								checked: filterGoodWithKids,
								onChange: function( value ) {
									setAttributes( { filterGoodWithKids: value } );
								},
							} ),
							el( 'p', {
								className: 'components-base-control__label',
								style: { marginTop: '16px', marginBottom: '8px', fontWeight: '600' }
							}, __( 'Health & Training', 'shelterkit-pets' ) ),
							el( ToggleControl, {
								label: __( 'Shots Current', 'shelterkit-pets' ),
								checked: filterShotsCurrent,
								onChange: function( value ) {
									setAttributes( { filterShotsCurrent: value } );
								},
							} ),
							el( ToggleControl, {
								label: __( 'Spayed/Neutered', 'shelterkit-pets' ),
								checked: filterSpayedNeutered,
								onChange: function( value ) {
									setAttributes( { filterSpayedNeutered: value } );
								},
							} ),
							el( ToggleControl, {
								label: __( 'Housebroken', 'shelterkit-pets' ),
								checked: filterHousebroken,
								onChange: function( value ) {
									setAttributes( { filterHousebroken: value } );
								},
							} ),
							el( ToggleControl, {
								label: __( 'Special Needs', 'shelterkit-pets' ),
								help: __( 'Show pets with special needs', 'shelterkit-pets' ),
								checked: filterSpecialNeeds,
								onChange: function( value ) {
									setAttributes( { filterSpecialNeeds: value } );
								},
							} )
						)
					)
				)
			);
		};
	}, 'withListingGridInspectorControls' );

	addFilter(
		'editor.BlockEdit',
		'petsync/pet-listing-grid/inspector-controls',
		withInspectorControls
	);

} )( window.wp );
