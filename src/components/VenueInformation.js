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
 * in post meta as JSON and updated using the `editPost` method from the editor package.
 *
 * @since 1.0.0
 *
 * @return {JSX.Element} The rendered React component.
 */
const VenueInformation = () => {
	const editPost = useDispatch( 'core/editor' ).editPost;
	// eslint-disable-next-line react-hooks/exhaustive-deps
	const updateVenueMeta = ( metaData ) => {
		const payload = JSON.stringify( {
			...venueInformationMetaData,
			...metaData,
		} );
		const meta = { gatherpress_venue_information: payload };

		editPost( { meta } );
	};

	const { updateVenueLatitude, updateVenueLongitude } =
		useDispatch( 'gatherpress/venue' );

	const { mapCustomLatLong } = useSelect(
		( select ) => ( {
			mapCustomLatLong: select( 'gatherpress/venue' ).getMapCustomLatLong(),
		} ),
		[],
	);

	let venueInformationMetaData = useSelect(
		( select ) =>
			select( 'core/editor' ).getEditedPostAttribute( 'meta' )
				.gatherpress_venue_information,
	);

	if ( venueInformationMetaData ) {
		venueInformationMetaData = JSON.parse( venueInformationMetaData );
	} else {
		venueInformationMetaData = {};
	}

	const [ fullAddress, setFullAddress ] = useState(
		venueInformationMetaData.fullAddress ?? '',
	);
	const [ phoneNumber, setPhoneNumber ] = useState(
		venueInformationMetaData.phoneNumber ?? '',
	);
	const [ website, setWebsite ] = useState(
		venueInformationMetaData.website ?? '',
	);

	Listener( { setFullAddress, setPhoneNumber, setWebsite } );

	const updateVenueMetaRef = useRef( updateVenueMeta );

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
					updateVenueMetaRef.current( {
						latitude: lat,
						longitude: lng,
					} );
				}
			} );
	}, [
		fullAddress,
		mapCustomLatLong,
		updateVenueLatitude,
		updateVenueLongitude,
	] );

	const debouncedGetData = useDebounce( getData, 300 );

	useEffect( () => {
		updateVenueMetaRef.current = updateVenueMeta;
	}, [ updateVenueMeta ] );

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
					updateVenueMeta( { fullAddress: value } );
				} }
			/>
			<TextControl
				label={ __( 'Phone Number', 'gatherpress' ) }
				value={ phoneNumber }
				onChange={ ( value ) => {
					Broadcaster( { setPhoneNumber: value } );
					updateVenueMeta( { phoneNumber: value } );
				} }
			/>
			<TextControl
				label={ __( 'Website', 'gatherpress' ) }
				value={ website }
				type="url"
				onChange={ ( value ) => {
					Broadcaster( { setWebsite: value } );
					updateVenueMeta( { website: value } );
				} }
			/>
		</>
	);
};

export default VenueInformation;
