/**
 * WordPress dependencies.
 */
import { __ } from '@wordpress/i18n';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import {
	PanelBody,
	RangeControl,
	SelectControl,
} from '@wordpress/components';
import { useSelect } from '@wordpress/data';

/**
 * Internal dependencies.
 */
import { isVenuePostType } from '../../helpers/venue';
import { getFromSettings } from '../../helpers/editor-settings';
import MapEmbed from '../../components/MapEmbed';

/**
 * Edit component for the Venue Map block.
 *
 * @since 1.0.0
 *
 * @param {Object}   props               - Component properties.
 * @param {Object}   props.attributes    - Block attributes.
 * @param {Function} props.setAttributes - Function to set block attributes.
 * @param {Object}   props.context       - Block context.
 *
 * @return {JSX.Element} The rendered React component.
 */
const Edit = ( { attributes, setAttributes, context } ) => {
	const { zoom, type, height, renderMode } = attributes;
	const blockProps = useBlockProps();

	// Determine the venue post ID and get venue info + static-map descriptors.
	const { isEditingThisVenue, venueInfoJson, staticMapDescriptors } = useSelect(
		( select ) => {
			const currentPostId = select( 'core/editor' )?.getCurrentPostId();
			const contextPostId = context?.postId || 0;

			// If we're editing a venue post directly and context doesn't provide a valid ID,
			// use the current post ID.
			const effectiveVenuePostId =
				contextPostId ||
				( isVenuePostType() ? currentPostId : 0 );

			if ( ! effectiveVenuePostId ) {
				return {
					isEditingThisVenue: false,
					venueInfoJson: '{}',
					staticMapDescriptors: {},
				};
			}

			const isEditing =
				currentPostId === effectiveVenuePostId &&
				isVenuePostType();

			if ( isEditing ) {
				// Read from core/editor store for the current post being edited.
				const meta =
					select( 'core/editor' )?.getEditedPostAttribute( 'meta' ) || {};
				return {
					isEditingThisVenue: true,
					venueInfoJson: meta?.gatherpress_venue_information || '{}',
					staticMapDescriptors:
						meta?.gatherpress_venue_static_map || {},
				};
			}

			// Read from core store for a different venue post.
			// Use context?.postType (the venue post type from BlockContextProvider),
			// not currentPostType (the event post type), to avoid requesting the wrong endpoint.
			const { getEditedEntityRecord } = select( 'core' );
			const venuePost = getEditedEntityRecord(
				'postType',
				context?.postType,
				effectiveVenuePostId
			);

			return {
				isEditingThisVenue: false,
				venueInfoJson: venuePost?.meta?.gatherpress_venue_information || '{}',
				staticMapDescriptors:
					venuePost?.meta?.gatherpress_venue_static_map || {},
			};
		},
		[ context?.postId, context?.postType ]
	);

	// For live preview when editing the venue, read lat/long from venue store.
	// The venue store is updated in real-time by VenueInformation.js.
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

	// Use venue store values for live preview when editing this venue.
	// The store is kept in sync with meta by VenueInformation.js.
	// When editing, the store is updated in real-time by geocoding.
	let latitude = venueInfo.latitude || '';
	let longitude = venueInfo.longitude || '';

	if ( isEditingThisVenue ) {
		// When editing the venue, always use store values for live preview.
		latitude = null !== storeLat && storeLat !== undefined ? String( storeLat ) : latitude;
		longitude = null !== storeLng && storeLng !== undefined ? String( storeLng ) : longitude;
	}

	// Map type is a Google Maps–only concept; only expose the selector when
	// the map platform is Google and we're rendering interactively on the
	// front-end. The OSM/Leaflet and static-image paths both ignore it.
	const showMapTypeControl =
		'interactive' === renderMode &&
		'google' === getFromSettings( 'mapPlatform' );

	// When the block is in static mode we show one of two previews in place of
	// the interactive MapEmbed: (a) the actual cached PNG for this venue+zoom
	// if it's been generated, or (b) a placeholder surface noting that the
	// image will be generated on the next save. The interactive MapEmbed only
	// renders when the user explicitly picked Interactive.
	const staticMapDescriptor =
		staticMapDescriptors?.[ String( zoom ) ] ||
		staticMapDescriptors?.[ zoom ];
	const staticMapUrl = staticMapDescriptor?.url || '';
	const isStaticMode = 'static' === renderMode;
	const showStaticImage = isStaticMode && '' !== staticMapUrl;
	const showStaticPlaceholder = isStaticMode && '' === staticMapUrl;

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
					<RangeControl
						label={ __( 'Height (px)', 'gatherpress' ) }
						value={ height }
						onChange={ ( value ) =>
							setAttributes( { height: value } )
						}
						min={ 100 }
						max={ 800 }
					/>
				</PanelBody>
			</InspectorControls>
			<div { ...blockProps }>
				<div className="block-editor-inner-blocks">
					{ showStaticImage && (
						<div
							className="gatherpress-venue-map gatherpress-venue-map--static"
							style={ { height: `${ height }px` } }
						>
							<img
								className="gatherpress-venue-map__image"
								src={ staticMapUrl }
								alt={ fullAddress
									? `${ __( 'Map of', 'gatherpress' ) } ${ fullAddress }`
									: __( 'Venue map', 'gatherpress' ) }
							/>
						</div>
					) }
					{ showStaticPlaceholder && (
						<div
							className="gatherpress-venue-map gatherpress-venue-map--static"
							style={ { height: `${ height }px` } }
						>
							<div className="gatherpress-venue-map__placeholder">
								{ __(
									'Static map preview appears after venue is saved.',
									'gatherpress'
								) }
							</div>
						</div>
					) }
					{ ! isStaticMode && (
						<MapEmbed
							location={ fullAddress }
							latitude={ latitude }
							longitude={ longitude }
							zoom={ zoom }
							type={ type }
							height={ height }
						/>
					) }
				</div>
			</div>
		</>
	);
};

export default Edit;
