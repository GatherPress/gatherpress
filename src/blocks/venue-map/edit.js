/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import {
	BlockControls,
	InspectorControls,
	useBlockProps,
} from '@wordpress/block-editor';
import {
	Dropdown,
	PanelBody,
	RangeControl,
	ResizableBox,
	SelectControl,
	TextControl,
	ToggleControl,
	ToolbarButton,
	ToolbarGroup,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalToolsPanelItem as ToolsPanelItem,
} from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { useEffect } from '@wordpress/element';
import { Icon, link as linkIcon, mapMarker } from '@wordpress/icons';

/**
 * Internal dependencies
 */
import { isVenuePostType } from '../../helpers/venue';
import { isInFSETemplate } from '../../helpers/editor';
import { getFromSettings } from '../../helpers/editor-settings';
import MapEmbed from '../../components/MapEmbed';
import {
	GOOGLE_IFRAME_UNSUPPORTED_MAP_TYPE_SLUGS,
	GOOGLE_MAP_TYPE_DEFINITIONS,
	toMapsEmbedApiMapType,
} from '../../components/GoogleMap';
import {
	useSharedBlockGuardState,
	generateBlockGuardStateKey,
} from '../../supports/block-guard';
import {
	RegenerateMapButton,
	pickDescriptorForCombo,
	getDimensionValue,
	parsePxDimension,
	resolveDimensions,
	usePlaceholderPolling,
} from './helpers';

const HEIGHT_MIN = 100;
const HEIGHT_MAX = 4000;
const DEFAULT_HEIGHT = 300;

// Presets for the aspect-ratio dropdown. The first entry matches the
// server-side `Venue\Map::DEFAULT_ASPECT_RATIO` (2/1) so freshly inserted
// blocks keep the behavior that shipped in the first round of static maps.
const ASPECT_RATIO_PRESETS = [
	{ label: __( 'Landscape - 2:1', 'gatherpress' ), value: '2/1' },
	{ label: __( 'Wide - 16:9', 'gatherpress' ), value: '16/9' },
	{ label: __( 'Classic - 3:2', 'gatherpress' ), value: '3/2' },
	{ label: __( 'Standard - 4:3', 'gatherpress' ), value: '4/3' },
	{ label: __( 'Square - 1:1', 'gatherpress' ), value: '1/1' },
	{ label: __( 'Custom', 'gatherpress' ), value: 'custom' },
];

// Allow-list for the `scale` block attribute — mirrors
// `Venue\Map::SCALE_OPTIONS` so JS and PHP can't drift. The Scale
// SelectControl options are derived from this list; the guard that
// hides the control when render mode is interactive reads from it too.
const SCALE_OPTIONS = [ 'cover', 'contain', 'fill' ];
const SCALE_DEFAULT = 'cover';
const SCALE_LABELS = {
	cover: __( 'Cover', 'gatherpress' ),
	contain: __( 'Contain', 'gatherpress' ),
	fill: __( 'Fill', 'gatherpress' ),
};

/**
 * Normalize a Settings → Venues default dimension into a usable value.
 *
 * The settings store pixel integers ('' or 0 meaning "not set"). Returns
 * the positive pixel count, or undefined so the caller's fallback chain
 * continues to "auto".
 *
 * @since 0.35.0
 *
 * @param {*} value Raw setting value.
 *
 * @return {number|undefined} Positive pixel count, or undefined.
 */
const toSiteDefaultDimension = ( value ) => {
	const parsed = parseInt( value, 10 );

	return Number.isInteger( parsed ) && 0 < parsed ? parsed : undefined;
};

const LINK_DESTINATION_NONE = 'none';
const LINK_DESTINATION_OPENSTREETMAP = 'openstreetmap';
const LINK_DESTINATION_GOOGLE = 'google';
const LINK_DESTINATION_CUSTOM = 'custom';

// Stable empty-object sentinels used as fallbacks in the useSelect selector.
// Returning inline `{}` literals on every call creates new references each
// render, triggering Gutenberg's "Non-equal value keys" warning and causing
// unnecessary re-renders. Frozen constants ensure referential stability when
// the underlying data is absent.
const EMPTY_META = Object.freeze( {} );
const EMPTY_STATIC_MAP_DESCRIPTORS = Object.freeze( {} );
const EMPTY_POST = Object.freeze( {} );

/**
 * Build the canonical external-map URL for a preset destination.
 *
 * @param {string} destination One of the LINK_DESTINATION_* constants.
 * @param {string} latitude    Venue latitude.
 * @param {string} longitude   Venue longitude.
 * @param {number} zoom        Desired zoom level.
 *
 * @return {string} Computed href, or '' when the preset doesn't apply.
 */
const buildExternalMapUrl = ( destination, latitude, longitude, zoom ) => {
	if ( ! latitude || ! longitude ) {
		return '';
	}
	switch ( destination ) {
		case LINK_DESTINATION_OPENSTREETMAP:
			return `https://www.openstreetmap.org/?mlat=${ latitude }&mlon=${ longitude }#map=${ zoom }/${ latitude }/${ longitude }`;
		case LINK_DESTINATION_GOOGLE:
			return `https://www.google.com/maps/?q=${ latitude },${ longitude }&z=${ zoom }`;
		default:
			return '';
	}
};

/**
 * Edit component for the Venue Map block.
 *
 * @since 0.34.0
 *
 * @param {Object}   props               - Component properties.
 * @param {Object}   props.attributes    - Block attributes.
 * @param {Function} props.setAttributes - Function to set block attributes.
 * @param {string}   props.clientId      - The block's runtime client ID (used to find the parent venue block's guard state).
 * @param {Object}   props.context       - Block context.
 *
 * @return {JSX.Element} The rendered React component.
 */
const Edit = ( { attributes, setAttributes, context, clientId } ) => {
	const {
		zoom,
		type,
		aspectRatio,
		scale,
		renderMode,
		href,
		linkDestination,
		linkTarget,
		rel,
	} = attributes;
	// The map always fills its container — width comes from the column
	// and the block's alignment, never from a stored value. Height is the
	// only stored dimension (style.dimensions.height via core's dimensions
	// support, serialization skipped so this block owns its own output).
	// An unset height falls back to the site-wide default from Settings →
	// Venues; unset there too means the aspect ratio shapes the block.
	const heightValue =
		getDimensionValue( attributes, 'height' ) ??
		toSiteDefaultDimension( getFromSettings( 'venueMapDefaultHeight' ) );
	const heightPx = parsePxDimension( heightValue );
	const blockProps = useBlockProps();

	// Determine the venue post ID and get venue meta + static-map descriptors.
	// `savedVenueMeta` reflects what's persisted server-side — compared
	// against the edited meta below to detect unsaved address/coord changes
	// and force the placeholder until the next save regenerates the PNG.
	const {
		isEditingThisVenue,
		venueMeta,
		savedVenueMeta,
		staticMapDescriptors,
		venuePostId,
		venuePostType,
	} = useSelect(
		( select ) => {
			const currentPostId = select( 'core/editor' )?.getCurrentPostId();
			const contextPostId = context?.postId || 0;

			const effectiveVenuePostId =
				contextPostId ||
				( isVenuePostType() ? currentPostId : 0 );

			const resolvedVenuePostType =
				context?.postType ||
				select( 'core/editor' )?.getCurrentPostType() ||
				'';

			if ( ! effectiveVenuePostId ) {
				return {
					isEditingThisVenue: false,
					venueMeta: EMPTY_META,
					savedVenueMeta: EMPTY_META,
					staticMapDescriptors: EMPTY_STATIC_MAP_DESCRIPTORS,
					venuePostId: 0,
					venuePostType: '',
				};
			}

			const isEditing =
				currentPostId === effectiveVenuePostId && isVenuePostType();

			if ( isEditing ) {
				const meta =
					select( 'core/editor' )?.getEditedPostAttribute( 'meta' ) || EMPTY_META;
				const savedPost =
					select( 'core/editor' )?.getCurrentPost() || EMPTY_POST;
				// Prefer the `core` entity cache for the descriptors map —
				// `regenerate` patches that cache directly via
				// receiveEntityRecords, but `core/editor`'s in-memory copy of
				// currentPost is seeded from server on load and never syncs
				// back, so reading it here would show stale URLs.
				const editedVenuePost = select( 'core' ).getEditedEntityRecord(
					'postType',
					resolvedVenuePostType,
					effectiveVenuePostId
				);
				return {
					isEditingThisVenue: true,
					venueMeta: meta,
					savedVenueMeta: savedPost?.meta || EMPTY_META,
					staticMapDescriptors:
						editedVenuePost?.meta?.gatherpress_static_map ||
						meta?.gatherpress_static_map ||
						EMPTY_STATIC_MAP_DESCRIPTORS,
					venuePostId: effectiveVenuePostId,
					venuePostType: resolvedVenuePostType,
				};
			}

			const { getEditedEntityRecord } = select( 'core' );
			const venuePost = getEditedEntityRecord(
				'postType',
				context?.postType,
				effectiveVenuePostId
			);

			const meta = venuePost?.meta || EMPTY_META;

			return {
				isEditingThisVenue: false,
				venueMeta: meta,
				savedVenueMeta: meta,
				staticMapDescriptors:
					meta?.gatherpress_static_map || EMPTY_STATIC_MAP_DESCRIPTORS,
				venuePostId: effectiveVenuePostId,
				venuePostType: context?.postType || '',
			};
		},
		[ context?.postId, context?.postType ]
	);

	const { storeLat, storeLng } = useSelect(
		( select ) => ( {
			storeLat: select( 'gatherpress/venue' ).getVenueLatitude(),
			storeLng: select( 'gatherpress/venue' ).getVenueLongitude(),
		} ),
		[]
	);

	const address = venueMeta.gatherpress_address || '';

	let latitude = venueMeta.gatherpress_latitude || '';
	let longitude = venueMeta.gatherpress_longitude || '';

	if ( isEditingThisVenue ) {
		latitude =
			null !== storeLat && storeLat !== undefined
				? String( storeLat )
				: latitude;
		longitude =
			null !== storeLng && storeLng !== undefined
				? String( storeLng )
				: longitude;
	}

	const mapPlatform = getFromSettings( 'mapPlatform' );
	const googleMapsApiKey = getFromSettings( 'googleMapsApiKey' ) || '';
	// Resolve the site-wide scale default from Settings so "Reset all"
	// and the "has value" check on the Scale ToolsPanelItem mirror what
	// apply_block_attribute_defaults() stamps on the block. Fall back to
	// SCALE_DEFAULT if Settings carries anything outside the allow-list.
	const rawSiteScale = getFromSettings( 'venueMapDefaultScale' );
	const siteScaleDefault = SCALE_OPTIONS.includes( rawSiteScale )
		? rawSiteScale
		: SCALE_DEFAULT;
	const showMapTypeControl =
		'interactive' === renderMode && 'google' === mapPlatform;

	// Full list of map types for Google. Maps Embed API (iframe) supports
	// only roadmap and satellite — hybrid/terrain are filtered out below until
	// a Maps JavaScript API integration can use the full set without changing
	// `GOOGLE_MAP_TYPE_DEFINITIONS` in `GoogleMap.js`.
	const GOOGLE_MAP_TYPE_OPTIONS_ALL = GOOGLE_MAP_TYPE_DEFINITIONS.map(
		( definition ) => ( {
			label: definition.label,
			value: definition.slug,
		} )
	);

	const IFRAME_UNSUPPORTED_GOOGLE_MAP_TYPES = new Set(
		GOOGLE_IFRAME_UNSUPPORTED_MAP_TYPE_SLUGS
	);

	const googleMapTypeSelectOptions = GOOGLE_MAP_TYPE_OPTIONS_ALL.filter(
		( opt ) => ! IFRAME_UNSUPPORTED_GOOGLE_MAP_TYPES.has( opt.value )
	);

	useEffect( () => {
		if (
			showMapTypeControl &&
			! [ 'roadmap', 'satellite' ].includes( type )
		) {
			setAttributes( {
				type: toMapsEmbedApiMapType( type ),
			} );
		}
	}, [ showMapTypeControl, type, setAttributes ] );

	// Link destination options track the site's mapping platform: on an
	// OSM-powered site we only offer the OpenStreetMap preset (and vice
	// versa). Mixing an "open in Google Maps" link into a Leaflet/OSM
	// install would be confusing — users would expect whatever service
	// the rest of the site uses.
	const platformOption = 'google' === mapPlatform
		? { label: __( 'Google Maps', 'gatherpress' ), value: LINK_DESTINATION_GOOGLE }
		: { label: __( 'OpenStreetMap', 'gatherpress' ), value: LINK_DESTINATION_OPENSTREETMAP };
	const linkDestinationOptions = [
		{ label: __( 'None', 'gatherpress' ), value: LINK_DESTINATION_NONE },
		platformOption,
		{
			label: __( 'Custom URL', 'gatherpress' ),
			value: LINK_DESTINATION_CUSTOM,
		},
	];

	const hasUnsavedMapInputs =
		isEditingThisVenue &&
		( ( venueMeta.gatherpress_address || '' ) !==
			( savedVenueMeta.gatherpress_address || '' ) ||
			( venueMeta.gatherpress_latitude || '' ) !==
				( savedVenueMeta.gatherpress_latitude || '' ) ||
			( venueMeta.gatherpress_longitude || '' ) !==
				( savedVenueMeta.gatherpress_longitude || '' ) );

	// Write dimensions to style.dimensions. `undefined` removes a
	// dimension ("auto" — or the site default when one is configured).
	const setDimensions = ( changes, extraAttributes = {} ) => {
		const nextDimensions = { ...( attributes.style?.dimensions ?? {} ) };

		Object.entries( changes ).forEach( ( [ key, value ] ) => {
			if ( undefined === value ) {
				delete nextDimensions[ key ];
			} else {
				nextDimensions[ key ] = value;
			}
		} );

		setAttributes( {
			...extraAttributes,
			style: {
				...attributes.style,
				dimensions: nextDimensions,
			},
		} );
	};

	// Compute the effective pixel dimensions (matching what the server
	// will compose) so the cached-PNG lookup hits the right combo key.
	const { width: effectiveWidth, height: effectiveHeight } =
		resolveDimensions( {
			width: 0,
			height: heightPx,
			aspectRatio,
			defaultHeight: DEFAULT_HEIGHT,
		} );

	const comboKey = `${ zoom }x${ effectiveWidth }x${ effectiveHeight }`;
	const staticMapDescriptor = pickDescriptorForCombo(
		staticMapDescriptors,
		comboKey,
		mapPlatform || 'osm'
	);
	const staticMapUrl = staticMapDescriptor?.url || '';
	const isStaticMode = 'static' === renderMode;
	const showStaticImage =
		isStaticMode && '' !== staticMapUrl && ! hasUnsavedMapInputs;
	const showStaticPlaceholder = isStaticMode && ! showStaticImage;

	const isCustomAspectRatio =
		! ASPECT_RATIO_PRESETS.some(
			( preset ) => preset.value === aspectRatio
		) || 'custom' === aspectRatio;

	// Find the nearest `gatherpress/venue` ancestor so we can subscribe to
	// the exact guard state the block-guard HOC writes to (it's keyed per
	// instance via generateBlockGuardStateKey, not by block name alone —
	// subscribing to the bare name would miss every update).
	const parentVenueClientId = useSelect(
		( select ) => {
			if ( ! clientId ) {
				return '';
			}
			const { getBlockParentsByBlockName } = select( 'core/block-editor' );
			const parents = getBlockParentsByBlockName(
				clientId,
				'gatherpress/venue'
			);
			if ( ! Array.isArray( parents ) || 0 === parents.length ) {
				return '';
			}
			// getBlockParentsByBlockName returns root → self order, so the
			// last entry is the closest ancestor.
			return parents.at( -1 );
		},
		[ clientId ]
	);
	const parentGuardKey = parentVenueClientId
		? generateBlockGuardStateKey( 'gatherpress/venue', parentVenueClientId )
		: 'gatherpress/venue';
	const [ isParentGuarded ] = useSharedBlockGuardState( parentGuardKey );

	// Close the "async descriptor arrived while placeholder is showing"
	// gap — see `usePlaceholderPolling` for the cadence + bail-out logic.
	usePlaceholderPolling( {
		active:
			showStaticPlaceholder &&
			Boolean( address ) &&
			Boolean( latitude ) &&
			Boolean( longitude ),
		venuePostId,
		venuePostType,
	} );

	// Keep the href in sync with a preset link destination so toggling the
	// dropdown doesn't leave a stale URL behind. We only auto-write when a
	// preset is chosen; `custom` lets the user edit the field freely.
	useEffect( () => {
		if (
			LINK_DESTINATION_OPENSTREETMAP === linkDestination ||
			LINK_DESTINATION_GOOGLE === linkDestination
		) {
			const computed = buildExternalMapUrl(
				linkDestination,
				latitude,
				longitude,
				zoom
			);
			if ( computed && computed !== href ) {
				setAttributes( { href: computed } );
			}
		} else if ( LINK_DESTINATION_NONE === linkDestination && href ) {
			setAttributes( { href: '' } );
		}
		// linkTarget/rel intentionally excluded — users edit them directly.
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ linkDestination, latitude, longitude, zoom ] );

	const onResizeStop = ( event, direction, elt, delta ) => {
		// ResizableBox hands us the pixel delta from resize start; combine
		// with the effective height we rendered at to get the new value.
		// Dragging always commits an explicit height — which takes over
		// from the aspect ratio's derived shape until reset.
		const newHeight = Math.max(
			HEIGHT_MIN,
			Math.min(
				HEIGHT_MAX,
				Math.round( effectiveHeight + delta.height )
			)
		);

		setDimensions( { height: `${ newHeight }px` } );
	};

	// The wrapper always spans its container; an explicit height stamps
	// inline (and wins over the ratio), an unset height leaves the aspect
	// ratio shaping the block as its container width changes.
	const toCssDimension = ( value ) =>
		'number' === typeof value ? `${ value }px` : value;
	const wrapperStyle = {
		width: '100%',
		height:
			undefined === heightValue
				? undefined
				: toCssDimension( heightValue ),
		aspectRatio:
			undefined === heightValue ? aspectRatio || '2/1' : undefined,
	};

	// Inside the ResizableBox the box owns the dimensions — the preview must
	// fill it edge to edge or the map detaches from the drag handles while
	// resizing. 100%/100% tracks whatever the box (or an in-flight drag)
	// sets; when the box height is auto the 100% resolves to auto and the
	// aspect-ratio takes over, matching the standalone sizing rules above.
	// The guarded branch renders without the box, so it keeps wrapperStyle.
	const boxedStyle = {
		width: '100%',
		height: '100%',
		aspectRatio: wrapperStyle.aspectRatio,
	};
	const previewStyle = isParentGuarded ? wrapperStyle : boxedStyle;

	const previewContent = (
		<>
			{ showStaticImage && (
				<div
					className="gatherpress-venue-map gatherpress-venue-map--static"
					style={ previewStyle }
				>
					<img
						className="gatherpress-venue-map__image"
						src={ staticMapUrl }
						alt={
							address
								? `${ __( 'Map of', 'gatherpress' ) } ${ address }`
								: __( 'Venue map', 'gatherpress' )
						}
						style={ {
							objectFit: SCALE_OPTIONS.includes( scale )
								? scale
								: siteScaleDefault,
						} }
					/>
				</div>
			) }
			{ showStaticPlaceholder && (
				<div
					className="gatherpress-venue-map gatherpress-venue-map--static"
					style={ previewStyle }
				>
					<div className="gatherpress-venue-map__placeholder">
						{ ! address && isInFSETemplate() && (
							<Icon
								icon={ mapMarker }
								size={ 48 }
								className="gatherpress-venue-map__placeholder-icon"
							/>
						) }
						{ ! address &&
							! isInFSETemplate() &&
							__(
								'Add an address to generate the map.',
								'gatherpress'
							) }
						{ address &&
							hasUnsavedMapInputs &&
							__(
								'Save the venue first.',
								'gatherpress'
							) }
						{ address &&
							! hasUnsavedMapInputs &&
							0 < venuePostId && (
							<>
								<span>
									{ __(
										'Ready to generate the map.',
										'gatherpress'
									) }
								</span>
								<RegenerateMapButton
									venuePostId={ venuePostId }
									venuePostType={ venuePostType }
									zoom={ zoom }
									width={ effectiveWidth }
									height={ effectiveHeight }
									aspectRatio={ aspectRatio }
									label={ __(
										'Generate map',
										'gatherpress'
									) }
									variant="primary"
								/>
							</>
						) }
					</div>
				</div>
			) }
			{ ! isStaticMode && (
				<div
					className="gatherpress-venue-map gatherpress-venue-map--interactive"
					style={ previewStyle }
				>
					<MapEmbed
						location={ address }
						latitude={ latitude }
						longitude={ longitude }
						zoom={ zoom }
						type={ type }
						googleMapsApiKey={ googleMapsApiKey }
					/>
				</div>
			) }
		</>
	);

	return (
		<>
			<InspectorControls>
				<PanelBody>
					<SelectControl
						__next40pxDefaultSize
						label={ __( 'Render mode', 'gatherpress' ) }
						value={ renderMode }
						options={ [
							{
								label: __( 'Interactive', 'gatherpress' ),
								value: 'interactive',
							},
							{
								label: __( 'Static image', 'gatherpress' ),
								value: 'static',
							},
						] }
						onChange={ ( value ) =>
							setAttributes( { renderMode: value } )
						}
					/>
					<RangeControl
						label={ __( 'Zoom level', 'gatherpress' ) }
						value={ zoom }
						onChange={ ( value ) =>
							setAttributes( { zoom: value } )
						}
						min={ 1 }
						max={ 20 }
					/>
					{ showMapTypeControl && (
						<SelectControl
							__next40pxDefaultSize
							label={ __( 'Map type', 'gatherpress' ) }
							value={ type }
							options={ googleMapTypeSelectOptions }
							onChange={ ( value ) =>
								setAttributes( { type: value } )
							}
						/>
					) }
					{ isStaticMode && showStaticImage && 0 < venuePostId && (
						<RegenerateMapButton
							venuePostId={ venuePostId }
							venuePostType={ venuePostType }
							zoom={ zoom }
							width={ effectiveWidth }
							height={ effectiveHeight }
							aspectRatio={ aspectRatio }
							disabled={
								! address || hasUnsavedMapInputs
							}
						/>
					) }
				</PanelBody>
			</InspectorControls>
			{ /* Width & height come from core's dimensions support; the
			     block's own shape controls join that same panel. */ }
			<InspectorControls group="dimensions">
				<ToolsPanelItem
					label={ __( 'Aspect ratio', 'gatherpress' ) }
					hasValue={ () =>
						'' !== ( aspectRatio ?? '' ) &&
						'2/1' !== aspectRatio
					}
					onDeselect={ () =>
						setAttributes( { aspectRatio: '2/1' } )
					}
					resetAllFilter={ ( attrs ) => ( {
						...attrs,
						aspectRatio: '2/1',
					} ) }
					isShownByDefault
					panelId={ clientId }
				>
					<SelectControl
						__next40pxDefaultSize
						label={ __( 'Aspect ratio', 'gatherpress' ) }
						value={ isCustomAspectRatio ? 'custom' : aspectRatio }
						options={ ASPECT_RATIO_PRESETS }
						onChange={ ( value ) => {
							if ( 'custom' === value ) {
								// Clear so the Custom text field below owns
								// the value. If the user typed nothing, the
								// server falls back to DEFAULT_ASPECT_RATIO
								// for dimension derivation.
								setAttributes( { aspectRatio: '' } );
								return;
							}
							// Picking a preset resets height to auto so the
							// new ratio drives the rendered shape — the
							// user's stored width (if any) is preserved.
							setDimensions(
								{ height: undefined },
								{ aspectRatio: value },
							);
						} }
					/>
					{ isCustomAspectRatio && (
						<TextControl
							label={ __(
								'Custom aspect ratio',
								'gatherpress'
							) }
							help={ __(
								'Format: "16/9" or "4:3".',
								'gatherpress'
							) }
							value={ aspectRatio || '' }
							onChange={ ( value ) =>
								setAttributes( { aspectRatio: value } )
							}
						/>
					) }
				</ToolsPanelItem>
				{ isStaticMode && (
					<ToolsPanelItem
						label={ __( 'Scale', 'gatherpress' ) }
						hasValue={ () =>
							siteScaleDefault !==
								( scale ?? siteScaleDefault )
						}
						onDeselect={ () =>
							setAttributes( { scale: siteScaleDefault } )
						}
						resetAllFilter={ ( attrs ) => ( {
							...attrs,
							scale: siteScaleDefault,
						} ) }
						isShownByDefault
						panelId={ clientId }
					>
						<SelectControl
							__next40pxDefaultSize
							label={ __( 'Scale', 'gatherpress' ) }
							value={ scale ?? siteScaleDefault }
							options={ SCALE_OPTIONS.map( ( value ) => ( {
								label: SCALE_LABELS[ value ],
								value,
							} ) ) }
							onChange={ ( value ) =>
								setAttributes( { scale: value } )
							}
						/>
					</ToolsPanelItem>
				) }
			</InspectorControls>
			{ isStaticMode && (
				<BlockControls>
					<ToolbarGroup>
						<Dropdown
							popoverProps={ { placement: 'bottom-start' } }
							renderToggle={ ( { isOpen, onToggle } ) => (
								<ToolbarButton
									icon={ linkIcon }
									title={ __( 'Link', 'gatherpress' ) }
									onClick={ onToggle }
									aria-expanded={ isOpen }
									isActive={
										Boolean( href ) &&
										LINK_DESTINATION_NONE !==
											linkDestination
									}
								/>
							) }
							renderContent={ () => (
								<div
									style={ {
										padding: '16px',
										minWidth: '280px',
									} }
								>
									<SelectControl
										__next40pxDefaultSize
										label={ __( 'Link to', 'gatherpress' ) }
										value={
											linkDestination ||
											LINK_DESTINATION_NONE
										}
										options={ linkDestinationOptions }
										onChange={ ( value ) =>
											setAttributes( {
												linkDestination: value,
											} )
										}
									/>
									{ LINK_DESTINATION_CUSTOM ===
										linkDestination && (
										<TextControl
											label={ __(
												'Link URL',
												'gatherpress'
											) }
											value={ href || '' }
											onChange={ ( value ) =>
												setAttributes( {
													href: value,
												} )
											}
										/>
									) }
									{ LINK_DESTINATION_NONE !==
										linkDestination &&
										linkDestination && (
										<>
											<ToggleControl
												label={ __(
													'Open in new tab',
													'gatherpress'
												) }
												checked={
													'_blank' ===
														linkTarget
												}
												onChange={ ( checked ) =>
													setAttributes( {
														linkTarget:
																checked
																	? '_blank'
																	: '',
													} )
												}
											/>
											<TextControl
												label={ __(
													'Link rel',
													'gatherpress'
												) }
												help={ __(
													'Space-separated tokens, e.g. "nofollow sponsored". `noopener noreferrer` is added automatically when the link opens in a new tab.',
													'gatherpress'
												) }
												value={ rel || '' }
												onChange={ ( value ) =>
													setAttributes( {
														rel: value,
													} )
												}
											/>
										</>
									) }
								</div>
							) }
						/>
					</ToolbarGroup>
				</BlockControls>
			) }
			<div { ...blockProps }>
				<div className="block-editor-inner-blocks">
					{ isParentGuarded ? (
						previewContent
					) : (
						<ResizableBox
							// Width always comes from the container (and the
							// block's alignment), so the box only ever
							// resizes vertically — same model as core's
							// Cover block.
							size={ {
								width: 'auto',
								height: 0 < heightPx ? heightPx : 'auto',
							} }
							minHeight={ HEIGHT_MIN }
							maxHeight={ HEIGHT_MAX }
							enable={ {
								top: false,
								right: false,
								bottom: true,
								left: false,
								topRight: false,
								bottomRight: false,
								bottomLeft: false,
								topLeft: false,
							} }
							onResizeStop={ onResizeStop }
						>
							{ previewContent }
						</ResizableBox>
					) }
				</div>
			</div>
		</>
	);
};

export default Edit;
