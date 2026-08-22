/**
 * Petstablished Blocks - Editor Registration
 *
 * Registers server-side rendered blocks in the block editor.
 *
 * @param wp
 * @package
 * @since 2.0.0
 */

( function ( wp ) {
	const { registerBlockType } = wp.blocks;
	const {
		useBlockProps,
		InspectorControls,
		InnerBlocks,
		MediaUpload,
		MediaUploadCheck,
	} = wp.blockEditor;
	const {
		PanelBody,
		ToggleControl,
		SelectControl,
		RangeControl,
		TextControl,
		Button,
		ComboboxControl,
	} = wp.components;
	const { createElement: el } = wp.element;
	const { __ } = wp.i18n;
	const ServerSideRender = wp.serverSideRender;

	// === SVG Icon Library ===
	const icons = {
		paw: el(
			'svg',
			{
				width: 24,
				height: 24,
				viewBox: '0 0 24 24',
				fill: 'none',
				stroke: 'currentColor',
				strokeWidth: 2,
			},
			el( 'path', {
				d: 'M10 5.172C10 3.782 8.423 2.679 6.5 3c-2.823.47-4.113 6.006-4 7 .08.703 1.725 1.722 3.656 1 1.261-.472 1.96-1.45 2.344-2.5M14 5.172c0-1.39 1.577-2.493 3.5-2.172 2.823.47 4.113 6.006 4 7-.08.703-1.725 1.722-3.656 1-1.261-.472-1.96-1.45-2.344-2.5',
			} ),
			el( 'path', {
				d: 'M8 14v.5M16 14v.5M11.25 16.25h1.5L12 17l-.75-.75Z',
			} ),
			el( 'path', {
				d: 'M4.42 11.247A13.152 13.152 0 0 0 4 14.556C4 18.728 7.582 21 12 21s8-2.272 8-6.444c0-1.061-.162-2.2-.493-3.309',
			} )
		),
		dog: el(
			'svg',
			{
				width: 20,
				height: 20,
				viewBox: '0 0 24 24',
				fill: 'none',
				stroke: 'currentColor',
				strokeWidth: 2,
				strokeLinecap: 'round',
				strokeLinejoin: 'round',
			},
			el( 'path', {
				d: 'M10 5.172C10 3.782 8.423 2.679 6.5 3c-2.823.47-4.113 6.006-4 7 .08.703 1.725 1.722 3.656 1 1.261-.472 1.96-1.45 2.344-2.5M14.267 5.172c0-1.39 1.577-2.493 3.5-2.172 2.823.47 4.113 6.006 4 7-.08.703-1.725 1.722-3.656 1-1.261-.472-1.855-1.45-2.239-2.5',
			} ),
			el( 'path', {
				d: 'M8 14v.5M16 14v.5M11.25 16.25h1.5L12 17l-.75-.75z',
			} ),
			el( 'path', {
				d: 'M4.42 11.247A13.152 13.152 0 0 0 4 14.556C4 18.728 7.582 21 12 21s8-2.272 8-6.444a11.702 11.702 0 0 0-.493-3.309',
			} )
		),
		cat: el(
			'svg',
			{
				width: 20,
				height: 20,
				viewBox: '0 0 24 24',
				fill: 'none',
				stroke: 'currentColor',
				strokeWidth: 2,
				strokeLinecap: 'round',
				strokeLinejoin: 'round',
			},
			el( 'path', {
				d: 'M12 5c.67 0 1.35.09 2 .26 1.78-2 5.03-2.84 6.42-2.26 1.4.58-.42 7-.42 7 .57 1.07 1 2.24 1 3.44C21 17.9 16.97 21 12 21s-9-3.1-9-7.56c0-1.25.5-2.4 1-3.44 0 0-1.89-6.42-.5-7 1.39-.58 4.72.23 6.5 2.23A9.04 9.04 0 0 1 12 5z',
			} ),
			el( 'path', {
				d: 'M8 14v.5M16 14v.5M11.25 16.25h1.5L12 17l-.75-.75z',
			} )
		),
		child: el(
			'svg',
			{
				width: 20,
				height: 20,
				viewBox: '0 0 24 24',
				fill: 'none',
				stroke: 'currentColor',
				strokeWidth: 2,
				strokeLinecap: 'round',
				strokeLinejoin: 'round',
			},
			el( 'circle', { cx: 12, cy: 4, r: 2 } ),
			el( 'path', { d: 'M12 8v4m0 0-3 7m3-7 3 7M9 12h6' } )
		),
		check: el(
			'svg',
			{
				width: 16,
				height: 16,
				viewBox: '0 0 24 24',
				fill: 'none',
				stroke: 'currentColor',
				strokeWidth: 3,
				strokeLinecap: 'round',
				strokeLinejoin: 'round',
			},
			el( 'path', { d: 'M20 6L9 17l-5-5' } )
		),
		x: el(
			'svg',
			{
				width: 16,
				height: 16,
				viewBox: '0 0 24 24',
				fill: 'none',
				stroke: 'currentColor',
				strokeWidth: 3,
				strokeLinecap: 'round',
				strokeLinejoin: 'round',
			},
			el( 'line', { x1: 18, y1: 6, x2: 6, y2: 18 } ),
			el( 'line', { x1: 6, y1: 6, x2: 18, y2: 18 } )
		),
		question: el(
			'svg',
			{
				width: 16,
				height: 16,
				viewBox: '0 0 24 24',
				fill: 'none',
				stroke: 'currentColor',
				strokeWidth: 2,
				strokeLinecap: 'round',
				strokeLinejoin: 'round',
			},
			el( 'circle', { cx: 12, cy: 12, r: 10 } ),
			el( 'path', { d: 'M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3' } ),
			el( 'line', { x1: 12, y1: 17, x2: 12.01, y2: 17 } )
		),
		heart: el(
			'svg',
			{
				width: 20,
				height: 20,
				viewBox: '0 0 24 24',
				fill: 'none',
				stroke: 'currentColor',
				strokeWidth: 2,
				strokeLinecap: 'round',
				strokeLinejoin: 'round',
			},
			el( 'path', {
				d: 'M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z',
			} )
		),
		columns: el(
			'svg',
			{
				width: 20,
				height: 20,
				viewBox: '0 0 24 24',
				fill: 'none',
				stroke: 'currentColor',
				strokeWidth: 2,
				strokeLinecap: 'round',
				strokeLinejoin: 'round',
			},
			el( 'rect', { x: 3, y: 3, width: 18, height: 18, rx: 2, ry: 2 } ),
			el( 'line', { x1: 12, y1: 3, x2: 12, y2: 21 } )
		),
		share: el(
			'svg',
			{
				width: 20,
				height: 20,
				viewBox: '0 0 24 24',
				fill: 'none',
				stroke: 'currentColor',
				strokeWidth: 2,
				strokeLinecap: 'round',
				strokeLinejoin: 'round',
			},
			el( 'circle', { cx: 18, cy: 5, r: 3 } ),
			el( 'circle', { cx: 6, cy: 12, r: 3 } ),
			el( 'circle', { cx: 18, cy: 19, r: 3 } ),
			el( 'line', { x1: 8.59, y1: 13.51, x2: 15.42, y2: 17.49 } ),
			el( 'line', { x1: 15.41, y1: 6.51, x2: 8.59, y2: 10.49 } )
		),
		image: el(
			'svg',
			{
				width: 48,
				height: 48,
				viewBox: '0 0 24 24',
				fill: 'none',
				stroke: 'currentColor',
				strokeWidth: 1.5,
				strokeLinecap: 'round',
				strokeLinejoin: 'round',
			},
			el( 'rect', { x: 3, y: 3, width: 18, height: 18, rx: 2, ry: 2 } ),
			el( 'circle', { cx: 8.5, cy: 8.5, r: 1.5 } ),
			el( 'path', { d: 'M21 15l-5-5L5 21' } )
		),
	};

	// Common icon for pet blocks.
	const pawIcon = icons.paw;

	// === Pet Card ===
	registerBlockType( 'petsync/pet-card', {
		title: __( 'Pet Card', 'shelterkit-pets' ),
		description: __(
			'Display a single pet card with image and details.',
			'shelterkit-pets'
		),
		category: 'widgets',
		icon: pawIcon,
		keywords: [ 'pet', 'animal', 'adoption', 'card' ],
		supports: { html: false },
		attributes: {
			showFavorite: { type: 'boolean', default: true },
			showCompare: { type: 'boolean', default: true },
			showStatus: { type: 'boolean', default: true },
		},
		usesContext: [ 'postId', 'postType' ],
		edit( props ) {
			const { attributes, setAttributes } = props;
			const blockProps = useBlockProps();

			return el(
				'div',
				blockProps,
				el(
					InspectorControls,
					{},
					el(
						PanelBody,
						{
							title: __( 'Display Options', 'shelterkit-pets' ),
						},
						el( ToggleControl, {
							label: __(
								'Show Favorite Button',
								'shelterkit-pets'
							),
							checked: attributes.showFavorite,
							onChange: ( val ) =>
								setAttributes( { showFavorite: val } ),
						} ),
						el( ToggleControl, {
							label: __(
								'Show Compare Button',
								'shelterkit-pets'
							),
							checked: attributes.showCompare,
							onChange: ( val ) =>
								setAttributes( { showCompare: val } ),
						} ),
						el( ToggleControl, {
							label: __( 'Show Status Badge', 'shelterkit-pets' ),
							checked: attributes.showStatus,
							onChange: ( val ) =>
								setAttributes( { showStatus: val } ),
						} )
					)
				),
				el( ServerSideRender, {
					block: 'petsync/pet-card',
					attributes,
				} )
			);
		},
		save: () => null,
	} );

	// === Pet Listing Grid ===
	registerBlockType( 'petsync/pet-listing-grid', {
		title: __( 'Pet Listing Grid', 'shelterkit-pets' ),
		description: __(
			'Display a filterable grid of adoptable pets with instant client-side filtering.',
			'shelterkit-pets'
		),
		category: 'widgets',
		icon: pawIcon,
		keywords: [ 'pet', 'listing', 'grid', 'archive', 'adoption' ],
		supports: { html: false, align: [ 'wide', 'full' ] },
		attributes: {
			columns: { type: 'number', default: 3 },
			showFilters: { type: 'boolean', default: true },
			showResultsCount: { type: 'boolean', default: true },
			badgeType: { type: 'string', default: 'animal' },
		},
		edit( props ) {
			const blockProps = useBlockProps();

			// Inspector controls live in blocks/pet-listing-grid/editor.js
			// (Display Settings / Filter Settings / Compatibility Filters);
			// adding panels here would duplicate them.
			return el(
				'div',
				blockProps,
				el( ServerSideRender, {
					block: 'petsync/pet-listing-grid',
					attributes: props.attributes,
				} )
			);
		},
		save: () => null,
	} );

	// === Pet Details ===
	registerBlockType( 'petsync/pet-details', {
		title: __( 'Pet Details', 'shelterkit-pets' ),
		description: __(
			'Display comprehensive pet profile information.',
			'shelterkit-pets'
		),
		category: 'widgets',
		icon: pawIcon,
		keywords: [ 'pet', 'details', 'profile', 'single' ],
		supports: { html: false, align: [ 'wide', 'full' ] },
		attributes: {
			layout: { type: 'string', default: 'sidebar' },
			showImage: { type: 'boolean', default: true },
			showStatus: { type: 'boolean', default: true },
			showAttributes: { type: 'boolean', default: true },
			showCompatibility: { type: 'boolean', default: true },
			showHealth: { type: 'boolean', default: true },
			showDescription: { type: 'boolean', default: true },
			showAdoptionInfo: { type: 'boolean', default: true },
			showActions: { type: 'boolean', default: true },
		},
		usesContext: [ 'postId', 'postType' ],
		edit( props ) {
			const { attributes, setAttributes } = props;
			const blockProps = useBlockProps();

			return el(
				'div',
				blockProps,
				el(
					InspectorControls,
					{},
					el(
						PanelBody,
						{ title: __( 'Layout', 'shelterkit-pets' ) },
						el( SelectControl, {
							label: __( 'Layout', 'shelterkit-pets' ),
							value: attributes.layout,
							options: [
								{
									label: __( 'Sidebar', 'shelterkit-pets' ),
									value: 'sidebar',
								},
								{
									label: __( 'Stacked', 'shelterkit-pets' ),
									value: 'stacked',
								},
							],
							onChange: ( val ) =>
								setAttributes( { layout: val } ),
						} )
					),
					el(
						PanelBody,
						{
							title: __( 'Sections', 'shelterkit-pets' ),
							initialOpen: false,
						},
						el( ToggleControl, {
							label: __( 'Show Image', 'shelterkit-pets' ),
							checked: attributes.showImage,
							onChange: ( val ) =>
								setAttributes( { showImage: val } ),
						} ),
						el( ToggleControl, {
							label: __( 'Show Status', 'shelterkit-pets' ),
							checked: attributes.showStatus,
							onChange: ( val ) =>
								setAttributes( { showStatus: val } ),
						} ),
						el( ToggleControl, {
							label: __( 'Show Attributes', 'shelterkit-pets' ),
							checked: attributes.showAttributes,
							onChange: ( val ) =>
								setAttributes( { showAttributes: val } ),
						} ),
						el( ToggleControl, {
							label: __(
								'Show Compatibility',
								'shelterkit-pets'
							),
							checked: attributes.showCompatibility,
							onChange: ( val ) =>
								setAttributes( { showCompatibility: val } ),
						} ),
						el( ToggleControl, {
							label: __( 'Show Health Info', 'shelterkit-pets' ),
							checked: attributes.showHealth,
							onChange: ( val ) =>
								setAttributes( { showHealth: val } ),
						} ),
						el( ToggleControl, {
							label: __( 'Show Description', 'shelterkit-pets' ),
							checked: attributes.showDescription,
							onChange: ( val ) =>
								setAttributes( { showDescription: val } ),
						} ),
						el( ToggleControl, {
							label: __(
								'Show Adoption Info',
								'shelterkit-pets'
							),
							checked: attributes.showAdoptionInfo,
							onChange: ( val ) =>
								setAttributes( { showAdoptionInfo: val } ),
						} ),
						el( ToggleControl, {
							label: __( 'Show Actions', 'shelterkit-pets' ),
							checked: attributes.showActions,
							onChange: ( val ) =>
								setAttributes( { showActions: val } ),
						} )
					)
				),
				el( ServerSideRender, {
					block: 'petsync/pet-details',
					attributes,
				} )
			);
		},
		save: () => null,
	} );

	// === Pet Compare Bar ===
	registerBlockType( 'petsync/pet-compare-bar', {
		title: __( 'Pet Compare Bar', 'shelterkit-pets' ),
		description: __(
			'Sticky bar showing pets selected for comparison.',
			'shelterkit-pets'
		),
		category: 'widgets',
		icon: 'columns',
		keywords: [ 'pet', 'compare', 'comparison' ],
		supports: { html: false, multiple: false },
		attributes: {
			position: { type: 'string', default: 'bottom' },
		},
		edit( props ) {
			const { attributes, setAttributes } = props;
			const blockProps = useBlockProps();

			return el(
				'div',
				blockProps,
				el(
					InspectorControls,
					{},
					el(
						PanelBody,
						{ title: __( 'Settings', 'shelterkit-pets' ) },
						el( SelectControl, {
							label: __( 'Position', 'shelterkit-pets' ),
							value: attributes.position,
							options: [
								{
									label: __( 'Bottom', 'shelterkit-pets' ),
									value: 'bottom',
								},
								{
									label: __( 'Top', 'shelterkit-pets' ),
									value: 'top',
								},
							],
							onChange: ( val ) =>
								setAttributes( { position: val } ),
						} )
					)
				),
				el(
					'div',
					{ className: 'petstablished-editor-placeholder' },
					el( 'p', {}, __( 'Pet Compare Bar', 'shelterkit-pets' ) ),
					el(
						'small',
						{},
						__(
							'This will appear as a sticky bar when pets are added to comparison.',
							'shelterkit-pets'
						)
					)
				)
			);
		},
		save: () => null,
	} );

	// === Pet Filters (deprecated — use pet-listing-grid's built-in toolbar) ===
	registerBlockType( 'petsync/pet-filters', {
		title: __( 'Pet Filters (deprecated)', 'shelterkit-pets' ),
		description: __(
			"Deprecated — use the pet listing grid's built-in filter toolbar instead.",
			'shelterkit-pets'
		),
		category: 'widgets',
		icon: 'filter',
		keywords: [],
		supports: { html: false, inserter: false, align: [ 'wide', 'full' ] },
		attributes: {
			showAnimal: { type: 'boolean', default: true },
			showBreed: { type: 'boolean', default: true },
			showAge: { type: 'boolean', default: true },
			showSex: { type: 'boolean', default: true },
			showSize: { type: 'boolean', default: true },
			layout: { type: 'string', default: 'horizontal' },
		},
		edit( props ) {
			const { attributes, setAttributes } = props;
			const blockProps = useBlockProps();

			return el(
				'div',
				blockProps,
				el(
					InspectorControls,
					{},
					el(
						PanelBody,
						{ title: __( 'Layout', 'shelterkit-pets' ) },
						el( SelectControl, {
							label: __( 'Layout', 'shelterkit-pets' ),
							value: attributes.layout,
							options: [
								{
									label: __(
										'Horizontal',
										'shelterkit-pets'
									),
									value: 'horizontal',
								},
								{
									label: __( 'Vertical', 'shelterkit-pets' ),
									value: 'vertical',
								},
							],
							onChange: ( val ) =>
								setAttributes( { layout: val } ),
						} )
					),
					el(
						PanelBody,
						{
							title: __( 'Filters', 'shelterkit-pets' ),
							initialOpen: false,
						},
						el( ToggleControl, {
							label: __( 'Animal Type', 'shelterkit-pets' ),
							checked: attributes.showAnimal,
							onChange: ( val ) =>
								setAttributes( { showAnimal: val } ),
						} ),
						el( ToggleControl, {
							label: __( 'Breed', 'shelterkit-pets' ),
							checked: attributes.showBreed,
							onChange: ( val ) =>
								setAttributes( { showBreed: val } ),
						} ),
						el( ToggleControl, {
							label: __( 'Age', 'shelterkit-pets' ),
							checked: attributes.showAge,
							onChange: ( val ) =>
								setAttributes( { showAge: val } ),
						} ),
						el( ToggleControl, {
							label: __( 'Sex', 'shelterkit-pets' ),
							checked: attributes.showSex,
							onChange: ( val ) =>
								setAttributes( { showSex: val } ),
						} ),
						el( ToggleControl, {
							label: __( 'Size', 'shelterkit-pets' ),
							checked: attributes.showSize,
							onChange: ( val ) =>
								setAttributes( { showSize: val } ),
						} )
					)
				),
				el( ServerSideRender, {
					block: 'petsync/pet-filters',
					attributes,
				} )
			);
		},
		save: () => null,
	} );

	// === Pet Favorites Toggle (deprecated — use pet-favorites-modal) ===
	registerBlockType( 'petsync/pet-favorites-toggle', {
		title: __( 'Favorites Toggle (deprecated)', 'shelterkit-pets' ),
		description: __(
			'Deprecated — use Pet Favorites Modal instead.',
			'shelterkit-pets'
		),
		category: 'widgets',
		icon: 'heart',
		keywords: [],
		supports: { html: false, inserter: false },
		attributes: {
			showCount: { type: 'boolean', default: true },
		},
		edit( props ) {
			const { attributes, setAttributes } = props;
			const blockProps = useBlockProps();

			return el(
				'div',
				blockProps,
				el(
					InspectorControls,
					{},
					el(
						PanelBody,
						{ title: __( 'Settings', 'shelterkit-pets' ) },
						el( ToggleControl, {
							label: __( 'Show Count', 'shelterkit-pets' ),
							checked: attributes.showCount,
							onChange: ( val ) =>
								setAttributes( { showCount: val } ),
						} )
					)
				),
				el( ServerSideRender, {
					block: 'petsync/pet-favorites-toggle',
					attributes,
				} )
			);
		},
		save: () => null,
	} );

	// === Pet Slider ===
	registerBlockType( 'petsync/pet-slider', {
		title: __( 'Pet Slider', 'shelterkit-pets' ),
		description: __(
			'A carousel slider showcasing available pets. Perfect for home pages, 404 pages, or anywhere you want to highlight adoptable pets.',
			'shelterkit-pets'
		),
		category: 'widgets',
		icon: pawIcon,
		keywords: [ 'pet', 'slider', 'carousel', 'featured', 'hero', '404' ],
		supports: { html: false, align: [ 'wide', 'full' ] },
		attributes: {
			title: { type: 'string', default: 'Meet Our Pets' },
			showTitle: { type: 'boolean', default: true },
			count: { type: 'number', default: 8 },
			orderBy: { type: 'string', default: 'random' },
			autoplay: { type: 'boolean', default: false },
			autoplaySpeed: { type: 'number', default: 5000 },
			showNavigation: { type: 'boolean', default: true },
			showDots: { type: 'boolean', default: true },
			cardStyle: { type: 'string', default: 'default' },
			displayMode: { type: 'string', default: 'carousel' },
			showQuickActions: { type: 'boolean', default: true },
			showBadges: { type: 'boolean', default: true },
			badgePosition: { type: 'string', default: 'image-top' },
			ctaText: { type: 'string', default: 'Find Your New Best Friend' },
			showCta: { type: 'boolean', default: false },
			linkToArchive: { type: 'boolean', default: true },
			archiveLinkText: { type: 'string', default: 'View All Pets' },
			// Card styling (managed by editor.js inspector controls).
			cardBorderRadius: { type: 'number', default: 12 },
			cardGap: { type: 'number', default: 16 },
			// Typography (managed by editor.js inspector controls).
			nameFontSize: { type: 'string', default: '' },
			nameFontFamily: { type: 'string', default: '' },
			metaFontSize: { type: 'string', default: '' },
			metaFontFamily: { type: 'string', default: '' },
			// Similar pets mode (managed by editor.js inspector controls).
			similarPetsMode: { type: 'boolean', default: false },
			filterAnimal: { type: 'string', default: '' },
			filterAge: { type: 'string', default: '' },
			excludePostId: { type: 'number', default: 0 },
		},
		edit( props ) {
			const { attributes, setAttributes } = props;
			const blockProps = useBlockProps();

			return el(
				'div',
				blockProps,
				el(
					InspectorControls,
					{},
					el(
						PanelBody,
						{ title: __( 'Display Mode', 'shelterkit-pets' ) },
						el( SelectControl, {
							label: __( 'Mode', 'shelterkit-pets' ),
							value: attributes.displayMode,
							options: [
								{
									label: __( 'Carousel', 'shelterkit-pets' ),
									value: 'carousel',
								},
								{
									label: __(
										'Hero (Featured)',
										'shelterkit-pets'
									),
									value: 'hero',
								},
							],
							onChange: ( val ) =>
								setAttributes( { displayMode: val } ),
							help:
								attributes.displayMode === 'hero'
									? __(
											'Large featured pet with thumbnail strip. Great for home and 404 pages.',
											'shelterkit-pets'
									  )
									: __(
											'Traditional sliding carousel of pet cards.',
											'shelterkit-pets'
									  ),
						} ),
						el( SelectControl, {
							label: __( 'Card Style', 'shelterkit-pets' ),
							value: attributes.cardStyle,
							options: [
								{
									label: __( 'Default', 'shelterkit-pets' ),
									value: 'default',
								},
								{
									label: __( 'Minimal', 'shelterkit-pets' ),
									value: 'minimal',
								},
								{
									label: __( 'Overlay', 'shelterkit-pets' ),
									value: 'overlay',
								},
							],
							onChange: ( val ) =>
								setAttributes( { cardStyle: val } ),
						} )
					),
					el(
						PanelBody,
						{
							title: __( 'Content', 'shelterkit-pets' ),
							initialOpen: false,
						},
						el( ToggleControl, {
							label: __( 'Show Title', 'shelterkit-pets' ),
							checked: attributes.showTitle,
							onChange: ( val ) =>
								setAttributes( { showTitle: val } ),
						} ),
						attributes.showTitle &&
							el(
								'div',
								{ style: { marginBottom: '16px' } },
								el(
									'label',
									{
										style: {
											display: 'block',
											marginBottom: '8px',
										},
									},
									__( 'Title', 'shelterkit-pets' )
								),
								el( 'input', {
									type: 'text',
									value: attributes.title,
									onChange: ( e ) =>
										setAttributes( {
											title: e.target.value,
										} ),
									style: { width: '100%' },
								} )
							),
						attributes.displayMode === 'hero' &&
							el( ToggleControl, {
								label: __(
									'Show CTA Subtitle',
									'shelterkit-pets'
								),
								checked: attributes.showCta,
								onChange: ( val ) =>
									setAttributes( { showCta: val } ),
							} ),
						attributes.displayMode === 'hero' &&
							attributes.showCta &&
							el(
								'div',
								{ style: { marginBottom: '16px' } },
								el(
									'label',
									{
										style: {
											display: 'block',
											marginBottom: '8px',
										},
									},
									__( 'CTA Text', 'shelterkit-pets' )
								),
								el( 'input', {
									type: 'text',
									value: attributes.ctaText,
									onChange: ( e ) =>
										setAttributes( {
											ctaText: e.target.value,
										} ),
									style: { width: '100%' },
								} )
							),
						el( RangeControl, {
							label: __( 'Number of Pets', 'shelterkit-pets' ),
							value: attributes.count,
							onChange: ( val ) =>
								setAttributes( { count: val } ),
							min: 1,
							max: 20,
						} ),
						el( SelectControl, {
							label: __( 'Order By', 'shelterkit-pets' ),
							value: attributes.orderBy,
							options: [
								{
									label: __( 'Random', 'shelterkit-pets' ),
									value: 'random',
								},
								{
									label: __(
										'Newest First',
										'shelterkit-pets'
									),
									value: 'newest',
								},
								{
									label: __(
										'Name (A-Z)',
										'shelterkit-pets'
									),
									value: 'name',
								},
							],
							onChange: ( val ) =>
								setAttributes( { orderBy: val } ),
						} )
					),
					el(
						PanelBody,
						{
							title: __( 'Navigation', 'shelterkit-pets' ),
							initialOpen: false,
						},
						attributes.displayMode === 'carousel' &&
							el( ToggleControl, {
								label: __(
									'Show Navigation Arrows',
									'shelterkit-pets'
								),
								checked: attributes.showNavigation,
								onChange: ( val ) =>
									setAttributes( { showNavigation: val } ),
							} ),
						attributes.displayMode === 'carousel' &&
							el( ToggleControl, {
								label: __( 'Show Dots', 'shelterkit-pets' ),
								checked: attributes.showDots,
								onChange: ( val ) =>
									setAttributes( { showDots: val } ),
							} ),
						el( ToggleControl, {
							label: __( 'Autoplay', 'shelterkit-pets' ),
							checked: attributes.autoplay,
							onChange: ( val ) =>
								setAttributes( { autoplay: val } ),
						} ),
						attributes.autoplay &&
							el( RangeControl, {
								label: __(
									'Autoplay Speed (ms)',
									'shelterkit-pets'
								),
								value: attributes.autoplaySpeed,
								onChange: ( val ) =>
									setAttributes( { autoplaySpeed: val } ),
								min: 2000,
								max: 10000,
								step: 500,
							} )
					),
					el(
						PanelBody,
						{
							title: __( 'Features', 'shelterkit-pets' ),
							initialOpen: false,
						},
						el( ToggleControl, {
							label: __(
								'Show Quick Actions (Favorite/Compare)',
								'shelterkit-pets'
							),
							checked: attributes.showQuickActions,
							onChange: ( val ) =>
								setAttributes( { showQuickActions: val } ),
						} ),
						el( ToggleControl, {
							label: __( 'Link to Archive', 'shelterkit-pets' ),
							checked: attributes.linkToArchive,
							onChange: ( val ) =>
								setAttributes( { linkToArchive: val } ),
						} ),
						attributes.linkToArchive &&
							el(
								'div',
								{ style: { marginTop: '8px' } },
								el(
									'label',
									{
										style: {
											display: 'block',
											marginBottom: '8px',
										},
									},
									__( 'Archive Link Text', 'shelterkit-pets' )
								),
								el( 'input', {
									type: 'text',
									value: attributes.archiveLinkText,
									onChange: ( e ) =>
										setAttributes( {
											archiveLinkText: e.target.value,
										} ),
									style: { width: '100%' },
								} )
							),
						el( ToggleControl, {
							label: __(
								'Show Badges (Bonded Pair, Special Needs)',
								'shelterkit-pets'
							),
							checked: attributes.showBadges,
							onChange: ( val ) =>
								setAttributes( { showBadges: val } ),
						} ),
						attributes.showBadges &&
							el( SelectControl, {
								label: __(
									'Badge Position',
									'shelterkit-pets'
								),
								value: attributes.badgePosition,
								options: [
									{
										label: __(
											'Top of image',
											'shelterkit-pets'
										),
										value: 'image-top',
									},
									{
										label: __(
											'Bottom overlay',
											'shelterkit-pets'
										),
										value: 'overlay-bottom',
									},
									{
										label: __(
											'Above pet name',
											'shelterkit-pets'
										),
										value: 'above-name',
									},
								],
								onChange: ( val ) =>
									setAttributes( { badgePosition: val } ),
							} )
					)
				),
				el( ServerSideRender, {
					block: 'petsync/pet-slider',
					attributes,
				} )
			);
		},
		save: () => null,
	} );

	// === Pet Comparison ===
	registerBlockType( 'petsync/pet-comparison', {
		title: __( 'Pet Comparison', 'shelterkit-pets' ),
		description: __(
			'Display side-by-side comparison of selected pets.',
			'shelterkit-pets'
		),
		category: 'widgets',
		icon: 'columns',
		keywords: [ 'pet', 'comparison', 'compare' ],
		supports: { html: false, align: [ 'wide', 'full' ] },
		attributes: {},
		edit( props ) {
			const blockProps = useBlockProps();

			return el(
				'div',
				blockProps,
				el( ServerSideRender, {
					block: 'petsync/pet-comparison',
					attributes: props.attributes,
				} )
			);
		},
		save: () => null,
	} );

	// === Child Blocks for Pet Details (Server-Side Rendered) ===

	// Pet Gallery
	registerBlockType( 'petsync/pet-gallery', {
		title: __( 'Pet Gallery', 'shelterkit-pets' ),
		description: __(
			'Display pet photo gallery with lightbox.',
			'shelterkit-pets'
		),
		category: 'petsync',
		icon: 'format-gallery',
		keywords: [ 'pet', 'gallery', 'photos', 'images' ],
		parent: [ 'petsync/pet-details' ],
		usesContext: [ 'postId', 'postType' ],
		supports: { html: false, reusable: false },
		attributes: {
			showThumbnails: { type: 'boolean', default: true },
			showLightbox: { type: 'boolean', default: true },
		},
		edit( props ) {
			const { attributes, setAttributes, context } = props;
			const blockProps = useBlockProps( {
				className: 'pet-gallery-editor',
			} );

			// Check if we have a pet context.
			const postId = context?.postId;
			const postType = context?.postType;
			const hasPetContext = postId && postType === 'pet';

			if ( ! hasPetContext ) {
				return el(
					'div',
					blockProps,
					el(
						'div',
						{ className: 'components-placeholder' },
						el(
							'div',
							{ className: 'components-placeholder__label' },
							el( 'span', {
								className: 'dashicons dashicons-format-gallery',
								style: { marginRight: '8px' },
							} ),
							__( 'Pet Gallery', 'shelterkit-pets' )
						),
						el(
							'div',
							{
								className:
									'components-placeholder__instructions',
							},
							__(
								"Displays the pet's photo gallery. This block requires a pet context to display content.",
								'shelterkit-pets'
							)
						)
					)
				);
			}

			return el(
				'div',
				blockProps,
				el(
					InspectorControls,
					{},
					el(
						PanelBody,
						{
							title: __( 'Gallery Settings', 'shelterkit-pets' ),
						},
						el( ToggleControl, {
							label: __( 'Show Thumbnails', 'shelterkit-pets' ),
							checked: attributes.showThumbnails,
							onChange: ( val ) =>
								setAttributes( { showThumbnails: val } ),
						} ),
						el( ToggleControl, {
							label: __( 'Enable Lightbox', 'shelterkit-pets' ),
							checked: attributes.showLightbox,
							onChange: ( val ) =>
								setAttributes( { showLightbox: val } ),
						} )
					)
				),
				el( ServerSideRender, {
					block: 'petsync/pet-gallery',
					attributes,
					urlQueryArgs: { post_id: postId },
				} )
			);
		},
		save: () => null,
	} );

	// Pet Actions (Favorite, Compare, Share buttons)
	registerBlockType( 'petsync/pet-actions', {
		title: __( 'Pet Actions', 'shelterkit-pets' ),
		description: __(
			'Display favorite, compare, and share action buttons.',
			'shelterkit-pets'
		),
		category: 'petsync',
		icon: 'heart',
		keywords: [ 'pet', 'actions', 'favorite', 'compare', 'share' ],
		parent: [ 'petsync/pet-details' ],
		usesContext: [ 'postId', 'postType' ],
		supports: { html: false, reusable: false },
		attributes: {
			showFavorite: { type: 'boolean', default: true },
			showCompare: { type: 'boolean', default: true },
			showShare: { type: 'boolean', default: true },
			labelDisplay: {
				type: 'string',
				default: 'icon-and-text',
				enum: [ 'icon-and-text', 'icon-only', 'text-only' ],
			},
			displayMode: {
				type: 'string',
				default: 'inline',
				enum: [ 'inline', 'overlay' ],
			},
		},
		edit( props ) {
			const { attributes, setAttributes } = props;
			const isOverlay = attributes.displayMode === 'overlay';
			const blockProps = useBlockProps( {
				className:
					'pet-actions-editor' +
					( isOverlay ? ' pet-actions-editor--overlay' : '' ),
			} );

			return el(
				'div',
				blockProps,
				el(
					InspectorControls,
					{},
					el(
						PanelBody,
						{
							title: __( 'Action Buttons', 'shelterkit-pets' ),
						},
						el( ToggleControl, {
							label: __(
								'Show Favorite Button',
								'shelterkit-pets'
							),
							checked: attributes.showFavorite,
							onChange: ( val ) =>
								setAttributes( { showFavorite: val } ),
						} ),
						el( ToggleControl, {
							label: __(
								'Show Compare Button',
								'shelterkit-pets'
							),
							checked: attributes.showCompare,
							onChange: ( val ) =>
								setAttributes( { showCompare: val } ),
						} ),
						el( ToggleControl, {
							label: __( 'Show Share Button', 'shelterkit-pets' ),
							checked: attributes.showShare,
							onChange: ( val ) =>
								setAttributes( { showShare: val } ),
						} )
					),
					el(
						PanelBody,
						{
							title: __( 'Display', 'shelterkit-pets' ),
							initialOpen: false,
						},
						el( SelectControl, {
							label: __( 'Display Mode', 'shelterkit-pets' ),
							value: attributes.displayMode,
							options: [
								{
									label: __(
										'Inline (segmented bar)',
										'shelterkit-pets'
									),
									value: 'inline',
								},
								{
									label: __(
										'Overlay (floating on gallery)',
										'shelterkit-pets'
									),
									value: 'overlay',
								},
							],
							onChange: ( val ) =>
								setAttributes( { displayMode: val } ),
							help: isOverlay
								? __(
										'Circular icon buttons positioned over the gallery image.',
										'shelterkit-pets'
								  )
								: __(
										'Segmented button bar below the gallery.',
										'shelterkit-pets'
								  ),
						} ),
						! isOverlay &&
							el( SelectControl, {
								label: __(
									'Desktop Label Style',
									'shelterkit-pets'
								),
								value: attributes.labelDisplay,
								options: [
									{
										label: __(
											'Icon & Text',
											'shelterkit-pets'
										),
										value: 'icon-and-text',
									},
									{
										label: __(
											'Icon Only',
											'shelterkit-pets'
										),
										value: 'icon-only',
									},
									{
										label: __(
											'Text Only',
											'shelterkit-pets'
										),
										value: 'text-only',
									},
								],
								onChange: ( val ) =>
									setAttributes( { labelDisplay: val } ),
								help: __(
									'Mobile always shows icons only.',
									'shelterkit-pets'
								),
							} )
					)
				),
				el(
					'div',
					{ className: 'pet-actions-preview' },
					el(
						'div',
						{ className: 'components-placeholder is-small' },
						el(
							'div',
							{
								className: 'components-placeholder__label',
								style: {
									display: 'flex',
									gap: '8px',
									alignItems: 'center',
								},
							},
							icons.heart,
							icons.columns,
							icons.share
						),
						el(
							'div',
							{
								className:
									'components-placeholder__instructions',
								style: { fontSize: '12px' },
							},
							__(
								'Pet Actions: Favorite, Compare, Share',
								'shelterkit-pets'
							)
						)
					)
				)
			);
		},
		save: () => null,
	} );

	// Pet Attributes (breed, age, sex, size, etc.)
	registerBlockType( 'petsync/pet-attributes', {
		title: __( 'Pet Attributes', 'shelterkit-pets' ),
		description: __(
			'Display pet attributes like breed, age, sex, size in a definition list.',
			'shelterkit-pets'
		),
		category: 'petsync',
		icon: 'list-view',
		keywords: [ 'pet', 'attributes', 'breed', 'age', 'sex', 'size' ],
		parent: [ 'petsync/pet-details' ],
		usesContext: [ 'postId', 'postType' ],
		supports: { html: false, reusable: false },
		attributes: {
			showBreed: { type: 'boolean', default: true },
			showAge: { type: 'boolean', default: true },
			showSex: { type: 'boolean', default: true },
			showSize: { type: 'boolean', default: true },
			showColor: { type: 'boolean', default: true },
			showCoat: { type: 'boolean', default: true },
			showCoatPattern: { type: 'boolean', default: true },
			showWeight: { type: 'boolean', default: true },
		},
		edit( props ) {
			const { attributes, setAttributes } = props;
			const blockProps = useBlockProps( {
				className: 'pet-attributes-editor',
			} );

			return el(
				'div',
				blockProps,
				el(
					InspectorControls,
					{},
					el(
						PanelBody,
						{
							title: __(
								'Visible Attributes',
								'shelterkit-pets'
							),
						},
						el( ToggleControl, {
							label: __( 'Breed', 'shelterkit-pets' ),
							checked: attributes.showBreed,
							onChange: ( val ) =>
								setAttributes( { showBreed: val } ),
						} ),
						el( ToggleControl, {
							label: __( 'Age', 'shelterkit-pets' ),
							checked: attributes.showAge,
							onChange: ( val ) =>
								setAttributes( { showAge: val } ),
						} ),
						el( ToggleControl, {
							label: __( 'Sex', 'shelterkit-pets' ),
							checked: attributes.showSex,
							onChange: ( val ) =>
								setAttributes( { showSex: val } ),
						} ),
						el( ToggleControl, {
							label: __( 'Size', 'shelterkit-pets' ),
							checked: attributes.showSize,
							onChange: ( val ) =>
								setAttributes( { showSize: val } ),
						} ),
						el( ToggleControl, {
							label: __( 'Color', 'shelterkit-pets' ),
							checked: attributes.showColor,
							onChange: ( val ) =>
								setAttributes( { showColor: val } ),
						} ),
						el( ToggleControl, {
							label: __( 'Coat', 'shelterkit-pets' ),
							checked: attributes.showCoat,
							onChange: ( val ) =>
								setAttributes( { showCoat: val } ),
						} ),
						el( ToggleControl, {
							label: __( 'Coat Pattern', 'shelterkit-pets' ),
							checked: attributes.showCoatPattern,
							onChange: ( val ) =>
								setAttributes( { showCoatPattern: val } ),
						} ),
						el( ToggleControl, {
							label: __( 'Weight', 'shelterkit-pets' ),
							checked: attributes.showWeight,
							onChange: ( val ) =>
								setAttributes( { showWeight: val } ),
						} )
					)
				),
				el(
					'div',
					{ className: 'pet-attributes-preview' },
					el(
						'dl',
						{
							className: 'pet-attributes-list',
							style: {
								margin: 0,
								padding: '12px',
								background: '#f0f0f0',
								borderRadius: '4px',
							},
						},
						el(
							'div',
							{
								style: {
									display: 'flex',
									gap: '8px',
									marginBottom: '4px',
								},
							},
							el(
								'dt',
								{
									style: {
										fontWeight: 'bold',
										minWidth: '60px',
									},
								},
								__( 'Breed', 'shelterkit-pets' )
							),
							el(
								'dd',
								{ style: { margin: 0, color: '#666' } },
								'[Breed]'
							)
						),
						el(
							'div',
							{
								style: {
									display: 'flex',
									gap: '8px',
									marginBottom: '4px',
								},
							},
							el(
								'dt',
								{
									style: {
										fontWeight: 'bold',
										minWidth: '60px',
									},
								},
								__( 'Age', 'shelterkit-pets' )
							),
							el(
								'dd',
								{ style: { margin: 0, color: '#666' } },
								'[Age]'
							)
						),
						el(
							'div',
							{ style: { display: 'flex', gap: '8px' } },
							el(
								'dt',
								{
									style: {
										fontWeight: 'bold',
										minWidth: '60px',
									},
								},
								__( 'Sex', 'shelterkit-pets' )
							),
							el(
								'dd',
								{ style: { margin: 0, color: '#666' } },
								'[Sex]'
							)
						)
					)
				)
			);
		},
		save: () => null,
	} );

	// Pet Compatibility (good with dogs, cats, kids)
	registerBlockType( 'petsync/pet-compatibility', {
		title: __( 'Pet Compatibility', 'shelterkit-pets' ),
		description: __(
			'Display pet compatibility with dogs, cats, and children.',
			'shelterkit-pets'
		),
		category: 'petsync',
		icon: 'groups',
		keywords: [
			'pet',
			'compatibility',
			'good with',
			'dogs',
			'cats',
			'kids',
		],
		parent: [ 'petsync/pet-details' ],
		usesContext: [ 'postId', 'postType' ],
		supports: { html: false, reusable: false },
		attributes: {
			showDogs: { type: 'boolean', default: true },
			showCats: { type: 'boolean', default: true },
			showKids: { type: 'boolean', default: true },
			displayStyle: {
				type: 'string',
				default: 'cards',
				enum: [ 'cards', 'stacked', 'pills' ],
			},
			headingText: { type: 'string', default: 'Good with' },
			positiveHeadingText: {
				type: 'string',
				default: 'Plays nicely with',
			},
		},
		edit( props ) {
			const { attributes, setAttributes } = props;
			const blockProps = useBlockProps( {
				className: 'pet-compatibility-editor',
			} );

			return el(
				'div',
				blockProps,
				el(
					InspectorControls,
					{},
					el(
						PanelBody,
						{
							title: __(
								'Compatibility Options',
								'shelterkit-pets'
							),
						},
						el( SelectControl, {
							label: __( 'Display Style', 'shelterkit-pets' ),
							value: attributes.displayStyle,
							options: [
								{
									label: __(
										'Cards (grid)',
										'shelterkit-pets'
									),
									value: 'cards',
								},
								{
									label: __(
										'Stacked (vertical cards)',
										'shelterkit-pets'
									),
									value: 'stacked',
								},
								{
									label: __(
										'Pills (compact strip)',
										'shelterkit-pets'
									),
									value: 'pills',
								},
							],
							onChange: ( val ) =>
								setAttributes( { displayStyle: val } ),
						} ),
						el( ToggleControl, {
							label: __( 'Show Dogs', 'shelterkit-pets' ),
							checked: attributes.showDogs,
							onChange: ( val ) =>
								setAttributes( { showDogs: val } ),
						} ),
						el( ToggleControl, {
							label: __( 'Show Cats', 'shelterkit-pets' ),
							checked: attributes.showCats,
							onChange: ( val ) =>
								setAttributes( { showCats: val } ),
						} ),
						el( ToggleControl, {
							label: __( 'Show Kids', 'shelterkit-pets' ),
							checked: attributes.showKids,
							onChange: ( val ) =>
								setAttributes( { showKids: val } ),
						} )
					),
					el(
						PanelBody,
						{
							title: __( 'Heading', 'shelterkit-pets' ),
							initialOpen: false,
						},
						el( TextControl, {
							label: __(
								'All-Positive Heading',
								'shelterkit-pets'
							),
							value: attributes.positiveHeadingText,
							onChange: ( val ) =>
								setAttributes( { positiveHeadingText: val } ),
							help: __(
								'Shown when all visible items are "yes".',
								'shelterkit-pets'
							),
						} ),
						el( TextControl, {
							label: __(
								'Mixed/General Heading',
								'shelterkit-pets'
							),
							value: attributes.headingText,
							onChange: ( val ) =>
								setAttributes( { headingText: val } ),
							help: __(
								'Shown when any item is "no" or "unknown".',
								'shelterkit-pets'
							),
						} )
					)
				),
				el(
					'div',
					{ className: 'pet-compatibility-preview' },
					el(
						'ul',
						{
							style: {
								display: 'flex',
								gap: '12px',
								listStyle: 'none',
								margin: 0,
								padding: '12px',
								background: '#f0f0f0',
								borderRadius: '4px',
								flexWrap: 'wrap',
							},
						},
						el(
							'li',
							{
								style: {
									display: 'flex',
									alignItems: 'center',
									gap: '6px',
									padding: '6px 10px',
									background: '#ecfdf5',
									borderRadius: '6px',
									border: '1px solid #a7f3d0',
								},
							},
							el(
								'span',
								{
									style: {
										display: 'flex',
										color: '#065f46',
									},
								},
								icons.dog
							),
							el(
								'span',
								{
									style: {
										fontSize: '13px',
										fontWeight: 500,
										color: '#065f46',
									},
								},
								__( 'Dogs', 'shelterkit-pets' )
							),
							el(
								'span',
								{
									style: {
										display: 'flex',
										color: '#10b981',
									},
								},
								icons.check
							)
						),
						el(
							'li',
							{
								style: {
									display: 'flex',
									alignItems: 'center',
									gap: '6px',
									padding: '6px 10px',
									background: '#f5f5f5',
									borderRadius: '6px',
									border: '1px solid #e0e0e0',
								},
							},
							el(
								'span',
								{ style: { display: 'flex', color: '#666' } },
								icons.cat
							),
							el(
								'span',
								{
									style: {
										fontSize: '13px',
										fontWeight: 500,
										color: '#666',
									},
								},
								__( 'Cats', 'shelterkit-pets' )
							),
							el(
								'span',
								{ style: { display: 'flex', color: '#999' } },
								icons.question
							)
						),
						el(
							'li',
							{
								style: {
									display: 'flex',
									alignItems: 'center',
									gap: '6px',
									padding: '6px 10px',
									background: '#ecfdf5',
									borderRadius: '6px',
									border: '1px solid #a7f3d0',
								},
							},
							el(
								'span',
								{
									style: {
										display: 'flex',
										color: '#065f46',
									},
								},
								icons.child
							),
							el(
								'span',
								{
									style: {
										fontSize: '13px',
										fontWeight: 500,
										color: '#065f46',
									},
								},
								__( 'Kids', 'shelterkit-pets' )
							),
							el(
								'span',
								{
									style: {
										display: 'flex',
										color: '#10b981',
									},
								},
								icons.check
							)
						)
					)
				)
			);
		},
		save: () => null,
	} );

	// Pet Health (vaccinations, spayed/neutered, etc.)
	registerBlockType( 'petsync/pet-health', {
		title: __( 'Pet Health', 'shelterkit-pets' ),
		description: __(
			'Display pet health information like vaccinations and spay/neuter status.',
			'shelterkit-pets'
		),
		category: 'petsync',
		icon: 'plus-alt',
		keywords: [ 'pet', 'health', 'vaccinations', 'spayed', 'neutered' ],
		parent: [ 'petsync/pet-details' ],
		usesContext: [ 'postId', 'postType' ],
		supports: { html: false, reusable: false },
		/*
		 * These must match block.json. The server reads all of them in
		 * render.php, but only three were declared here — so the other five had
		 * no control, could not be set, and the fields they gate were on
		 * permanently. An attribute the editor does not declare is one the
		 * editor cannot serialise, so adding a toggle without the declaration
		 * would have looked like it worked and saved nothing.
		 */
		attributes: {
			displayMode: { type: 'string', default: 'known' },
			showVaccinations: { type: 'boolean', default: true },
			showSpayedNeutered: { type: 'boolean', default: true },
			showHousebroken: { type: 'boolean', default: true },
			showSpecialNeeds: { type: 'boolean', default: true },
			showHypoallergenic: { type: 'boolean', default: true },
			showDeclawed: { type: 'boolean', default: true },
			showStatusText: { type: 'boolean', default: true },
		},
		edit( props ) {
			const { attributes, setAttributes } = props;
			const blockProps = useBlockProps( {
				className: 'pet-health-editor',
			} );

			return el(
				'div',
				blockProps,
				el(
					InspectorControls,
					{},
					el(
						PanelBody,
						{
							title: __(
								'Health Information',
								'shelterkit-pets'
							),
						},
						el( ToggleControl, {
							label: __( 'Show Vaccinations', 'shelterkit-pets' ),
							checked: attributes.showVaccinations,
							onChange: ( val ) =>
								setAttributes( { showVaccinations: val } ),
						} ),
						el( ToggleControl, {
							label: __(
								'Show Spayed/Neutered',
								'shelterkit-pets'
							),
							checked: attributes.showSpayedNeutered,
							onChange: ( val ) =>
								setAttributes( { showSpayedNeutered: val } ),
						} ),
						el( ToggleControl, {
							label: __( 'Show Housebroken', 'shelterkit-pets' ),
							checked: attributes.showHousebroken,
							onChange: ( val ) =>
								setAttributes( { showHousebroken: val } ),
						} ),
						el( ToggleControl, {
							label: __(
								'Show Special Needs',
								'shelterkit-pets'
							),
							checked: attributes.showSpecialNeeds,
							onChange: ( val ) =>
								setAttributes( { showSpecialNeeds: val } ),
						} ),
						el( ToggleControl, {
							label: __(
								'Show Hypoallergenic',
								'shelterkit-pets'
							),
							checked: attributes.showHypoallergenic,
							onChange: ( val ) =>
								setAttributes( { showHypoallergenic: val } ),
						} ),
						el( ToggleControl, {
							label: __( 'Show Declawed', 'shelterkit-pets' ),
							help: __(
								'Cats only — hidden for other animals regardless.',
								'shelterkit-pets'
							),
							checked: attributes.showDeclawed,
							onChange: ( val ) =>
								setAttributes( { showDeclawed: val } ),
						} ),
						el( SelectControl, {
							label: __( 'Which to show', 'shelterkit-pets' ),
							value: attributes.displayMode,
							options: [
								{
									label: __(
										'Known answers only',
										'shelterkit-pets'
									),
									value: 'known',
								},
								{
									label: __(
										'Everything, including unknown',
										'shelterkit-pets'
									),
									value: 'all',
								},
								{
									label: __(
										'Only what is true',
										'shelterkit-pets'
									),
									value: 'positive',
								},
							],
							onChange: ( val ) =>
								setAttributes( { displayMode: val } ),
						} ),
						el( ToggleControl, {
							label: __(
								'Show Yes/No wording',
								'shelterkit-pets'
							),
							help: __(
								'Turn off to rely on the icons alone — useful on printed kennel cards. Screen readers are unaffected either way.',
								'shelterkit-pets'
							),
							checked: attributes.showStatusText,
							onChange: ( val ) =>
								setAttributes( { showStatusText: val } ),
						} )
					)
				),
				el(
					'div',
					{ className: 'pet-health-preview' },
					el(
						'ul',
						{
							style: {
								listStyle: 'none',
								margin: 0,
								padding: '12px',
								background: '#f0f0f0',
								borderRadius: '4px',
							},
						},
						el(
							'li',
							{ style: { marginBottom: '4px' } },
							'✅ ',
							__( 'Spayed/Neutered', 'shelterkit-pets' )
						),
						el(
							'li',
							{ style: { marginBottom: '4px' } },
							'✅ ',
							__( 'Vaccinations Current', 'shelterkit-pets' )
						),
						el(
							'li',
							null,
							'✅ ',
							__( 'House Trained', 'shelterkit-pets' )
						)
					)
				)
			);
		},
		save: () => null,
	} );

	// Pet Adoption CTA — card container with InnerBlocks.
	// Content (heading, fee row, note, action button) is composed from native
	// core blocks with Block Bindings + the petsync/adoption-action child block.
	registerBlockType( 'petsync/pet-adoption-cta', {
		title: __( 'Pet Adoption CTA', 'shelterkit-pets' ),
		description: __(
			'Display adoption fee and application link or downloadable PDF form.',
			'shelterkit-pets'
		),
		category: 'petsync',
		icon: 'heart',
		keywords: [ 'pet', 'adoption', 'cta', 'apply', 'fee', 'pdf' ],
		parent: [ 'petsync/pet-details' ],
		usesContext: [ 'postId', 'postType' ],
		supports: {
			html: false,
			reusable: false,
			spacing: { margin: true, padding: true },
		},
		attributes: {},
		edit() {
			const blockProps = useBlockProps( {
				className: 'pet-adoption-cta-editor',
			} );

			// Default InnerBlocks template:
			//   core/group (card — navy gradient, white text)
			//     core/group (content column — vertical flex)
			//       core/heading (bound to adoption_title)
			//       core/group (fee row — horizontal flex)
			//         core/paragraph (static label)
			//         core/paragraph (bound to adoption_fee_formatted)
			//       core/paragraph (note — freeform)
			//     petsync/adoption-action (button)
			const TEMPLATE = [
				[
					'core/group',
					{
						className: 'pet-adoption-cta__card',
						style: {
							color: {
								gradient:
									'linear-gradient(135deg, #1e3a5f 0%, #2d5a87 100%)',
								text: '#ffffff',
							},
						},
					},
					[
						[
							'core/group',
							{
								className: 'pet-adoption-cta__content',
								layout: {
									type: 'flex',
									orientation: 'vertical',
									flexWrap: 'nowrap',
								},
							},
							[
								[
									'core/heading',
									{
										level: 2,
										className: 'pet-adoption-cta__title',
										metadata: {
											bindings: {
												content: {
													source: 'petsync/pet-data',
													args: {
														key: 'adoption_title',
													},
												},
											},
										},
									},
								],
								[
									'core/group',
									{
										className: 'pet-adoption-cta__fee-row',
										layout: {
											type: 'flex',
											flexWrap: 'nowrap',
											verticalAlignment: 'center',
										},
									},
									[
										[
											'core/paragraph',
											{
												content: __(
													'Adoption Fee:',
													'shelterkit-pets'
												),
												className:
													'pet-adoption-cta__fee-label',
											},
										],
										[
											'core/paragraph',
											{
												className:
													'pet-adoption-cta__fee-amount',
												metadata: {
													bindings: {
														content: {
															source: 'petsync/pet-data',
															args: {
																key: 'adoption_fee_formatted',
															},
														},
													},
												},
											},
										],
									],
								],
								[
									'core/paragraph',
									{
										content: __(
											'The adoption fee helps cover vaccinations, spay/neuter surgery, microchip, and initial veterinary care.',
											'shelterkit-pets'
										),
										className: 'pet-adoption-cta__note',
									},
								],
							],
						],
						[ 'petsync/adoption-action', {} ],
					],
				],
			];

			return el(
				'div',
				blockProps,
				el( InnerBlocks, {
					template: TEMPLATE,
					templateLock: false,
					allowedBlocks: [
						'core/group',
						'core/heading',
						'core/paragraph',
						'core/spacer',
						'petsync/adoption-action',
					],
				} )
			);
		},
		// InnerBlocks content must round-trip through editor saves; a null
		// save would drop the card's inner blocks from saved templates.
		save: () => el( InnerBlocks.Content ),
	} );

	// Back to Top — floating scroll-to-top button.
	registerBlockType( 'petsync/back-to-top', {
		title: __( 'Back to Top', 'shelterkit-pets' ),
		description: __(
			'Floating button that scrolls back to the top of the page.',
			'shelterkit-pets'
		),
		category: 'petsync',
		icon: 'arrow-up-alt',
		keywords: [ 'back', 'top', 'scroll' ],
		supports: { html: false, multiple: false },
		attributes: {
			position: {
				type: 'string',
				default: 'bottom-left',
				enum: [ 'bottom-left', 'bottom-right' ],
			},
			threshold: { type: 'integer', default: 400 },
		},
		edit( props ) {
			const { attributes, setAttributes } = props;
			const blockProps = useBlockProps();
			return el(
				'div',
				blockProps,
				el(
					InspectorControls,
					{},
					el(
						PanelBody,
						{ title: __( 'Settings', 'shelterkit-pets' ) },
						el( SelectControl, {
							label: __( 'Position', 'shelterkit-pets' ),
							value: attributes.position,
							options: [
								{
									label: __(
										'Bottom Left',
										'shelterkit-pets'
									),
									value: 'bottom-left',
								},
								{
									label: __(
										'Bottom Right',
										'shelterkit-pets'
									),
									value: 'bottom-right',
								},
							],
							onChange: ( val ) =>
								setAttributes( { position: val } ),
						} ),
						el( RangeControl, {
							label: __(
								'Show after scrolling (px)',
								'shelterkit-pets'
							),
							value: attributes.threshold,
							onChange: ( val ) =>
								setAttributes( { threshold: val } ),
							min: 100,
							max: 1000,
							step: 50,
						} )
					)
				),
				el(
					'div',
					{
						style: {
							padding: '12px',
							background: '#f0f0f0',
							borderRadius: '4px',
							textAlign: 'center',
							fontSize: '13px',
							color: '#666',
						},
					},
					__(
						'↑ Back to Top button (visible after scrolling)',
						'shelterkit-pets'
					)
				)
			);
		},
		save: () => null,
	} );

	// Pet Notifications — standalone toast region driven by the global
	// petsync store's notification state. Invisible until a message fires.
	registerBlockType( 'petsync/pet-toast', {
		title: __( 'Pet Notifications', 'shelterkit-pets' ),
		description: __(
			'Floating toast that surfaces favorites, comparison, and sharing confirmations.',
			'shelterkit-pets'
		),
		category: 'petsync',
		icon: 'megaphone',
		keywords: [ 'toast', 'notification', 'feedback', 'status' ],
		supports: { html: false, multiple: false, reusable: false },
		attributes: {},
		edit() {
			const blockProps = useBlockProps();
			return el(
				'div',
				blockProps,
				el(
					'div',
					{ className: 'petstablished-editor-placeholder' },
					el( 'p', {}, __( 'Pet Notifications', 'shelterkit-pets' ) ),
					el(
						'small',
						{},
						__(
							'Toast messages (favorites, comparison, sharing) appear here on the front end. Not visible until a message fires.',
							'shelterkit-pets'
						)
					)
				)
			);
		},
		save: () => null,
	} );

	// Pet Breadcrumb — SSR breadcrumb trail: Home › Adoptable Pets › Pet Name.
	registerBlockType( 'petsync/pet-breadcrumb', {
		title: __( 'Pet Breadcrumb', 'shelterkit-pets' ),
		description: __(
			'Breadcrumb trail: Home › Adoptable Pets › Pet Name.',
			'shelterkit-pets'
		),
		category: 'petsync',
		icon: 'arrow-left-alt',
		keywords: [ 'pet', 'breadcrumb', 'navigation', 'back' ],
		ancestor: [ 'petsync/pet-details' ],
		usesContext: [ 'postId', 'postType' ],
		supports: { html: false, reusable: false },
		attributes: {
			homeLabel: { type: 'string', default: 'Home' },
			archiveLabel: { type: 'string', default: 'Adoptable Pets' },
			separator: { type: 'string', default: '›' },
		},
		edit() {
			const blockProps = useBlockProps( {
				className: 'pet-breadcrumb-editor',
			} );
			return el(
				'div',
				blockProps,
				el(
					'p',
					{
						style: {
							margin: 0,
							color: '#666',
							fontSize: '0.8125rem',
						},
					},
					__(
						'Home › Adoptable Pets › [Pet Name]',
						'shelterkit-pets'
					)
				)
			);
		},
		save: () => null,
	} );

	// Pet Tagline — quick-facts summary with taxonomy filter links.
	registerBlockType( 'petsync/pet-tagline', {
		title: __( 'Pet Tagline', 'shelterkit-pets' ),
		description: __(
			'Quick-facts tagline with taxonomy filter links.',
			'shelterkit-pets'
		),
		category: 'petsync',
		icon: 'tag',
		keywords: [ 'pet', 'tagline', 'quick', 'facts' ],
		ancestor: [ 'petsync/pet-details' ],
		usesContext: [ 'postId', 'postType' ],
		supports: { html: false, reusable: false },
		attributes: {
			separator: { type: 'string', default: ' · ' },
		},
		edit() {
			const blockProps = useBlockProps( {
				className: 'pet-tagline-editor',
			} );
			return el(
				'div',
				blockProps,
				el(
					'p',
					{ style: { margin: 0, color: '#666', fontSize: '1rem' } },
					__(
						'Dog · Labrador · Young · Male · Medium',
						'shelterkit-pets'
					)
				)
			);
		},
		save: () => null,
	} );

	// Pet Adoption Fee — SSR fee row, auto-hidden when no fee is set.
	registerBlockType( 'petsync/adoption-fee', {
		title: __( 'Pet Adoption Fee', 'shelterkit-pets' ),
		description: __(
			'Displays the adoption fee. Hidden automatically if no fee is set.',
			'shelterkit-pets'
		),
		category: 'petsync',
		icon: 'tag',
		keywords: [ 'pet', 'adoption', 'fee', 'price' ],
		ancestor: [ 'petsync/pet-adoption-cta' ],
		usesContext: [ 'postId', 'postType' ],
		supports: { html: false, reusable: false },
		attributes: {},
		edit() {
			const blockProps = useBlockProps( {
				className: 'pet-adoption-fee-editor',
			} );
			return el(
				'div',
				blockProps,
				el(
					'span',
					{ className: 'pet-adoption-cta__fee-label' },
					__( 'Adoption Fee:', 'shelterkit-pets' )
				),
				el(
					'span',
					{ className: 'pet-adoption-cta__fee-amount' },
					' $[Fee]'
				)
			);
		},
		save: () => null,
	} );

	// Pet Adoption Action — application button (Petstablished link, internal
	// page link, or PDF download). Lives inside petsync/pet-adoption-cta as a
	// child block.
	registerBlockType( 'petsync/adoption-action', {
		title: __( 'Pet Adoption Action', 'shelterkit-pets' ),
		description: __(
			'Adoption application button — links to Petstablished form, an internal page, or provides a PDF download.',
			'shelterkit-pets'
		),
		category: 'petsync',
		icon: 'download',
		keywords: [ 'pet', 'adoption', 'button', 'apply', 'pdf', 'page' ],
		ancestor: [ 'petsync/pet-adoption-cta' ],
		usesContext: [ 'postId', 'postType' ],
		supports: { html: false, reusable: false },
		attributes: {
			formMode: {
				type: 'string',
				default: 'petstablished',
				enum: [ 'petstablished', 'pdf', 'page' ],
			},
			pdfAttachmentId: { type: 'integer', default: 0 },
			pdfButtonText: {
				type: 'string',
				default: 'Download Adoption Application',
			},
			buttonText: {
				type: 'string',
				default: 'Start Adoption Application',
			},
			pageId: { type: 'integer', default: 0 },
			pageButtonText: {
				type: 'string',
				default: 'View Adoption Resources',
			},
		},
		edit( props ) {
			const { attributes, setAttributes } = props;
			const blockProps = useBlockProps( {
				className: 'pet-adoption-action-editor',
			} );
			const isPdf = attributes.formMode === 'pdf';
			const isPage = attributes.formMode === 'page';

			// Fetched unconditionally (rules of hooks); resolves lazily so
			// non-page modes don't pay for it until the panel needs it.
			const pages = wp.data.useSelect( function ( select ) {
				return select( 'core' ).getEntityRecords( 'postType', 'page', {
					per_page: -1,
					status: 'publish',
					orderby: 'title',
					order: 'asc',
					_fields: 'id,title',
				} );
			}, [] );

			const pageOptions = ( pages || [] ).map( function ( page ) {
				return {
					value: String( page.id ),
					label:
						page.title?.rendered ||
						__( '(no title)', 'shelterkit-pets' ),
				};
			} );

			return el(
				'div',
				blockProps,
				el(
					InspectorControls,
					{},
					el(
						PanelBody,
						{
							title: __( 'Application Mode', 'shelterkit-pets' ),
						},
						el( SelectControl, {
							label: __( 'Form Mode', 'shelterkit-pets' ),
							value: attributes.formMode,
							options: [
								{
									label: __(
										'Petstablished (link to adoption form)',
										'shelterkit-pets'
									),
									value: 'petstablished',
								},
								{
									label: __(
										'PDF Download',
										'shelterkit-pets'
									),
									value: 'pdf',
								},
								{
									label: __(
										'Internal Page (e.g. Adoption Resources)',
										'shelterkit-pets'
									),
									value: 'page',
								},
							],
							onChange: ( val ) =>
								setAttributes( { formMode: val } ),
						} ),
						! isPdf &&
							! isPage &&
							el( TextControl, {
								label: __( 'Button Text', 'shelterkit-pets' ),
								value: attributes.buttonText,
								onChange: ( val ) =>
									setAttributes( { buttonText: val } ),
							} ),
						isPage &&
							el(
								'div',
								{},
								el( ComboboxControl, {
									label: __( 'Page', 'shelterkit-pets' ),
									value: attributes.pageId
										? String( attributes.pageId )
										: '',
									options: pageOptions,
									onChange: ( val ) =>
										setAttributes( {
											pageId: parseInt( val, 10 ) || 0,
										} ),
								} ),
								el( TextControl, {
									label: __(
										'Button Text',
										'shelterkit-pets'
									),
									value: attributes.pageButtonText,
									onChange: ( val ) =>
										setAttributes( {
											pageButtonText: val,
										} ),
								} )
							),
						isPdf &&
							el(
								'div',
								{},
								el(
									MediaUploadCheck,
									{},
									el( MediaUpload, {
										onSelect: ( media ) =>
											setAttributes( {
												pdfAttachmentId: media.id,
											} ),
										allowedTypes: [ 'application/pdf' ],
										value: attributes.pdfAttachmentId,
										render: ( { open } ) =>
											el(
												Button,
												{
													onClick: open,
													variant: 'secondary',
													style: {
														marginBottom: '12px',
														width: '100%',
														justifyContent:
															'center',
													},
												},
												attributes.pdfAttachmentId
													? __(
															'Replace PDF',
															'shelterkit-pets'
													  )
													: __(
															'Select PDF from Media Library',
															'shelterkit-pets'
													  )
											),
									} )
								),
								el( TextControl, {
									label: __(
										'PDF Button Text',
										'shelterkit-pets'
									),
									value: attributes.pdfButtonText,
									onChange: ( val ) =>
										setAttributes( { pdfButtonText: val } ),
								} )
							)
					)
				),
				( isPdf && ! attributes.pdfAttachmentId ) ||
					( isPage && ! attributes.pageId )
					? el(
							'div',
							{
								className:
									'pet-adoption-cta-preview__empty-state',
							},
							el(
								'p',
								{},
								isPdf
									? __(
											'No PDF selected — choose a file in the Application Mode panel.',
											'shelterkit-pets'
									  )
									: __(
											'No page selected — choose one in the Application Mode panel.',
											'shelterkit-pets'
									  )
							)
					  )
					: el(
							'button',
							{
								className: 'pet-adoption-cta__action-btn',
								disabled: true,
							},
							( isPdf && attributes.pdfButtonText ) ||
								( isPage && attributes.pageButtonText ) ||
								attributes.buttonText
					  )
			);
		},
		save: () => null,
	} );

	// === Pet Favorites Modal ===
	registerBlockType( 'petsync/pet-favorites-modal', {
		title: __( 'Pet Favorites Modal', 'shelterkit-pets' ),
		description: __(
			'Floating heart button that opens a modal showing all favorited pets.',
			'shelterkit-pets'
		),
		category: 'petsync',
		icon: 'heart',
		keywords: [ 'pet', 'favorites', 'heart', 'modal' ],
		supports: { html: false, multiple: false },
		attributes: {
			position: {
				type: 'string',
				default: 'bottom-right',
				enum: [ 'bottom-right', 'bottom-left' ],
			},
			showCompare: { type: 'boolean', default: true },
		},
		edit( props ) {
			const { attributes, setAttributes } = props;
			const blockProps = useBlockProps();

			return el(
				'div',
				blockProps,
				el(
					InspectorControls,
					{},
					el(
						PanelBody,
						{ title: __( 'Settings', 'shelterkit-pets' ) },
						el( SelectControl, {
							label: __( 'Position', 'shelterkit-pets' ),
							value: attributes.position,
							options: [
								{
									label: __(
										'Bottom Right',
										'shelterkit-pets'
									),
									value: 'bottom-right',
								},
								{
									label: __(
										'Bottom Left',
										'shelterkit-pets'
									),
									value: 'bottom-left',
								},
							],
							onChange: ( val ) =>
								setAttributes( { position: val } ),
						} ),
						el( ToggleControl, {
							label: __(
								'Show Compare Button',
								'shelterkit-pets'
							),
							checked: attributes.showCompare,
							onChange: ( val ) =>
								setAttributes( { showCompare: val } ),
						} )
					)
				),
				el(
					'div',
					{
						style: {
							display: 'flex',
							alignItems: 'center',
							gap: '12px',
							padding: '16px 20px',
							background: '#f9f9f9',
							borderRadius: '8px',
							border: '1px dashed #ddd',
						},
					},
					el( 'span', {
						className: 'dashicons dashicons-heart',
						style: { fontSize: '24px', color: '#cf2e2e' },
					} ),
					el(
						'div',
						{},
						el(
							'strong',
							{},
							__( 'Favorites Modal', 'shelterkit-pets' )
						),
						el(
							'p',
							{
								style: {
									margin: '4px 0 0',
									fontSize: '12px',
									color: '#757575',
								},
							},
							__(
								'Floating heart button appears on the frontend. Click to open a modal with all favorited pets.',
								'shelterkit-pets'
							)
						)
					)
				)
			);
		},
		save: () => null,
	} );
} )( window.wp );
