/**
 * External dependencies
 */
import { render, fireEvent, act } from '@testing-library/react';
import { expect, test, jest, beforeEach } from '@jest/globals';
import '@testing-library/jest-dom';

/**
 * WordPress dependencies
 */
jest.mock( '@wordpress/data', () => ( {
	useSelect: jest.fn(),
	useDispatch: jest.fn(),
} ) );

jest.mock( '@wordpress/compose', () => ( {
	useDebounce: jest.fn( ( callback ) => callback ),
} ) );

jest.mock( '@wordpress/components', () => ( {
	__experimentalVStack: ( { children } ) => <div>{ children }</div>,
	TextControl: ( { label, value, onChange } ) => (
		<div>
			<label htmlFor={ label }>{ label }</label>
			<input
				id={ label }
				value={ value }
				onChange={ ( event ) => onChange( event.target.value ) }
			/>
		</div>
	),
	ToggleControl: ( { label, checked, onChange } ) => (
		<div>
			<label htmlFor={ label }>{ label }</label>
			<input
				id={ label }
				type="checkbox"
				checked={ checked }
				onChange={ ( event ) => onChange( event.target.checked ) }
			/>
		</div>
	),
} ) );

/**
 * Internal dependencies
 */
jest.mock( '@src/helpers/geocoding', () => ( {
	GEOCODE_LOCK_NAME: 'gatherpress/venue-geocoding',
	geocodeAddress: jest.fn( () =>
		Promise.resolve( { latitude: '1', longitude: '2' } ),
	),
} ) );

jest.mock( '@src/components/AddressAutocompleteField', () => ( {
	__esModule: true,
	default: ( { value, onChange } ) => (
		<div>
			<label htmlFor="address">Address</label>
			<input
				id="address"
				value={ value }
				onChange={ ( event ) => onChange( event.target.value ) }
			/>
		</div>
	),
} ) );

import { useSelect, useDispatch } from '@wordpress/data';
import { useDebounce } from '@wordpress/compose';
import { geocodeAddress } from '@src/helpers/geocoding';
import VenueInformation from '@src/components/VenueInformation';

const editor = {
	editPost: jest.fn(),
	lockPostSaving: jest.fn(),
	unlockPostSaving: jest.fn(),
};

const venueStore = {
	updateVenueLatitude: jest.fn(),
	updateVenueLongitude: jest.fn(),
};

let meta = {};

beforeEach( () => {
	jest.clearAllMocks();
	meta = {};

	useDebounce.mockImplementation( ( callback ) => callback );
	useDispatch.mockImplementation( ( store ) =>
		'core/editor' === store ? editor : venueStore,
	);
	useSelect.mockImplementation( ( selector ) =>
		selector( () => ( {
			getEditedPostAttribute: () => meta,
		} ) ),
	);
} );

/**
 * Render the component and let the geocode promise settle.
 *
 * @return {Object} The render result.
 */
const renderVenueInformation = async () => {
	let result;

	await act( async () => {
		result = render( <VenueInformation /> );
	} );

	return result;
};

test( 'geocodes the address into the coordinates', async () => {
	meta = { gatherpress_address: '1 Main St' };

	const { queryByLabelText, getByLabelText } = await renderVenueInformation();

	expect( geocodeAddress ).toHaveBeenCalledWith( '1 Main St' );
	expect( editor.lockPostSaving ).toHaveBeenCalledWith( 'gatherpress/venue-geocoding' );
	expect( editor.editPost ).toHaveBeenCalledWith( {
		meta: { gatherpress_latitude: '1', gatherpress_longitude: '2' },
	} );
	expect( getByLabelText( 'Set coordinates manually' ) ).not.toBeChecked();
	expect( queryByLabelText( 'Latitude' ) ).toBeNull();
} );

test( 'empties the coordinates when the address is not found', async () => {
	geocodeAddress.mockResolvedValueOnce( {} );
	meta = { gatherpress_address: 'Nowhere' };

	await renderVenueInformation();

	expect( venueStore.updateVenueLatitude ).toHaveBeenCalledWith( null );
	expect( venueStore.updateVenueLongitude ).toHaveBeenCalledWith( null );
	expect( editor.editPost ).toHaveBeenCalledWith( {
		meta: { gatherpress_latitude: '', gatherpress_longitude: '' },
	} );
} );

test( 'clears the coordinates without an address', async () => {
	await renderVenueInformation();

	expect( geocodeAddress ).not.toHaveBeenCalled();
	expect( venueStore.updateVenueLatitude ).toHaveBeenCalledWith( '' );
	expect( editor.editPost ).toHaveBeenCalledWith( {
		meta: { gatherpress_latitude: '', gatherpress_longitude: '' },
	} );
} );

test( 'turns on manual coordinates', async () => {
	meta = { gatherpress_address: '1 Main St' };

	const { getByLabelText } = await renderVenueInformation();

	fireEvent.click( getByLabelText( 'Set coordinates manually' ) );

	expect( editor.editPost ).toHaveBeenCalledWith( {
		meta: { gatherpress_manual_coordinates: true },
	} );
} );

test( 'geocodes the address again when manual coordinates turn off', async () => {
	meta = {
		gatherpress_address: '1 Main St',
		gatherpress_manual_coordinates: true,
	};

	const { rerender } = await renderVenueInformation();

	expect( geocodeAddress ).not.toHaveBeenCalled();

	meta = { ...meta, gatherpress_manual_coordinates: false };

	await act( async () => {
		rerender( <VenueInformation /> );
	} );

	expect( geocodeAddress ).toHaveBeenCalledWith( '1 Main St' );
	expect( editor.editPost ).toHaveBeenCalledWith( {
		meta: { gatherpress_latitude: '1', gatherpress_longitude: '2' },
	} );
} );

test( 'keeps manual coordinates and edits them by hand', async () => {
	meta = {
		gatherpress_address: '1 Main St',
		gatherpress_latitude: '52.37',
		gatherpress_longitude: '4.89',
		gatherpress_manual_coordinates: true,
	};

	const { getByLabelText } = await renderVenueInformation();

	expect( geocodeAddress ).not.toHaveBeenCalled();
	expect( editor.lockPostSaving ).not.toHaveBeenCalled();
	expect( getByLabelText( 'Set coordinates manually' ) ).toBeChecked();
	expect( getByLabelText( 'Latitude' ) ).toHaveValue( '52.37' );
	expect( getByLabelText( 'Longitude' ) ).toHaveValue( '4.89' );

	fireEvent.change( getByLabelText( 'Latitude' ), { target: { value: '52.4' } } );
	fireEvent.change( getByLabelText( 'Longitude' ), { target: { value: '4.9' } } );

	expect( venueStore.updateVenueLatitude ).toHaveBeenCalledWith( '52.4' );
	expect( venueStore.updateVenueLongitude ).toHaveBeenCalledWith( '4.9' );
	expect( editor.editPost ).toHaveBeenCalledWith( {
		meta: { gatherpress_latitude: '52.4' },
	} );
	expect( editor.editPost ).toHaveBeenCalledWith( {
		meta: { gatherpress_longitude: '4.9' },
	} );
} );

test( 'edits the address, phone and website', async () => {
	useSelect.mockImplementation( ( selector ) =>
		selector( () => ( {
			getEditedPostAttribute: () => undefined,
		} ) ),
	);

	const { getByLabelText } = await renderVenueInformation();

	fireEvent.change( getByLabelText( 'Address' ), { target: { value: '2 Main St' } } );
	fireEvent.change( getByLabelText( 'Phone Number' ), { target: { value: '555-0100' } } );
	fireEvent.change( getByLabelText( 'Website' ), { target: { value: 'https://example.org' } } );

	expect( editor.editPost ).toHaveBeenCalledWith( {
		meta: { gatherpress_address: '2 Main St' },
	} );
	expect( editor.editPost ).toHaveBeenCalledWith( {
		meta: { gatherpress_phone: '555-0100' },
	} );
	expect( editor.editPost ).toHaveBeenCalledWith( {
		meta: { gatherpress_website: 'https://example.org' },
	} );
} );

test( 'skips a pending geocode once the coordinates are manual', async () => {
	let pendingGeocode = null;

	useDebounce.mockImplementation( ( callback ) => {
		pendingGeocode = callback;

		return () => {};
	} );
	meta = { gatherpress_address: '1 Main St' };

	const { rerender } = await renderVenueInformation();

	meta = { ...meta, gatherpress_manual_coordinates: true };
	rerender( <VenueInformation /> );

	await act( async () => {
		await pendingGeocode();
	} );

	expect( geocodeAddress ).not.toHaveBeenCalled();
	expect( editor.editPost ).not.toHaveBeenCalled();
	expect( editor.unlockPostSaving ).toHaveBeenCalledWith( 'gatherpress/venue-geocoding' );
} );

test( 'skips a lookup that finishes after the coordinates turn manual', async () => {
	let resolveGeocode;

	geocodeAddress.mockReturnValueOnce(
		new Promise( ( resolve ) => {
			resolveGeocode = resolve;
		} ),
	);
	meta = { gatherpress_address: '1 Main St' };

	const { rerender } = await renderVenueInformation();

	meta = { ...meta, gatherpress_manual_coordinates: true };
	rerender( <VenueInformation /> );

	await act( async () => {
		resolveGeocode( { latitude: '3', longitude: '4' } );
	} );

	expect( venueStore.updateVenueLatitude ).not.toHaveBeenCalled();
	expect( venueStore.updateVenueLongitude ).not.toHaveBeenCalled();
	expect( editor.editPost ).not.toHaveBeenCalled();
} );

test( 'skips a lookup that finishes after the address changes', async () => {
	let resolveGeocode;

	geocodeAddress.mockReturnValueOnce(
		new Promise( ( resolve ) => {
			resolveGeocode = resolve;
		} ),
	);
	meta = { gatherpress_address: '1 Main St' };

	const { rerender } = await renderVenueInformation();

	meta = { gatherpress_address: '2 Main St' };

	await act( async () => {
		rerender( <VenueInformation /> );
	} );

	await act( async () => {
		resolveGeocode( { latitude: '3', longitude: '4' } );
	} );

	expect( geocodeAddress ).toHaveBeenCalledWith( '2 Main St' );
	expect( editor.editPost ).toHaveBeenCalledWith( {
		meta: { gatherpress_latitude: '1', gatherpress_longitude: '2' },
	} );
	expect( venueStore.updateVenueLatitude ).not.toHaveBeenCalledWith( '3' );
	expect( editor.editPost ).not.toHaveBeenCalledWith( {
		meta: { gatherpress_latitude: '3', gatherpress_longitude: '4' },
	} );
} );
