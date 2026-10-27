/**
 * WordPress dependencies
 */
import { TextControl, ToggleControl } from '@wordpress/components';
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
 * blocks via core/post-meta block bindings. The coordinates come from the
 * address unless gatherpress_manual_coordinates is on, for a venue without
 * a street address.
 *
 * @since 0.27.0
 *
 * @return {JSX.Element} The rendered React component.
 */
const VenueInformation = () => {
	const { editPost, lockPostSaving, unlockPostSaving } =
		useDispatch( 'core/editor' );

	const { updateVenueLatitude, updateVenueLongitude } =
		useDispatch( 'gatherpress/venue' );

	const { venueMeta } = useSelect(
		( selectData ) => ( {
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
	const manualCoordinates = !! venueMeta.gatherpress_manual_coordinates;

	// Use refs to track the current address and coordinates setting for
	// geocoding without recreating the callback.
	const addressRef = useRef( address );
	const manualCoordinatesRef = useRef( manualCoordinates );

	addressRef.current = address;
	manualCoordinatesRef.current = manualCoordinates;

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
			// Coordinates set by hand stay as they are.
			if ( manualCoordinatesRef.current ) {
				return;
			}

			// If address is empty, clear lat/long.
			if ( ! currentAddress ) {
				updateVenueLatitude( '' );
				updateVenueLongitude( '' );
				updateVenueField( {
					gatherpress_latitude: '',
					gatherpress_longitude: '',
				} );
				return;
			}

			const { latitude, longitude } = await geocodeAddress( currentAddress );

			updateVenueLatitude( latitude || null );
			updateVenueLongitude( longitude || null );
			updateVenueField( {
				gatherpress_latitude: latitude || '',
				gatherpress_longitude: longitude || '',
			} );
		} finally {
			// Release the save lock even when geocoding throws — otherwise
			// the editor would be stuck in the locked state forever.
			unlockPostSaving( GEOCODE_LOCK_NAME );
		}
	}, [
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
		// Coordinates set by hand need no geocoding. Turning them off geocodes
		// the address again.
		if ( manualCoordinates ) {
			return undefined;
		}

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
	}, [ address, manualCoordinates ] ); // Not debouncedGetData.

	return (
		<>
			<AddressAutocompleteField
				variant="settings"
				value={ address }
				onChange={ ( value ) => {
					updateVenueField( { gatherpress_address: value } );
				} }
			/>
			<ToggleControl
				label={ __( 'Set coordinates manually', 'gatherpress' ) }
				help={ __(
					'For a venue without a street address, like a park entrance. The address then no longer moves the map pin.',
					'gatherpress',
				) }
				checked={ manualCoordinates }
				onChange={ ( value ) => {
					updateVenueField( { gatherpress_manual_coordinates: value } );
				} }
			/>
			{ manualCoordinates && (
				<>
					<TextControl
						__next40pxDefaultSize
						label={ __( 'Latitude', 'gatherpress' ) }
						value={ venueMeta.gatherpress_latitude || '' }
						type="number"
						step="any"
						onChange={ ( value ) => {
							updateVenueLatitude( value );
							updateVenueField( { gatherpress_latitude: value } );
						} }
					/>
					<TextControl
						__next40pxDefaultSize
						label={ __( 'Longitude', 'gatherpress' ) }
						value={ venueMeta.gatherpress_longitude || '' }
						type="number"
						step="any"
						onChange={ ( value ) => {
							updateVenueLongitude( value );
							updateVenueField( { gatherpress_longitude: value } );
						} }
					/>
				</>
			) }
			<TextControl
				__next40pxDefaultSize
				label={ __( 'Phone Number', 'gatherpress' ) }
				value={ phone }
				onChange={ ( value ) => {
					updateVenueField( { gatherpress_phone: value } );
				} }
			/>
			<TextControl
				__next40pxDefaultSize
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
