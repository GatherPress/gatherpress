/**
 * WordPress dependencies.
 */
import { TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { select, useDispatch, useSelect } from '@wordpress/data';
import { useEffect, useCallback, useRef } from '@wordpress/element';
import { useDebounce } from '@wordpress/compose';

/**
 * Internal dependencies.
 */
import { geocodeAddress } from '../helpers/geocoding';

/**
 * Parse venue information from JSON meta field.
 *
 * @param {Object} venueMeta - The venue meta object.
 * @return {Object} Parsed venue information.
 */
const parseVenueInfo = ( venueMeta ) => {
	try {
		const info = JSON.parse(
			venueMeta.gatherpress_venue_information || '{}',
		);
		return {
			fullAddress: info.fullAddress || '',
			phoneNumber: info.phoneNumber || '',
			website: info.website || '',
			latitude: info.latitude || '',
			longitude: info.longitude || '',
		};
	} catch ( e ) {
		return {
			fullAddress: '',
			phoneNumber: '',
			website: '',
			latitude: '',
			longitude: '',
		};
	}
};

/**
 * Get the current venue info merged with new fields.
 *
 * Reads the current meta from the editor store, parses it,
 * and merges with the provided fields.
 *
 * @param {Object} fields - Object of field names and values to merge.
 * @return {Object} The updated venue info object.
 */
const getUpdatedVenueInfo = ( fields ) => {
	const currentMeta =
		select( 'core/editor' ).getEditedPostAttribute( 'meta' ) || {};
	const currentInfo = parseVenueInfo( currentMeta );
	return {
		...currentInfo,
		...fields,
	};
};

/**
 * VenueInformation component for GatherPress.
 *
 * This component allows users to input and update venue information, including full address,
 * phone number, and website. It uses the `TextControl` component from the Gutenberg editor
 * package to provide input fields for each type of information. The entered data is stored
 * in a single JSON meta field.
 *
 * @since 1.0.0
 *
 * @return {JSX.Element} The rendered React component.
 */
const VenueInformation = () => {
	const { editPost } = useDispatch( 'core/editor' );

	const { updateVenueLatitude, updateVenueLongitude } =
		useDispatch( 'gatherpress/venue' );

	const { mapCustomLatLong, venueMeta } = useSelect(
		( selectData ) => ( {
			mapCustomLatLong: selectData( 'gatherpress/venue' ).getMapCustomLatLong(),
			venueMeta: selectData( 'core/editor' ).getEditedPostAttribute( 'meta' ) || {},
		} ),
		[],
	);

	// Use meta as source of truth - no local state needed.
	// editPost updates editor state, which is saved when user clicks Update.
	const venueInfo = parseVenueInfo( venueMeta );
	const fullAddress = venueInfo.fullAddress;
	const phoneNumber = venueInfo.phoneNumber;
	const website = venueInfo.website;
	const initialLat = venueInfo.latitude;
	const initialLng = venueInfo.longitude;

	// Use ref to track current address for geocoding without recreating callback.
	const fullAddressRef = useRef( fullAddress );

	fullAddressRef.current = fullAddress;

	// Initialize venue store with saved lat/long values on mount only.
	// After initialization, getData manages the store directly for live updates.
	useEffect( () => {
		if ( initialLat || initialLng ) {
			updateVenueLatitude( initialLat );
			updateVenueLongitude( initialLng );
		}
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [] ); // Run once on mount only.

	// Helper to update venue JSON meta field.
	// Accepts either (fieldName, value) or (fieldsObject) for multiple fields.
	const updateVenueField = useCallback(
		( fieldNameOrFields, value ) => {
			const fields =
				'object' === typeof fieldNameOrFields
					? fieldNameOrFields
					: { [ fieldNameOrFields ]: value };

			const updatedInfo = getUpdatedVenueInfo( fields );

			editPost( {
				meta: {
					gatherpress_venue_information: JSON.stringify( updatedInfo ),
				},
			} );
		},
		[ editPost ],
	);

	const getData = useCallback( async () => {
		// Read current address from ref to avoid recreating this callback.
		const address = fullAddressRef.current;

		// If address is empty, clear lat/long.
		if ( ! address ) {
			if ( ! mapCustomLatLong ) {
				updateVenueLatitude( '' );
				updateVenueLongitude( '' );
				updateVenueField( { latitude: '', longitude: '' } );
			}
			return;
		}

		const { latitude, longitude } = await geocodeAddress( address );

		if ( ! mapCustomLatLong ) {
			updateVenueLatitude( latitude || null );
			updateVenueLongitude( longitude || null );
			updateVenueField( {
				latitude: latitude || '',
				longitude: longitude || '',
			} );
		}
	}, [
		mapCustomLatLong,
		updateVenueLatitude,
		updateVenueLongitude,
		updateVenueField,
	] ); // fullAddress removed - read from ref instead.

	const debouncedGetData = useDebounce( getData, 300 );

	useEffect( () => {
		debouncedGetData();
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ fullAddress ] ); // Only depend on fullAddress, not debouncedGetData.

	return (
		<>
			<TextControl
				label={ __( 'Full Address', 'gatherpress' ) }
				value={ fullAddress }
				onChange={ ( value ) => {
					updateVenueField( 'fullAddress', value );
				} }
			/>
			<TextControl
				label={ __( 'Phone Number', 'gatherpress' ) }
				value={ phoneNumber }
				onChange={ ( value ) => {
					updateVenueField( 'phoneNumber', value );
				} }
			/>
			<TextControl
				label={ __( 'Website', 'gatherpress' ) }
				value={ website }
				type="url"
				onChange={ ( value ) => {
					updateVenueField( 'website', value );
				} }
			/>
		</>
	);
};

export default VenueInformation;
