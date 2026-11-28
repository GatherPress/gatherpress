/**
 * WordPress dependencies
 */
import { useDispatch } from '@wordpress/data';
import { useCallback, useEffect, useRef } from '@wordpress/element';
import { useDebounce } from '@wordpress/compose';

/**
 * Internal dependencies
 */
import {
	geocodeAddress,
	geocodeFailedMessage,
	GEOCODE_NOTICE_ID,
} from '../../../helpers/geocoding';

/**
 * Custom hook for geocoding address fields.
 *
 * Handles geocoding of address input with debouncing,
 * updates the venue store for live map preview,
 * and saves lat/long to venue meta.
 *
 * Note: this hook only fires when editing a venue-detail block whose venue
 * is *not* the current post being edited (e.g. a venue block embedded in an
 * event editor). It deliberately does **not** lock the current post's save —
 * the geocode writes to the venue entity, not the host post, so locking the
 * host post's Save button would block legitimate event edits while the venue
 * geocodes in the background. The matching `VenueInformation` sidebar panel
 * locks saving when the venue itself is the current post, which is the only
 * context where the lock-and-unlock pairing is correct.
 *
 * @since 0.34.0
 *
 * @param {string}   fieldType        - The type of field (only 'address' triggers geocoding).
 * @param {string}   fieldValue       - The current field value.
 * @param {Function} updateVenueField - Function to update venue meta fields.
 * @param {boolean}  [enabled=true]   - When false, the hook skips firing geocode requests. Callers pass false in contexts where another component (e.g. the VenueInformation sidebar panel) already geocodes the same address, to avoid double requests, and when the venue sets its coordinates by hand.
 * @param {boolean}  [hasCoordinates] - Whether the venue already has saved coordinates. A saved address with coordinates is not geocoded again on load.
 *
 * @return {void}
 */
export function useGeocoding(
	fieldType,
	fieldValue,
	updateVenueField,
	enabled = true,
	hasCoordinates = false,
) {
	// Get dispatch functions for venue store.
	const { updateVenueLatitude, updateVenueLongitude } =
		useDispatch( 'gatherpress/venue' );

	const { createErrorNotice } = useDispatch( 'core/notices' );

	// The address the current coordinates belong to. The venue loads after the
	// first render, so the saved address is taken the first time it shows up.
	const geocodedAddressRef = useRef( null );
	const seenSavedAddressRef = useRef( false );

	if ( ! seenSavedAddressRef.current && 'address' === fieldType && fieldValue ) {
		seenSavedAddressRef.current = true;
		geocodedAddressRef.current = hasCoordinates ? fieldValue : null;
	}

	// Track address for geocoding.
	const addressRef = useRef( 'address' === fieldType ? fieldValue : '' );
	if ( 'address' === fieldType ) {
		addressRef.current = fieldValue;
	}

	// Bumped whenever the address or the enabled state changes, so a lookup
	// that finishes after either one is ignored.
	const requestRef = useRef( 0 );

	// A delayed call reads this when it runs, after geocoding may have turned off.
	const enabledRef = useRef( enabled );
	enabledRef.current = enabled;

	// Geocode address field changes.
	const handleGeocode = useCallback( async () => {
		const address = addressRef.current;
		const request = requestRef.current;

		// A debounced call reads the address when it runs, so check here: the
		// coordinates may already belong to it.
		if (
			! enabledRef.current ||
			'address' !== fieldType ||
			address === geocodedAddressRef.current
		) {
			return;
		}

		// If address is empty, clear lat/long.
		if ( ! address ) {
			// Clear venue store for live preview.
			updateVenueLatitude( '' );
			updateVenueLongitude( '' );

			// Clear meta for the venue post.
			updateVenueField( {
				gatherpress_latitude: '',
				gatherpress_longitude: '',
			} );
			geocodedAddressRef.current = address;
			return;
		}

		const { latitude, longitude, error, unavailable } =
			await geocodeAddress( address );

		// Skip a lookup made stale by a newer address or enabled state.
		if ( request !== requestRef.current ) {
			return;
		}

		// A failed lookup keeps the stored coordinates, like the server-side geocode.
		if ( error ) {
			createErrorNotice( geocodeFailedMessage( unavailable ), {
				id: GEOCODE_NOTICE_ID,
				type: 'snackbar',
			} );
			return;
		}

		geocodedAddressRef.current = address;

		// Update venue store for live preview.
		updateVenueLatitude( latitude || null );
		updateVenueLongitude( longitude || null );

		// Update meta for the venue post.
		updateVenueField( {
			gatherpress_latitude: latitude || '',
			gatherpress_longitude: longitude || '',
		} );
	}, [
		fieldType,
		updateVenueLatitude,
		updateVenueLongitude,
		updateVenueField,
		createErrorNotice,
	] );

	// Longer debounce than autocomplete: geocoding is not user-visible during
	// typing (only the map preview and stored lat/long depend on it) so we let
	// the address settle for a second before hitting the upstream Photon API.
	const debouncedGeocode = useDebounce( handleGeocode, 1000 );

	// Trigger geocoding when address field value changes.
	useEffect( () => {
		requestRef.current++;

		if ( enabled && 'address' === fieldType ) {
			debouncedGeocode();
		}
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ fieldValue, fieldType, enabled ] );
}
