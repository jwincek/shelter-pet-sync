/**
 * Pet Details Block Editor
 *
 * Provides InnerBlocks editing with a default template of bound pet blocks.
 * Users can customize the layout while blocks stay bound to pet data.
 *
 * Since 3.0.0: showGallery/showActions attributes removed — InnerBlocks
 * composition controls which sections appear. Breadcrumb added to templates.
 *
 * @package Petstablished_Sync
 * @since 3.0.0
 */

( function() {
	'use strict';

	const { InnerBlocks, useBlockProps, InspectorControls } = wp.blockEditor;
	const { PanelBody, SelectControl } = wp.components;
	const { __ } = wp.i18n;
	const el = wp.element.createElement;

	/**
	 * Default InnerBlocks template — sidebar layout.
	 *
	 * Matches the structure in templates/single-pet.html so the editor
	 * preview and front-end rendering are consistent.
	 */
	const DEFAULT_TEMPLATE = [
		// Breadcrumb
		[ 'core/group', {
			metadata: { name: 'Breadcrumb' },
			className: 'pet-details__breadcrumb',
			layout: { type: 'flex', flexWrap: 'nowrap' },
		}, [
			[ 'core/paragraph', {
				className: 'pet-details__back-link',
				content: '<a href="#pet-archive">← ' + __( 'Back to All Pets', 'shelter-pets' ) + '</a>',
			} ],
		] ],

		// Two-column layout
		[ 'core/columns', { className: 'pet-details__columns' }, [
			// Left column — Gallery + Actions
			[ 'core/column', { width: '50%', className: 'pet-details__gallery-col' }, [
				[ 'petsync/pet-gallery', {} ],
				[ 'petsync/pet-actions', {} ],
			] ],

			// Right column — Info
			[ 'core/column', { width: '50%', className: 'pet-details__info-col' }, [
				// Header
				[ 'core/group', {
					metadata: { name: 'Pet Header' },
					className: 'pet-details__header',
					layout: { type: 'flex', flexWrap: 'wrap', justifyContent: 'space-between', verticalAlignment: 'center' },
				}, [
					[ 'core/heading', {
						level: 1,
						className: 'pet-details__name',
						metadata: { bindings: { content: { source: 'petsync/pet-data', args: { key: 'name' } } } },
					} ],
					[ 'core/paragraph', {
						className: 'pet-details__status',
						metadata: { bindings: { content: { source: 'petsync/pet-data', args: { key: 'status' } } } },
					} ],
				] ],

				// Tagline
				[ 'core/paragraph', {
					className: 'pet-details__tagline',
					metadata: { bindings: { content: { source: 'petsync/pet-data', args: { key: 'tagline' } } } },
				} ],

				// About section
				[ 'core/group', {
					metadata: { name: 'About Section' },
					className: 'pet-details__section pet-details__about',
				}, [
					[ 'core/heading', { level: 2, content: __( 'About', 'shelter-pets' ) } ],
					[ 'petsync/pet-attributes', {} ],
				] ],

				// Compatibility section
				[ 'core/group', {
					metadata: { name: 'Compatibility Section' },
					className: 'pet-details__section pet-details__compat',
				}, [
					[ 'core/heading', { level: 2, content: __( 'Good With', 'shelter-pets' ) } ],
					[ 'petsync/pet-compatibility', {} ],
				] ],

				// Health section
				[ 'core/group', {
					metadata: { name: 'Health Section' },
					className: 'pet-details__section pet-details__health-section',
				}, [
					[ 'core/heading', { level: 2, content: __( 'Health', 'shelter-pets' ) } ],
					[ 'petsync/pet-health', {} ],
				] ],
			] ],
		] ],

		// Story section
		[ 'core/group', {
			metadata: { name: 'Story Section' },
			className: 'pet-details__section pet-details__story',
		}, [
			[ 'core/heading', {
				level: 2,
				metadata: { bindings: { content: { source: 'petsync/pet-data', args: { key: 'story_title' } } } },
			} ],
			[ 'core/paragraph', {
				className: 'pet-details__description',
				metadata: { bindings: { content: { source: 'petsync/pet-data', args: { key: 'description' } } } },
			} ],
		] ],

		// Adoption CTA
		[ 'petsync/pet-adoption-cta', {} ],
	];

	/**
	 * Stacked layout template — gallery at top, single-column info.
	 */
	const STACKED_TEMPLATE = [
		// Breadcrumb
		[ 'core/group', {
			metadata: { name: 'Breadcrumb' },
			className: 'pet-details__breadcrumb',
			layout: { type: 'flex', flexWrap: 'nowrap' },
		}, [
			[ 'core/paragraph', {
				className: 'pet-details__back-link',
				content: '<a href="#pet-archive">← ' + __( 'Back to All Pets', 'shelter-pets' ) + '</a>',
			} ],
		] ],

		// Gallery at top
		[ 'petsync/pet-gallery', {} ],

		// Header
		[ 'core/group', {
			metadata: { name: 'Pet Header' },
			className: 'pet-details__header',
			layout: { type: 'flex', flexWrap: 'wrap', justifyContent: 'space-between' },
		}, [
			[ 'core/heading', {
				level: 1,
				metadata: { bindings: { content: { source: 'petsync/pet-data', args: { key: 'name' } } } },
			} ],
			[ 'core/paragraph', {
				className: 'pet-details__status',
				metadata: { bindings: { content: { source: 'petsync/pet-data', args: { key: 'status' } } } },
			} ],
		] ],

		// Quick actions
		[ 'petsync/pet-actions', {} ],

		// Tagline
		[ 'core/paragraph', {
			className: 'pet-details__tagline',
			metadata: { bindings: { content: { source: 'petsync/pet-data', args: { key: 'tagline' } } } },
		} ],

		// About
		[ 'core/group', {
			metadata: { name: 'About Section' },
			className: 'pet-details__section',
		}, [
			[ 'core/heading', { level: 2, content: __( 'About', 'shelter-pets' ) } ],
			[ 'petsync/pet-attributes', {} ],
		] ],

		// Compatibility & Health side by side
		[ 'core/columns', {}, [
			[ 'core/column', {}, [
				[ 'core/heading', { level: 2, content: __( 'Good With', 'shelter-pets' ) } ],
				[ 'petsync/pet-compatibility', {} ],
			] ],
			[ 'core/column', {}, [
				[ 'core/heading', { level: 2, content: __( 'Health', 'shelter-pets' ) } ],
				[ 'petsync/pet-health', {} ],
			] ],
		] ],

		// Story
		[ 'core/group', {
			metadata: { name: 'Story Section' },
			className: 'pet-details__section pet-details__story',
		}, [
			[ 'core/heading', {
				level: 2,
				metadata: { bindings: { content: { source: 'petsync/pet-data', args: { key: 'story_title' } } } },
			} ],
			[ 'core/paragraph', {
				metadata: { bindings: { content: { source: 'petsync/pet-data', args: { key: 'description' } } } },
			} ],
		] ],

		// Adoption CTA
		[ 'petsync/pet-adoption-cta', {} ],
	];

	/**
	 * Allowed blocks for pet-details InnerBlocks.
	 */
	const ALLOWED_BLOCKS = [
		'core/group',
		'core/columns',
		'core/column',
		'core/heading',
		'core/paragraph',
		'core/image',
		'core/button',
		'core/buttons',
		'core/list',
		'core/separator',
		'core/spacer',
		'petsync/pet-gallery',
		'petsync/pet-actions',
		'petsync/pet-attributes',
		'petsync/pet-compatibility',
		'petsync/pet-health',
		'petsync/pet-adoption-cta',
	];

	// Use hooks to add controls to the registered block.
	wp.hooks.addFilter(
		'blocks.registerBlockType',
		'petsync/pet-details-inner-blocks',
		function( settings, name ) {
			if ( name !== 'petsync/pet-details' ) {
				return settings;
			}

			// Override the edit function to use InnerBlocks.
			const originalEdit = settings.edit;

			settings.edit = function( props ) {
				const { attributes, setAttributes } = props;
				const { layout } = attributes;

				const blockProps = useBlockProps( {
					className: 'pet-details pet-details--' + layout,
				} );

				// Select template based on layout.
				const template = layout === 'stacked' ? STACKED_TEMPLATE : DEFAULT_TEMPLATE;

				return el(
					wp.element.Fragment,
					null,
					el(
						InspectorControls,
						null,
						el(
							PanelBody,
							{ title: __( 'Layout', 'shelter-pets' ) },
							el( SelectControl, {
								label: __( 'Layout Style', 'shelter-pets' ),
								value: layout,
								options: [
									{ label: __( 'Sidebar (Gallery Left)', 'shelter-pets' ), value: 'sidebar' },
									{ label: __( 'Stacked (Gallery Top)', 'shelter-pets' ), value: 'stacked' },
								],
								onChange: function( value ) {
									setAttributes( { layout: value } );
								},
							} )
						)
					),
					el(
						'article',
						blockProps,
						el( InnerBlocks, {
							allowedBlocks: ALLOWED_BLOCKS,
							template: template,
							templateLock: false,
						} )
					)
				);
			};

			// Save uses InnerBlocks.Content — the server render.php
			// wraps this in the <article> with interactivity attributes.
			settings.save = function() {
				return el( InnerBlocks.Content );
			};

			return settings;
		}
	);

} )();
