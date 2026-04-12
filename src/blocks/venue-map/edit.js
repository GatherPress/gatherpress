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
import { CPT_VENUE } from '../../helpers/namespace';
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
	const { zoom, type, height } = attributes;
	const blockProps = useBlockProps();

	// Determine the venue post ID and get venue info.
	const { isEditingThisVenue, venueInfoJson } = useSelect(
		( select ) => {
			const currentPostId = select( 'core/editor' )?.getCurrentPostId();
			const currentPostType = select( 'core/editor' )?.getCurrentPostType();
			const contextPostId = context?.postId || 0;

			// If we're editing a venue post directly and context doesn't provide a valid ID,
			// use the current post ID.
			const effectiveVenuePostId =
				contextPostId ||
				( currentPostType === CPT_VENUE ? currentPostId : 0 );

			if ( ! effectiveVenuePostId ) {
				return {
					isEditingThisVenue: false,
					venueInfoJson: '{}',
				};
			}

			const isEditing =
				currentPostId === effectiveVenuePostId &&
				currentPostType === CPT_VENUE;

			if ( isEditing ) {
				// Read from core/editor store for the current post being edited.
				const meta =
					select( 'core/editor' )?.getEditedPostAttribute( 'meta' ) || {};
				return {
					isEditingThisVenue: true,
					venueInfoJson: meta?.gatherpress_venue_information || '{}',
				};
			}

			// Read from core store for a different venue post.
			const { getEditedEntityRecord } = select( 'core' );
			const venuePost = getEditedEntityRecord(
				'postType',
				CPT_VENUE,
				effectiveVenuePostId
			);

			return {
				isEditingThisVenue: false,
				venueInfoJson: venuePost?.meta?.gatherpress_venue_information || '{}',
			};
		},
		[ context?.postId ]
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

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Map settings', 'gatherpress' ) }>
					<RangeControl
						label={ __( 'Zoom level', 'gatherpress' ) }
						value={ zoom }
						onChange={ ( value ) =>
							setAttributes( { zoom: value } )
						}
						min={ 1 }
						max={ 20 }
					/>
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
						onChange={ ( value ) => setAttributes( { type: value } ) }
					/>
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
					<MapEmbed
						location={ fullAddress }
						latitude={ latitude }
						longitude={ longitude }
						zoom={ zoom }
						type={ type }
						height={ height }
					/>
				</div>
			</div>
		</>
	);
};

export default Edit;
