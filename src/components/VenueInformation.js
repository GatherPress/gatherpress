/**
 * WordPress dependencies.
 */
import { TextControl } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { useDispatch, useSelect } from '@wordpress/data';
import { useState, useEffect, useCallback, useRef } from '@wordpress/element';
import { useDebounce } from '@wordpress/compose';

/**
 * Internal dependencies.
 */
import { Broadcaster, Listener } from '../helpers/broadcasting';

/**
 * VenueInformation component for GatherPress.
 *
 * This component allows users to input and update venue information, including full address,
 * phone number, and website. It uses the `TextControl` component from the Gutenberg editor
 * package to provide input fields for each type of information. The entered data is stored
 * in individual post meta fields.
 *
 * @since 1.0.0
 *
 * @return {JSX.Element} The rendered React component.
 */
const VenueInformation = () => {
	const editPost = useDispatch( 'core/editor' ).editPost;

	const { updateVenueLatitude, updateVenueLongitude } =
		useDispatch( 'gatherpress/venue' );

	const { mapCustomLatLong } = useSelect(
		( select ) => ( {
			mapCustomLatLong: select( 'gatherpress/venue' ).getMapCustomLatLong(),
		} ),
		[],
	);

	const venueMeta = useSelect(
		( select ) =>
			select( 'core/editor' ).getEditedPostAttribute( 'meta' ) || {},
	);

	const [ fullAddress, setFullAddress ] = useState(
		venueMeta.gatherpress_venue_address ?? '',
	);
	const [ phoneNumber, setPhoneNumber ] = useState(
		venueMeta.gatherpress_venue_phone ?? '',
	);
	const [ website, setWebsite ] = useState(
		venueMeta.gatherpress_venue_website ?? '',
	);

	// Sync local state when meta changes (e.g., from venue-detail blocks).
	useEffect( () => {
		if ( venueMeta.gatherpress_venue_address !== fullAddress ) {
			setFullAddress( venueMeta.gatherpress_venue_address ?? '' );
		}
		if ( venueMeta.gatherpress_venue_phone !== phoneNumber ) {
			setPhoneNumber( venueMeta.gatherpress_venue_phone ?? '' );
		}
		if ( venueMeta.gatherpress_venue_website !== website ) {
			setWebsite( venueMeta.gatherpress_venue_website ?? '' );
		}
	}, [ venueMeta, fullAddress, phoneNumber, website ] );

	Listener( { setFullAddress, setPhoneNumber, setWebsite } );

	const getData = useCallback( () => {
		let lat = null;
		let lng = null;

		fetch(
			`https://nominatim.openstreetmap.org/search?q=${ fullAddress }&format=geojson`,
		)
			.then( ( response ) => {
				if ( ! response.ok ) {
					throw new Error(
						sprintf(
							/* translators: %s: Error message */
							__( 'Network response was not ok %s', 'gatherpress' ),
							response.statusText,
						),
					);
				}
				return response.json();
			} )
			.then( ( data ) => {
				if ( 0 < data.features.length ) {
					lat = data.features[ 0 ].geometry.coordinates[ 1 ];
					lng = data.features[ 0 ].geometry.coordinates[ 0 ];
				}
				if ( ! mapCustomLatLong ) {
					updateVenueLatitude( lat );
					updateVenueLongitude( lng );
					editPost( {
						meta: {
							gatherpress_venue_latitude: String( lat ),
							gatherpress_venue_longitude: String( lng ),
						},
					} );
				}
			} );
	}, [
		fullAddress,
		mapCustomLatLong,
		updateVenueLatitude,
		updateVenueLongitude,
		editPost,
	] );

	const debouncedGetData = useDebounce( getData, 300 );

	useEffect( () => {
		debouncedGetData();
	}, [ fullAddress, debouncedGetData ] );

	return (
		<>
			<TextControl
				label={ __( 'Full Address', 'gatherpress' ) }
				value={ fullAddress }
				onChange={ ( value ) => {
					Broadcaster( { setFullAddress: value } );
					editPost( {
						meta: {
							gatherpress_venue_address: value,
						},
					} );
				} }
			/>
			<TextControl
				label={ __( 'Phone Number', 'gatherpress' ) }
				value={ phoneNumber }
				onChange={ ( value ) => {
					Broadcaster( { setPhoneNumber: value } );
					editPost( {
						meta: {
							gatherpress_venue_phone: value,
						},
					} );
				} }
			/>
			<TextControl
				label={ __( 'Website', 'gatherpress' ) }
				value={ website }
				type="url"
				onChange={ ( value ) => {
					Broadcaster( { setWebsite: value } );
					editPost( {
						meta: {
							gatherpress_venue_website: value,
						},
					} );
				} }
			/>
		</>
	);
};

export default VenueInformation;
