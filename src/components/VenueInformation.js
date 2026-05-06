/**
 * WordPress dependencies
 */
import { TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useDispatch, useSelect } from '@wordpress/data';
import { useEffect, useCallback, useRef } from '@wordpress/element';
import { useDebounce } from '@wordpress/compose';

/**
 * Internal dependencies
 */
import { geocodeAddress, GEOCODE_LOCK_NAME } from '../helpers/geocoding';
import AddressAutocompleteField from './AddressAutocompleteField';

/**
 * VenueInformation component for GatherPress.
 *
 * This component allows users to input and update venue information, including full address,
 * phone number, and website. Each field is stored as its own post meta key
 * (gatherpress_address, gatherpress_latitude, gatherpress_longitude,
 * gatherpress_phone, gatherpress_website) so the values can be bound to
 * blocks via core/post-meta block bindings.
 *
 * @since 1.0.0
 *
 * @return {JSX.Element} The rendered React component.
 */
const VenueInformation = () => {
	const { editPost, lockPostSaving, unlockPostSaving } =
		useDispatch( 'core/editor' );

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
	const address = venueMeta.gatherpress_address || '';
	const initialLat = venueMeta.gatherpress_latitude || '';
	const initialLng = venueMeta.gatherpress_longitude || '';
	const phone = venueMeta.gatherpress_phone || '';
	const website = venueMeta.gatherpress_website || '';

	// Use ref to track current address for geocoding without recreating callback.
	const addressRef = useRef( address );

	addressRef.current = address;

	// Initialize venue store with saved lat/long values on mount only.
	// After initialization, getData manages the store directly for live updates.
	useEffect( () => {
		if ( initialLat || initialLng ) {
			updateVenueLatitude( initialLat );
			updateVenueLongitude( initialLng );
		}
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [] ); // Run once on mount only.

	// Helper to write one or more individual venue meta keys.
	const updateVenueField = useCallback(
		( meta ) => {
			editPost( { meta } );
		},
		[ editPost ],
	);

	const getData = useCallback( async () => {
		// Read current address from ref to avoid recreating this callback.
		const currentAddress = addressRef.current;

		try {
			// If address is empty, clear lat/long.
			if ( ! currentAddress ) {
				if ( ! mapCustomLatLong ) {
					updateVenueLatitude( '' );
					updateVenueLongitude( '' );
					updateVenueField( {
						gatherpress_latitude: '',
						gatherpress_longitude: '',
					} );
				}
				return;
			}

			const { latitude, longitude } = await geocodeAddress( currentAddress );

			if ( ! mapCustomLatLong ) {
				updateVenueLatitude( latitude || null );
				updateVenueLongitude( longitude || null );
				updateVenueField( {
					gatherpress_latitude: latitude || '',
					gatherpress_longitude: longitude || '',
				} );
			}
		} finally {
			// Release the save lock even when geocoding throws — otherwise
			// the editor would be stuck in the locked state forever.
			unlockPostSaving( GEOCODE_LOCK_NAME );
		}
	}, [
		mapCustomLatLong,
		updateVenueLatitude,
		updateVenueLongitude,
		updateVenueField,
		unlockPostSaving,
	] ); // address removed - read from ref instead.

	// Longer debounce than autocomplete: geocoding is not user-visible during
	// typing (only the map preview and stored lat/long depend on it) so we let
	// the address settle for a second before hitting the upstream Photon API.
	const debouncedGetData = useDebounce( getData, 1000 );

	useEffect( () => {
		// Block the Save button while the address is being geocoded. Without
		// this, a quick save persists the new address with the previous
		// lat/long and Venue\Map bakes the static PNG at the wrong coords
		// until a second save rebuilds it. lockPostSaving is idempotent on
		// the same lock name, so repeat keystrokes don't stack locks.
		lockPostSaving( GEOCODE_LOCK_NAME );
		debouncedGetData();

		return () => {
			// If the component unmounts mid-geocode, release the lock so the
			// editor doesn't stay wedged.
			unlockPostSaving( GEOCODE_LOCK_NAME );
		};
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ address ] ); // Only depend on address, not debouncedGetData.

	return (
		<>
			<AddressAutocompleteField
				variant="settings"
				value={ address }
				onChange={ ( value ) => {
					updateVenueField( { gatherpress_address: value } );
				} }
			/>
			<TextControl
				label={ __( 'Phone Number', 'gatherpress' ) }
				value={ phone }
				onChange={ ( value ) => {
					updateVenueField( { gatherpress_phone: value } );
				} }
			/>
			<TextControl
				label={ __( 'Website', 'gatherpress' ) }
				value={ website }
				type="url"
				onChange={ ( value ) => {
					updateVenueField( { gatherpress_website: value } );
				} }
			/>
		</>
	);
};

export default VenueInformation;
