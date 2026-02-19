/**
 * WordPress dependencies.
 */
import { useSelect, useDispatch } from '@wordpress/data';
import { useCallback, useEffect, useRef } from '@wordpress/element';
import { useDebounce } from '@wordpress/compose';

/**
 * Internal dependencies.
 */
import { geocodeAddress } from '../../../helpers/geocoding';

/**
 * Custom hook for geocoding address fields.
 *
 * Handles geocoding of address input with debouncing,
 * updates the venue store for live map preview,
 * and saves lat/long to venue meta.
 *
 * @since 1.0.0
 *
 * @param {string}   fieldType        - The type of field (only 'address' triggers geocoding).
 * @param {string}   fieldValue       - The current field value.
 * @param {Function} updateVenueField - Function to update venue meta fields.
 * @return {Object} Geocoding state and handlers.
 */
export function useGeocoding( fieldType, fieldValue, updateVenueField ) {
	// Get dispatch functions for venue store.
	const { updateVenueLatitude, updateVenueLongitude } =
		useDispatch( 'gatherpress/venue' );

	// Get mapCustomLatLong setting from venue store.
	const { mapCustomLatLong } = useSelect(
		( selectData ) => ( {
			mapCustomLatLong:
				selectData( 'gatherpress/venue' ).getMapCustomLatLong(),
		} ),
		[]
	);

	// Track address for geocoding.
	const addressRef = useRef( 'address' === fieldType ? fieldValue : '' );
	if ( 'address' === fieldType ) {
		addressRef.current = fieldValue;
	}

	// Geocode address field changes.
	const handleGeocode = useCallback( async () => {
		const address = addressRef.current;

		if ( 'address' !== fieldType ) {
			return;
		}

		// If address is empty, clear lat/long.
		if ( ! address ) {
			if ( ! mapCustomLatLong ) {
				// Clear venue store for live preview.
				updateVenueLatitude( '' );
				updateVenueLongitude( '' );

				// Clear meta for the venue post.
				updateVenueField( { latitude: '', longitude: '' } );
			}
			return;
		}

		const { latitude, longitude } = await geocodeAddress( address );

		if ( ! mapCustomLatLong ) {
			// Update venue store for live preview.
			updateVenueLatitude( latitude || null );
			updateVenueLongitude( longitude || null );

			// Update meta for the venue post.
			updateVenueField( {
				latitude: latitude || '',
				longitude: longitude || '',
			} );
		}
	}, [
		fieldType,
		mapCustomLatLong,
		updateVenueLatitude,
		updateVenueLongitude,
		updateVenueField,
	] );

	const debouncedGeocode = useDebounce( handleGeocode, 300 );

	// Trigger geocoding when address field value changes.
	useEffect( () => {
		if ( 'address' === fieldType ) {
			debouncedGeocode();
		}
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ fieldValue, fieldType ] );

	return {
		mapCustomLatLong,
	};
}
