/**
 * WordPress dependencies.
 */
import { __ } from '@wordpress/i18n';
import {
	BlockControls,
	InspectorControls,
	useBlockProps,
} from '@wordpress/block-editor';
import {
	Dropdown,
	Flex,
	FlexItem,
	PanelBody,
	RangeControl,
	ResizableBox,
	SelectControl,
	TextControl,
	ToggleControl,
	ToolbarButton,
	ToolbarGroup,
} from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { useEffect } from '@wordpress/element';
import { Icon, link as linkIcon, mapMarker } from '@wordpress/icons';

/**
 * Internal dependencies.
 */
import { isVenuePostType } from '../../helpers/venue';
import { isInFSETemplate } from '../../helpers/editor';
import { getFromSettings } from '../../helpers/editor-settings';
import MapEmbed from '../../components/MapEmbed';
import {
	useSharedBlockGuardState,
	generateBlockGuardStateKey,
} from '../../supports/block-guard';
import {
	RegenerateMapButton,
	parseAspectRatio,
	resolveDimensions,
} from './helpers';

const WIDTH_MIN = 100;
const WIDTH_MAX = 4000;
const HEIGHT_MIN = 100;
const HEIGHT_MAX = 4000;
const DEFAULT_HEIGHT = 300;

// Presets for the aspect-ratio dropdown. The first entry matches the
// server-side `Venue_Map::DEFAULT_ASPECT_RATIO` (2/1) so freshly inserted
// blocks keep the behavior that shipped in the first round of static maps.
const ASPECT_RATIO_PRESETS = [
	{ label: __( '2:1 (landscape)', 'gatherpress' ), value: '2/1' },
	{ label: __( '16:9 (wide)', 'gatherpress' ), value: '16/9' },
	{ label: __( '3:2 (classic)', 'gatherpress' ), value: '3/2' },
	{ label: __( '4:3 (standard)', 'gatherpress' ), value: '4/3' },
	{ label: __( '1:1 (square)', 'gatherpress' ), value: '1/1' },
	{ label: __( 'Custom', 'gatherpress' ), value: 'custom' },
];

const LINK_DESTINATION_NONE = 'none';
const LINK_DESTINATION_OPENSTREETMAP = 'openstreetmap';
const LINK_DESTINATION_GOOGLE = 'google';
const LINK_DESTINATION_CUSTOM = 'custom';

/**
 * Build the canonical external-map URL for a preset destination.
 *
 * @param {string} destination One of the LINK_DESTINATION_* constants.
 * @param {string} latitude    Venue latitude.
 * @param {string} longitude   Venue longitude.
 * @param {number} zoom        Desired zoom level.
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
 * @since 1.0.0
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
		width,
		height,
		aspectRatio,
		renderMode,
		align,
		href,
		linkDestination,
		linkTarget,
		rel,
	} = attributes;
	const blockProps = useBlockProps();

	// Determine the venue post ID and get venue info + static-map descriptors.
	// `savedVenueInfoJson` reflects what's persisted server-side — compared
	// against the edited JSON below to detect unsaved address/coord changes
	// and force the placeholder until the next save regenerates the PNG.
	const {
		isEditingThisVenue,
		venueInfoJson,
		savedVenueInfoJson,
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
					venueInfoJson: '{}',
					savedVenueInfoJson: '{}',
					staticMapDescriptors: {},
					venuePostId: 0,
					venuePostType: '',
				};
			}

			const isEditing =
				currentPostId === effectiveVenuePostId && isVenuePostType();

			if ( isEditing ) {
				const meta =
					select( 'core/editor' )?.getEditedPostAttribute( 'meta' ) || {};
				const savedPost =
					select( 'core/editor' )?.getCurrentPost() || {};
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
					venueInfoJson: meta?.gatherpress_venue_information || '{}',
					savedVenueInfoJson:
						savedPost?.meta?.gatherpress_venue_information || '{}',
					staticMapDescriptors:
						editedVenuePost?.meta?.gatherpress_venue_static_map ||
						meta?.gatherpress_venue_static_map ||
						{},
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

			const venueInfo =
				venuePost?.meta?.gatherpress_venue_information || '{}';

			return {
				isEditingThisVenue: false,
				venueInfoJson: venueInfo,
				savedVenueInfoJson: venueInfo,
				staticMapDescriptors:
					venuePost?.meta?.gatherpress_venue_static_map || {},
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

	// Parse venue information from JSON field.
	let venueInfo = {};
	try {
		venueInfo = JSON.parse( venueInfoJson );
	} catch ( e ) {
		venueInfo = {};
	}

	const fullAddress = venueInfo.fullAddress || '';

	let latitude = venueInfo.latitude || '';
	let longitude = venueInfo.longitude || '';

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
	const showMapTypeControl =
		'interactive' === renderMode && 'google' === mapPlatform;

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

	let savedVenueInfo = {};
	try {
		savedVenueInfo = JSON.parse( savedVenueInfoJson );
	} catch ( e ) {
		savedVenueInfo = {};
	}
	const hasUnsavedMapInputs =
		isEditingThisVenue &&
		( ( venueInfo.fullAddress || '' ) !==
			( savedVenueInfo.fullAddress || '' ) ||
			( venueInfo.latitude || '' ) !==
				( savedVenueInfo.latitude || '' ) ||
			( venueInfo.longitude || '' ) !==
				( savedVenueInfo.longitude || '' ) );

	// Compute the effective pixel dimensions (matching what the server
	// will compose) so the cached-PNG lookup hits the right combo key.
	const { width: effectiveWidth, height: effectiveHeight } =
		resolveDimensions( {
			width,
			height,
			aspectRatio,
			defaultHeight: DEFAULT_HEIGHT,
		} );

	const comboKey = `${ zoom }x${ effectiveWidth }x${ effectiveHeight }`;
	const staticMapDescriptor = staticMapDescriptors?.[ comboKey ];
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
			return parents[ parents.length - 1 ];
		},
		[ clientId ]
	);
	const parentGuardKey = parentVenueClientId
		? generateBlockGuardStateKey( 'gatherpress/venue', parentVenueClientId )
		: 'gatherpress/venue';
	const [ isParentGuarded ] = useSharedBlockGuardState( parentGuardKey );

	// Model mirrors core/image:
	//   - width + aspectRatio are the authoritative shape inputs
	//   - height is derived at render time from width × ratio; stored as
	//     0 (auto) while a preset ratio is active
	//   - typing an explicit height clears aspectRatio, switching the
	//     block to "custom" mode where width and height are independent
	//   - dragging the grips adjusts width only unless the user already
	//     dropped into custom mode
	const handleWidthInput = ( raw ) => {
		const parsed = parseInt( raw, 10 );
		setAttributes( {
			width: Number.isNaN( parsed ) ? 0 : parsed,
		} );
	};
	const handleHeightInput = ( raw ) => {
		const parsed = parseInt( raw, 10 );
		setAttributes( {
			height: Number.isNaN( parsed ) ? 0 : parsed,
			aspectRatio: '',
		} );
	};

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

	// Resize-handle visibility follows block alignment. Left-aligned blocks
	// anchor to the left (handles on right + bottom + bottom-right); right-
	// aligned mirror that; wide/full let the container own the width.
	const showRightHandle =
		'right' !== align && 'wide' !== align && 'full' !== align;
	const showLeftHandle = 'right' === align;
	const showBottomHandle = 'wide' !== align && 'full' !== align;

	const lockRatio = isCustomAspectRatio
		? false
		: parseAspectRatio( aspectRatio ) || false;

	const onResizeStop = ( event, direction, elt, delta ) => {
		// ResizableBox hands us the pixel delta from resize start; combine
		// with the effective dimensions we rendered at to get the new value.
		const newWidth = Math.max(
			WIDTH_MIN,
			Math.min( WIDTH_MAX, Math.round( effectiveWidth + delta.width ) )
		);
		const changes = { width: newWidth };

		// In custom mode there's no ratio lock, so both edges moved
		// independently and we persist both. In preset mode height stays
		// auto so the cached aspect ratio keeps driving the render.
		if ( isCustomAspectRatio ) {
			changes.height = Math.max(
				HEIGHT_MIN,
				Math.min(
					HEIGHT_MAX,
					Math.round( effectiveHeight + delta.height )
				)
			);
		}

		setAttributes( changes );
	};

	// When the block is aligned wide or full, the alignment owns the
	// horizontal space — skip the explicit pixel width so `.alignwide`
	// / `.alignfull` can drive the layout. Height keeps its inline stamp;
	// aspect-ratio applies whenever a dimension is auto OR the alignment
	// is wide/full so the shape tracks the container as it fills.
	const isWideOrFull = 'wide' === align || 'full' === align;
	let wrapperWidth;
	if ( ! isWideOrFull ) {
		wrapperWidth = 0 < width ? `${ width }px` : '100%';
	}
	const wrapperStyle = {
		width: wrapperWidth,
		height: 0 < height ? `${ height }px` : undefined,
		aspectRatio:
			isWideOrFull || 0 === width || 0 === height
				? aspectRatio || '2/1'
				: undefined,
	};

	const previewContent = (
		<>
			{ showStaticImage && (
				<div
					className="gatherpress-venue-map gatherpress-venue-map--static"
					style={ wrapperStyle }
				>
					<img
						className="gatherpress-venue-map__image"
						src={ staticMapUrl }
						alt={
							fullAddress
								? `${ __( 'Map of', 'gatherpress' ) } ${ fullAddress }`
								: __( 'Venue map', 'gatherpress' )
						}
					/>
				</div>
			) }
			{ showStaticPlaceholder && (
				<div
					className="gatherpress-venue-map gatherpress-venue-map--static"
					style={ wrapperStyle }
				>
					<div className="gatherpress-venue-map__placeholder">
						{ ! fullAddress && isInFSETemplate() && (
							<Icon
								icon={ mapMarker }
								size={ 48 }
								className="gatherpress-venue-map__placeholder-icon"
							/>
						) }
						{ ! fullAddress &&
							! isInFSETemplate() &&
							__(
								'Add an address to generate the map.',
								'gatherpress'
							) }
						{ fullAddress &&
							hasUnsavedMapInputs &&
							__(
								'Save the venue first.',
								'gatherpress'
							) }
						{ fullAddress &&
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
									width={ width }
									height={ height }
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
					style={ wrapperStyle }
				>
					<MapEmbed
						location={ fullAddress }
						latitude={ latitude }
						longitude={ longitude }
						zoom={ zoom }
						type={ type }
						height={ effectiveHeight }
					/>
				</div>
			) }
		</>
	);

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Map settings', 'gatherpress' ) }>
					<SelectControl
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
							label={ __( 'Map type', 'gatherpress' ) }
							value={ type }
							options={ [
								{
									label: __( 'Roadmap', 'gatherpress' ),
									value: 'roadmap',
								},
								{
									label: __( 'Satellite', 'gatherpress' ),
									value: 'satellite',
								},
								{
									label: __( 'Hybrid', 'gatherpress' ),
									value: 'hybrid',
								},
								{
									label: __( 'Terrain', 'gatherpress' ),
									value: 'terrain',
								},
							] }
							onChange={ ( value ) =>
								setAttributes( { type: value } )
							}
						/>
					) }
					<Flex gap={ 2 } align="flex-end">
						<FlexItem isBlock>
							<TextControl
								label={ __( 'Width', 'gatherpress' ) }
								type="number"
								placeholder={ __( 'Auto', 'gatherpress' ) }
								value={ 0 < width ? String( width ) : '' }
								onChange={ handleWidthInput }
							/>
						</FlexItem>
						<FlexItem isBlock>
							<TextControl
								label={ __( 'Height', 'gatherpress' ) }
								type="number"
								placeholder={ __( 'Auto', 'gatherpress' ) }
								value={ 0 < height ? String( height ) : '' }
								onChange={ handleHeightInput }
							/>
						</FlexItem>
					</Flex>
					<SelectControl
						label={ __( 'Aspect ratio', 'gatherpress' ) }
						value={
							isCustomAspectRatio ? 'custom' : aspectRatio
						}
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
							setAttributes( {
								aspectRatio: value,
								height: 0,
							} );
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
					{ isStaticMode && showStaticImage && 0 < venuePostId && (
						<RegenerateMapButton
							venuePostId={ venuePostId }
							venuePostType={ venuePostType }
							zoom={ zoom }
							width={ width }
							height={ height }
							aspectRatio={ aspectRatio }
							disabled={
								! fullAddress || hasUnsavedMapInputs
							}
						/>
					) }
				</PanelBody>
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
							size={ {
								width:
									! isWideOrFull && 0 < width
										? width
										: 'auto',
								height: 0 < height ? height : 'auto',
							} }
							minWidth={ WIDTH_MIN }
							maxWidth={ WIDTH_MAX }
							minHeight={ HEIGHT_MIN }
							maxHeight={ HEIGHT_MAX }
							lockAspectRatio={ lockRatio }
							enable={ {
								top: false,
								right: showRightHandle,
								bottom: showBottomHandle,
								left: showLeftHandle,
								topRight: false,
								bottomRight:
									showRightHandle && showBottomHandle,
								bottomLeft:
									showLeftHandle && showBottomHandle,
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
