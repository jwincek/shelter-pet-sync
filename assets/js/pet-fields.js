/**
 * Pet Fields Panel (No Build)
 *
 * Sidebar panels for entering pet details by hand, so a shelter with no
 * Petstablished account can create a pet and have every block render.
 *
 * Panels, fields and control types all come from entities.json via the
 * localized `shelterPetsFields` config — adding a field is a config change,
 * not a change here.
 *
 * Uses wp.* globals instead of imports, so there is no build step.
 *
 * @param wp
 * @package
 * @since 1.0.0
 */

( function ( wp ) {
	const config = window.shelterPetsFields;

	if ( ! config || ! config.fields || ! config.fields.length ) {
		return;
	}

	const { registerPlugin } = wp.plugins;
	// PluginDocumentSettingPanel moved from wp.editPost to wp.editor. Prefer
	// the current location and fall back so the panel survives either.
	const { PluginDocumentSettingPanel } = wp.editor || wp.editPost || {};
	const { createElement: el, Fragment } = wp.element;
	const {
		TextControl,
		TextareaControl,
		SelectControl,
		Notice,
		BaseControl,
		Button,
		Flex,
		FlexItem,
	} = wp.components;
	const { MediaUpload, MediaUploadCheck } = wp.blockEditor;
	const { useSelect } = wp.data;
	const { useEntityProp } = wp.coreData;
	const { __, _n, sprintf } = wp.i18n;

	// Strings live here rather than being passed from PHP so the extractor sees
	// literals — a format string arriving as data can neither be validated by
	// @wordpress/valid-sprintf nor picked up for translation. Matches how
	// blocks-editor.js handles its strings.
	const PROVIDER_NAMES = {
		petstablished: __( 'Petstablished', 'shelter-pets' ),
	};

	if ( ! PluginDocumentSettingPanel ) {
		return;
	}

	const isLocked = !! config.provider;

	/**
	 * Human-readable platform name, falling back to the raw slug.
	 *
	 * @param {string} slug Provider slug.
	 * @return {string} Display name.
	 */
	function providerName( slug ) {
		return PROVIDER_NAMES[ slug ] || slug;
	}

	/**
	 * Render one field's control, bound to its post meta.
	 *
	 * @param {Object}   field   Field config.
	 * @param {Object}   meta    Current meta values.
	 * @param {Function} setMeta Meta setter.
	 * @return {Object} Element.
	 */
	function renderControl( field, meta, setMeta ) {
		const value = meta[ field.metaKey ] || '';

		const onChange = ( next ) => {
			const update = {};
			update[ field.metaKey ] = next;
			setMeta( update );
		};

		const shared = {
			key: field.name,
			label: field.label,
			value,
			onChange,
			disabled: isLocked,
			__nextHasNoMarginBottom: true,
			__next40pxDefaultSize: true,
		};

		if ( 'tristate' === field.control ) {
			return el(
				SelectControl,
				Object.assign( {}, shared, {
					options: [
						{
							label: __( 'Not recorded', 'shelter-pets' ),
							value: '',
						},
						{ label: __( 'Yes', 'shelter-pets' ), value: 'yes' },
						{ label: __( 'No', 'shelter-pets' ), value: 'no' },
						{
							label: __( 'Unknown', 'shelter-pets' ),
							value: 'unknown',
						},
					],
				} )
			);
		}

		if ( 'media' === field.control ) {
			return renderMediaControl( field, meta, setMeta );
		}

		if ( 'textarea' === field.control ) {
			return el( TextareaControl, shared );
		}

		return el(
			TextControl,
			Object.assign( {}, shared, {
				type: 'url' === field.control ? 'url' : 'text',
			} )
		);
	}

	/**
	 * Gallery picker bound to an array of attachment IDs.
	 *
	 * @param {Object}   field   Field config.
	 * @param {Object}   meta    Current meta values.
	 * @param {Function} setMeta Meta setter.
	 * @return {Object} Element.
	 */
	function renderMediaControl( field, meta, setMeta ) {
		const ids = Array.isArray( meta[ field.metaKey ] )
			? meta[ field.metaKey ]
			: [];

		const write = ( next ) => {
			const update = {};
			update[ field.metaKey ] = next;
			setMeta( update );
		};

		const count = ids.length;

		return el(
			BaseControl,
			{
				key: field.name,
				label: field.label,
				__nextHasNoMarginBottom: true,
				help: isLocked
					? undefined
					: sprintf(
							/* translators: %d: number of images currently in the gallery. */
							_n(
								'%d image selected.',
								'%d images selected.',
								count,
								'shelter-pets'
							),
							count
					  ),
			},
			el(
				MediaUploadCheck,
				{},
				el( MediaUpload, {
					multiple: true,
					gallery: true,
					allowedTypes: [ 'image' ],
					value: ids,
					onSelect: ( items ) =>
						write(
							( Array.isArray( items ) ? items : [ items ] ).map(
								( item ) => item.id
							)
						),
					render: ( { open } ) =>
						el(
							Flex,
							{ justify: 'flex-start', gap: 2 },
							el(
								FlexItem,
								{},
								el(
									Button,
									{
										variant: 'secondary',
										onClick: open,
										disabled: isLocked,
										__next40pxDefaultSize: true,
									},
									count
										? __( 'Edit gallery', 'shelter-pets' )
										: __( 'Add images', 'shelter-pets' )
								)
							),
							count && ! isLocked
								? el(
										FlexItem,
										{},
										el(
											Button,
											{
												variant: 'tertiary',
												isDestructive: true,
												onClick: () => write( [] ),
												__next40pxDefaultSize: true,
											},
											__( 'Clear', 'shelter-pets' )
										)
								  )
								: null
						),
				} )
			)
		);
	}

	/**
	 * One sidebar panel per configured group.
	 *
	 * @return {Object} Element.
	 */
	function PetFieldsPanels() {
		const postType = useSelect(
			( select ) => select( 'core/editor' ).getCurrentPostType(),
			[]
		);

		const [ meta, setMeta ] = useEntityProp(
			'postType',
			config.postType,
			'meta'
		);

		if ( postType !== config.postType || ! meta ) {
			return null;
		}

		// Tracks whether the lock notice has been placed yet. Keying it to the
		// first group would misplace it whenever that group has no fields and
		// gets filtered out below.
		let noticePlaced = false;

		const panels = config.groups
			.map( ( group ) => {
				const fields = config.fields.filter(
					( f ) => f.group === group.slug
				);

				if ( ! fields.length ) {
					return null;
				}

				const children = fields.map( ( f ) =>
					renderControl( f, meta, setMeta )
				);

				// The lock explanation belongs once, at the top of the first
				// panel that actually renders — not repeated on all five.
				if ( isLocked && ! noticePlaced ) {
					noticePlaced = true;
					children.unshift(
						el(
							Notice,
							{
								key: 'locked',
								status: 'info',
								isDismissible: false,
							},
							sprintf(
								/* translators: %s: name of the shelter platform, e.g. Petstablished. */
								__(
									'This pet was imported from %s. These fields are managed there — edit them in that platform and they will update on the next sync.',
									'shelter-pets'
								),
								providerName( config.provider )
							)
						)
					);
				}

				return el(
					PluginDocumentSettingPanel,
					{
						key: group.slug,
						name: 'shelter-pets-' + group.slug,
						title: group.label,
						className: 'shelter-pets-field-panel',
					},
					children
				);
			} )
			.filter( Boolean );

		return el( Fragment, null, panels );
	}

	registerPlugin( 'shelter-pets-fields', { render: PetFieldsPanels } );
} )( window.wp );
