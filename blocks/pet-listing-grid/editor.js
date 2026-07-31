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
							title: __( 'Display Settings', 'shelter-pets' ),
							initialOpen: true,
						},
						el( RangeControl, {
							label: __( 'Columns', 'shelter-pets' ),
							value: columns,
							onChange: function( value ) {
								setAttributes( { columns: value } );
							},
							min: 1,
							max: 6,
							step: 1,
						} ),
						el( SelectControl, {
							label: __( 'Badge Type', 'shelter-pets' ),
							value: badgeType,
							options: [
								{ label: __( 'Animal Type', 'shelter-pets' ), value: 'animal' },
								{ label: __( 'Age', 'shelter-pets' ), value: 'age' },
								{ label: __( 'New (7 days)', 'shelter-pets' ), value: 'new' },
								{ label: __( 'None', 'shelter-pets' ), value: 'none' },
							],
							onChange: function( value ) {
								setAttributes( { badgeType: value } );
							},
						} ),
						el( ToggleControl, {
							label: __( 'Show Results Count', 'shelter-pets' ),
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
							title: __( 'Filter Settings', 'shelter-pets' ),
							initialOpen: false,
						},
						el( ToggleControl, {
							label: __( 'Show Search', 'shelter-pets' ),
							help: __( 'Search pets by name or breed', 'shelter-pets' ),
							checked: showSearch,
							onChange: function( value ) {
								setAttributes( { showSearch: value } );
							},
						} ),
						el( ToggleControl, {
							label: __( 'Show Filters', 'shelter-pets' ),
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
							}, __( 'Basic Filters', 'shelter-pets' ) ),
							el( ToggleControl, {
								label: __( 'Animal Type', 'shelter-pets' ),
								checked: filterAnimal,
								onChange: function( value ) {
									setAttributes( { filterAnimal: value } );
								},
							} ),
							el( ToggleControl, {
								label: __( 'Breed', 'shelter-pets' ),
								checked: filterBreed,
								onChange: function( value ) {
									setAttributes( { filterBreed: value } );
								},
							} ),
							el( ToggleControl, {
								label: __( 'Age', 'shelter-pets' ),
								checked: filterAge,
								onChange: function( value ) {
									setAttributes( { filterAge: value } );
								},
							} ),
							el( ToggleControl, {
								label: __( 'Sex', 'shelter-pets' ),
								checked: filterSex,
								onChange: function( value ) {
									setAttributes( { filterSex: value } );
								},
							} ),
							el( ToggleControl, {
								label: __( 'Size', 'shelter-pets' ),
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
							title: __( 'Compatibility Filters', 'shelter-pets' ),
							initialOpen: false,
						},
						el( ToggleControl, {
							label: __( 'Show Compatibility Filters', 'shelter-pets' ),
							help: __( 'Filters for "good with" and health status', 'shelter-pets' ),
							checked: showCompatibilityFilters,
							onChange: function( value ) {
								setAttributes( { showCompatibilityFilters: value } );
							},
						} ),
						showCompatibilityFilters && el(
							Fragment,
							null,
							el( SelectControl, {
								label: __( 'Filter Style', 'shelter-pets' ),
								value: compatibilityStyle,
								options: [
									{ label: __( 'Chips (pill buttons)', 'shelter-pets' ), value: 'chips' },
									{ label: __( 'Checkboxes (grouped)', 'shelter-pets' ), value: 'checkboxes' },
								],
								onChange: function( value ) {
									setAttributes( { compatibilityStyle: value } );
								},
							} ),
							el( 'p', {
								className: 'components-base-control__label',
								style: { marginTop: '16px', marginBottom: '8px', fontWeight: '600' }
							}, __( 'Good With', 'shelter-pets' ) ),
							el( ToggleControl, {
								label: __( 'Dogs', 'shelter-pets' ),
								checked: filterGoodWithDogs,
								onChange: function( value ) {
									setAttributes( { filterGoodWithDogs: value } );
								},
							} ),
							el( ToggleControl, {
								label: __( 'Cats', 'shelter-pets' ),
								checked: filterGoodWithCats,
								onChange: function( value ) {
									setAttributes( { filterGoodWithCats: value } );
								},
							} ),
							el( ToggleControl, {
								label: __( 'Kids', 'shelter-pets' ),
								checked: filterGoodWithKids,
								onChange: function( value ) {
									setAttributes( { filterGoodWithKids: value } );
								},
							} ),
							el( 'p', {
								className: 'components-base-control__label',
								style: { marginTop: '16px', marginBottom: '8px', fontWeight: '600' }
							}, __( 'Health & Training', 'shelter-pets' ) ),
							el( ToggleControl, {
								label: __( 'Shots Current', 'shelter-pets' ),
								checked: filterShotsCurrent,
								onChange: function( value ) {
									setAttributes( { filterShotsCurrent: value } );
								},
							} ),
							el( ToggleControl, {
								label: __( 'Spayed/Neutered', 'shelter-pets' ),
								checked: filterSpayedNeutered,
								onChange: function( value ) {
									setAttributes( { filterSpayedNeutered: value } );
								},
							} ),
							el( ToggleControl, {
								label: __( 'Housebroken', 'shelter-pets' ),
								checked: filterHousebroken,
								onChange: function( value ) {
									setAttributes( { filterHousebroken: value } );
								},
							} ),
							el( ToggleControl, {
								label: __( 'Special Needs', 'shelter-pets' ),
								help: __( 'Show pets with special needs', 'shelter-pets' ),
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
