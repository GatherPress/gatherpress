/**
 * External dependencies
 */
import { renderHook, act } from '@testing-library/react';
import { expect, test, jest, beforeEach } from '@jest/globals';

/**
 * WordPress dependencies
 */
jest.mock( '@wordpress/data', () => ( {
	useDispatch: jest.fn(),
} ) );

jest.mock( '@wordpress/compose', () => ( {
	useDebounce: jest.fn( ( callback ) => callback ),
} ) );

/**
 * Internal dependencies
 */
jest.mock( '@src/helpers/geocoding', () => ( {
	GEOCODE_NOTICE_ID: 'gatherpress_geocode_failed',
	geocodeFailedMessage: ( unavailable ) =>
		unavailable ? 'Geocode unavailable.' : 'Geocode failed.',
	geocodeAddress: jest.fn( () =>
		Promise.resolve( { latitude: '1', longitude: '2', error: null } ),
	),
} ) );

import { useDispatch } from '@wordpress/data';
import { useDebounce } from '@wordpress/compose';
import { geocodeAddress } from '@src/helpers/geocoding';
import { useGeocoding } from '@src/blocks/venue-detail/hooks/use-geocoding';

const venueStore = {
	updateVenueLatitude: jest.fn(),
	updateVenueLongitude: jest.fn(),
};

const notices = {
	createErrorNotice: jest.fn(),
};

const updateVenueField = jest.fn();

beforeEach( () => {
	jest.clearAllMocks();

	useDebounce.mockImplementation( ( callback ) => callback );

	useDispatch.mockImplementation( ( store ) =>
		'core/notices' === store ? notices : venueStore,
	);
} );

/**
 * Render the hook with the given props and let any lookup settle.
 *
 * @param {Object} props The initial props.
 *
 * @return {Object} The renderHook result.
 */
const renderGeocoding = async ( props ) => {
	let result;

	await act( async () => {
		result = renderHook(
			( { value, coordinates, enabled = true } ) =>
				useGeocoding( 'address', value, updateVenueField, enabled, coordinates ),
			{ initialProps: props },
		);
	} );

	return result;
};

test( 'does not geocode a saved address with coordinates once the venue loads', async () => {
	const { rerender } = await renderGeocoding( { value: '', coordinates: false } );

	await act( async () => {
		rerender( { value: '1 Main St', coordinates: true } );
	} );

	expect( geocodeAddress ).not.toHaveBeenCalled();
	expect( updateVenueField ).not.toHaveBeenCalledWith( {
		gatherpress_latitude: '1',
		gatherpress_longitude: '2',
	} );
} );

test( 'geocodes a saved address that has no coordinates', async () => {
	await renderGeocoding( { value: '1 Main St', coordinates: false } );

	expect( geocodeAddress ).toHaveBeenCalledWith( '1 Main St' );
	expect( updateVenueField ).toHaveBeenCalledWith( {
		gatherpress_latitude: '1',
		gatherpress_longitude: '2',
	} );
} );

test( 'geocodes a changed address', async () => {
	const { rerender } = await renderGeocoding( { value: '1 Main St', coordinates: true } );

	await act( async () => {
		rerender( { value: '2 Main St', coordinates: true } );
	} );

	expect( geocodeAddress ).toHaveBeenCalledWith( '2 Main St' );
	expect( venueStore.updateVenueLatitude ).toHaveBeenCalledWith( '1' );
} );

test( 'keeps the coordinates and shows a notice when the lookup fails', async () => {
	geocodeAddress.mockResolvedValueOnce( {
		latitude: '',
		longitude: '',
		error: 'Geocoding request failed.',
	} );

	const { rerender } = await renderGeocoding( { value: '1 Main St', coordinates: true } );

	await act( async () => {
		rerender( { value: '2 Main St', coordinates: true } );
	} );

	expect( updateVenueField ).not.toHaveBeenCalled();
	expect( venueStore.updateVenueLatitude ).not.toHaveBeenCalled();
	expect( notices.createErrorNotice ).toHaveBeenCalledWith( 'Geocode failed.', {
		id: 'gatherpress_geocode_failed',
		type: 'snackbar',
	} );
} );

test( 'shows the outage notice when the location service is unavailable', async () => {
	geocodeAddress.mockResolvedValueOnce( {
		latitude: '',
		longitude: '',
		error: 'Geocoding request failed.',
		unavailable: true,
	} );

	const { rerender } = await renderGeocoding( { value: '1 Main St', coordinates: true } );

	await act( async () => {
		rerender( { value: '2 Main St', coordinates: true } );
	} );

	expect( updateVenueField ).not.toHaveBeenCalled();
	expect( notices.createErrorNotice ).toHaveBeenCalledWith( 'Geocode unavailable.', {
		id: 'gatherpress_geocode_failed',
		type: 'snackbar',
	} );
} );

test( 'clears the coordinates when the address is emptied', async () => {
	const { rerender } = await renderGeocoding( { value: '1 Main St', coordinates: true } );

	await act( async () => {
		rerender( { value: '', coordinates: true } );
	} );

	expect( updateVenueField ).toHaveBeenCalledWith( {
		gatherpress_latitude: '',
		gatherpress_longitude: '',
	} );
	expect( geocodeAddress ).not.toHaveBeenCalled();
} );

test( 'ignores a lookup that finishes after the address changes back', async () => {
	let resolveGeocode;

	geocodeAddress.mockReturnValueOnce(
		new Promise( ( resolve ) => {
			resolveGeocode = resolve;
		} ),
	);

	const { rerender } = await renderGeocoding( { value: '1 Main St', coordinates: true } );

	await act( async () => {
		rerender( { value: '2 Main St', coordinates: true } );
	} );

	await act( async () => {
		rerender( { value: '1 Main St', coordinates: true } );
	} );

	await act( async () => {
		resolveGeocode( { latitude: '3', longitude: '4', error: null } );
	} );

	expect( geocodeAddress ).toHaveBeenCalledTimes( 1 );
	expect( updateVenueField ).not.toHaveBeenCalled();
	expect( venueStore.updateVenueLatitude ).not.toHaveBeenCalled();
} );

test( 'ignores a lookup that finishes after geocoding is turned off', async () => {
	let resolveGeocode;

	geocodeAddress.mockReturnValueOnce(
		new Promise( ( resolve ) => {
			resolveGeocode = resolve;
		} ),
	);

	const { rerender } = await renderGeocoding( { value: '1 Main St', coordinates: true } );

	await act( async () => {
		rerender( { value: '2 Main St', coordinates: true } );
	} );

	await act( async () => {
		rerender( { value: '2 Main St', coordinates: true, enabled: false } );
	} );

	await act( async () => {
		resolveGeocode( { latitude: '', longitude: '', error: 'Geocoding request failed.' } );
	} );

	expect( updateVenueField ).not.toHaveBeenCalled();
	expect( notices.createErrorNotice ).not.toHaveBeenCalled();
} );

test( 'ignores a lookup that finishes after geocoding is turned off and on again', async () => {
	let resolveGeocode;

	geocodeAddress.mockReturnValueOnce(
		new Promise( ( resolve ) => {
			resolveGeocode = resolve;
		} ),
	);

	const { rerender } = await renderGeocoding( { value: '1 Main St', coordinates: true } );

	await act( async () => {
		rerender( { value: '2 Main St', coordinates: true } );
	} );

	await act( async () => {
		rerender( { value: '2 Main St', coordinates: true, enabled: false } );
	} );

	await act( async () => {
		rerender( { value: '2 Main St', coordinates: true, enabled: true } );
	} );

	await act( async () => {
		resolveGeocode( { latitude: '3', longitude: '4', error: null } );
	} );

	expect( geocodeAddress ).toHaveBeenCalledTimes( 2 );
	expect( venueStore.updateVenueLatitude ).toHaveBeenCalledWith( '1' );
	expect( venueStore.updateVenueLatitude ).not.toHaveBeenCalledWith( '3' );
} );

test( 'skips a delayed lookup once geocoding is turned off', async () => {
	let pendingGeocode = null;

	useDebounce.mockImplementation( ( callback ) => {
		pendingGeocode = callback;

		return () => {};
	} );

	const { rerender } = await renderGeocoding( { value: '1 Main St', coordinates: true } );

	await act( async () => {
		rerender( { value: '2 Main St', coordinates: true } );
	} );

	await act( async () => {
		rerender( { value: '2 Main St', coordinates: true, enabled: false } );
	} );

	await act( async () => {
		await pendingGeocode();
	} );

	expect( geocodeAddress ).not.toHaveBeenCalled();
	expect( updateVenueField ).not.toHaveBeenCalled();
} );
