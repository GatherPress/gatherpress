/**
 * WordPress dependencies.
 */
import { TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useDispatch, useSelect } from '@wordpress/data';
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
		( select ) => ( {
			mapCustomLatLong: select( 'gatherpress/venue' ).getMapCustomLatLong(),
			venueMeta: select( 'core/editor' ).getEditedPostAttribute( 'meta' ) || {},
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

	// Helper to update JSON field.
	const updateVenueField = useCallback(
		( jsonField, value ) => {
			// Get the current meta value from the editor store directly.
			const currentMeta = window.wp.data.select( 'core/editor' ).getEditedPostAttribute( 'meta' ) || {};
			const currentInfo = parseVenueInfo( currentMeta );
			const updatedInfo = {
				...currentInfo,
				[ jsonField ]: value,
			};

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

				// Update the JSON meta field with empty lat/long.
				const currentMeta = window.wp.data.select( 'core/editor' ).getEditedPostAttribute( 'meta' ) || {};
				const currentInfo = parseVenueInfo( currentMeta );
				const updatedInfo = {
					...currentInfo,
					latitude: '',
					longitude: '',
				};

				editPost( {
					meta: {
						gatherpress_venue_information: JSON.stringify( updatedInfo ),
					},
				} );
			}
			return;
		}

		const { latitude, longitude } = await geocodeAddress( address );

		if ( ! mapCustomLatLong ) {
			updateVenueLatitude( latitude || null );
			updateVenueLongitude( longitude || null );

			// Update the JSON meta field with lat/long.
			const currentMeta = window.wp.data.select( 'core/editor' ).getEditedPostAttribute( 'meta' ) || {};
			const currentInfo = parseVenueInfo( currentMeta );
			const updatedInfo = {
				...currentInfo,
				latitude: latitude || '',
				longitude: longitude || '',
			};

			editPost( {
				meta: {
					gatherpress_venue_information: JSON.stringify( updatedInfo ),
				},
			} );
		}
	}, [
		mapCustomLatLong,
		updateVenueLatitude,
		updateVenueLongitude,
		editPost,
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
