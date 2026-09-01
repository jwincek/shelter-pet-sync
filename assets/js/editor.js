/**
 * Petstablished Editor Script (No Build)
 *
 * Adds a sidebar panel for block binding helpers.
 * Uses wp.* globals instead of imports.
 *
 * @param wp
 * @package
 * @since 2.0.0
 */

( function ( wp ) {
	const { registerPlugin } = wp.plugins;
	const { PluginSidebar, PluginSidebarMoreMenuItem } = wp.editPost;
	const { createElement: el, useState } = wp.element;
	const { PanelBody, TextControl, Button, Notice } = wp.components;
	const { useSelect } = wp.data;

	const ICON = el(
		'svg',
		{
			width: 20,
			height: 20,
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
			d: 'M4.42 11.247A13.152 13.152 0 0 0 4 14.556C4 18.728 7.582 21 12 21s8-2.272 8-6.444c0-1.061-.162-2.2-.493-3.309m-9.243-6.082A8.801 8.801 0 0 1 12 5c.78 0 1.5.108 2.161.306',
		} )
	);

	function BindingKeysList() {
		const [ search, setSearch ] = useState( '' );
		const [ copied, setCopied ] = useState( null );
		const keys = window.petstablishedEditor?.bindingKeys || [];

		const filtered = keys.filter(
			( k ) =>
				k.key.toLowerCase().includes( search.toLowerCase() ) ||
				k.desc.toLowerCase().includes( search.toLowerCase() )
		);

		const copyBinding = ( key ) => {
			const json = JSON.stringify(
				{
					source: 'petsync/pet-data',
					args: { key },
				},
				null,
				2
			);

			navigator.clipboard.writeText( json ).then( () => {
				setCopied( key );
				setTimeout( () => setCopied( null ), 2000 );
			} );
		};

		return el(
			'div',
			{ className: 'pet-binding-keys' },
			el( TextControl, {
				placeholder: 'Search binding keys...',
				value: search,
				onChange: setSearch,
				className: 'pet-binding-search',
			} ),
			el(
				'ul',
				{ className: 'pet-binding-list' },
				filtered.map( ( item ) =>
					el(
						'li',
						{
							key: item.key,
							className: 'pet-binding-item',
						},
						el(
							'div',
							{ className: 'pet-binding-info' },
							el(
								'code',
								{ className: 'pet-binding-key' },
								item.key
							),
							el(
								'span',
								{
									className: `pet-binding-type pet-binding-type--${ item.type }`,
								},
								item.type
							),
							el(
								'span',
								{ className: 'pet-binding-desc' },
								item.desc
							)
						),
						el(
							Button,
							{
								isSmall: true,
								variant:
									copied === item.key
										? 'primary'
										: 'secondary',
								onClick: () => copyBinding( item.key ),
								'aria-label': `Copy ${ item.key } binding JSON`,
							},
							copied === item.key ? '✓' : 'Copy'
						)
					)
				)
			),
			filtered.length === 0 &&
				el(
					Notice,
					{
						status: 'warning',
						isDismissible: false,
					},
					'No matching binding keys.'
				)
		);
	}

	function UsageInstructions() {
		return el(
			PanelBody,
			{
				title: 'How to Use',
				initialOpen: false,
			},
			el(
				'ol',
				{ className: 'pet-binding-instructions' },
				el( 'li', null, 'Select a block (Paragraph, Image, etc.)' ),
				el( 'li', null, "Open the block's Advanced settings" ),
				el( 'li', null, 'Find the binding key you want above' ),
				el( 'li', null, 'Click "Copy" to copy the binding JSON' ),
				el(
					'li',
					null,
					'Paste into the Metadata section or use Code Editor'
				)
			),
			el(
				'p',
				null,
				el( 'strong', null, 'Example:' ),
				' For a pet\'s breed, use the "breed" binding key.'
			)
		);
	}

	function PetBindingsSidebar() {
		const postType = useSelect( ( select ) =>
			select( 'core/editor' )?.getCurrentPostType()
		);

		// Only show for pet post type or when editing templates.
		// 'vcps_pet', not 'pet'. Against 'pet' this was never true on a pet,
		// so the sidebar greeted you with "Pet bindings work in the Pet post
		// type or templates" on the one screen where they do.
		const isRelevant =
			postType === 'vcps_pet' || postType === 'wp_template';

		return el(
			'div',
			null,
			el(
				PluginSidebarMoreMenuItem,
				{ target: 'pet-bindings-sidebar' },
				'Pet Data Bindings'
			),
			el(
				PluginSidebar,
				{
					name: 'pet-bindings-sidebar',
					title: 'Pet Data Bindings',
					icon: ICON,
				},
				! isRelevant &&
					el(
						Notice,
						{
							status: 'info',
							isDismissible: false,
						},
						'Pet bindings work in the Pet post type or templates.'
					),
				el(
					PanelBody,
					{ title: 'Available Binding Keys', initialOpen: true },
					el( BindingKeysList )
				),
				el( UsageInstructions )
			)
		);
	}

	registerPlugin( 'petstablished-bindings', {
		render: PetBindingsSidebar,
		icon: ICON,
	} );
} )( window.wp );
