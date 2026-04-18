/**
 * WordPress dependencies.
 */
import { useSelect, useDispatch } from '@wordpress/data';
import { useCallback, useEffect, useRef } from '@wordpress/element';
import { useDebounce } from '@wordpress/compose';

/**
 * Internal dependencies.
 */
import { geocodeAddress, GEOCODE_LOCK_NAME } from '../../../helpers/geocoding';

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
 * @param {boolean}  [enabled=true]   - When false, the hook skips firing geocode requests. Callers pass false in contexts where another component (e.g. the VenueInformation sidebar panel) already geocodes the same address, to avoid double requests.
 * @return {Object} Geocoding state and handlers.
 */
export function useGeocoding( fieldType, fieldValue, updateVenueField, enabled = true ) {
	// Get dispatch functions for venue store.
	const { updateVenueLatitude, updateVenueLongitude } =
		useDispatch( 'gatherpress/venue' );

	// Dispatches used to block Save while geocoding is pending — prevents
	// the "saved new address with stale lat/long" race that otherwise bakes
	// a wrong-location static map until the next save.
	const { lockPostSaving, unlockPostSaving } =
		useDispatch( 'core/editor' );

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

		try {
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
		} finally {
			unlockPostSaving( GEOCODE_LOCK_NAME );
		}
	}, [
		fieldType,
		mapCustomLatLong,
		updateVenueLatitude,
		updateVenueLongitude,
		updateVenueField,
		unlockPostSaving,
	] );

	// Longer debounce than autocomplete: geocoding is not user-visible during
	// typing (only the map preview and stored lat/long depend on it) so we let
	// the address settle for a second before hitting the upstream Photon API.
	const debouncedGeocode = useDebounce( handleGeocode, 1000 );

	// Trigger geocoding when address field value changes.
	useEffect( () => {
		if ( enabled && 'address' === fieldType ) {
			lockPostSaving( GEOCODE_LOCK_NAME );
			debouncedGeocode();

			return () => {
				unlockPostSaving( GEOCODE_LOCK_NAME );
			};
		}

		return undefined;
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ fieldValue, fieldType, enabled ] );

	return {
		mapCustomLatLong,
	};
}
