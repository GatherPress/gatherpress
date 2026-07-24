/**
 * External dependencies
 */
import { render, fireEvent, act } from '@testing-library/react';
import { expect, test, jest, beforeEach } from '@jest/globals';
import '@testing-library/jest-dom';

/**
 * WordPress dependencies
 */
jest.mock( '@wordpress/api-fetch', () => jest.fn() );

jest.mock( '@wordpress/data', () => ( {
	useSelect: jest.fn(),
	useDispatch: jest.fn( () => ( { editPost: jest.fn() } ) ),
} ) );

jest.mock( '@wordpress/components', () => ( {
	Spinner: () => <span>Spinner</span>,
	Button: ( { children, onClick, disabled, variant } ) => (
		<button
			className={ `is-${ variant }` }
			onClick={ onClick }
			disabled={ disabled }
		>
			{ children }
		</button>
	),
	TextControl: ( { label, value, onChange, help } ) => (
		<div>
			<label htmlFor="test-venue-title">{ label }</label>
			<input
				id="test-venue-title"
				value={ value }
				onChange={ ( event ) => onChange( event.target.value ) }
			/>
			{ help }
		</div>
	),
	__experimentalHStack: ( { children } ) => <div>{ children }</div>,
	useNavigator: jest.fn( () => ( { goTo: jest.fn() } ) ),
} ) );

jest.mock( '@wordpress/core-data', () => ( {
	store: 'core',
} ) );

/**
 * Internal dependencies
 */
jest.mock( '@src/helpers/editor', () => ( {
	usePostTypeLabel: jest.fn( () => 'Venue' ),
} ) );

jest.mock( '@src/helpers/venue', () => ( {
	getVenuePostType: jest.fn( () => 'gatherpress_venue' ),
	getVenueTaxonomy: jest.fn( () => '_gatherpress_venue' ),
} ) );

jest.mock( '@src/helpers/event', () => ( {
	isPostTypeSupporting: jest.fn( () => false ),
} ) );

jest.mock( '@src/helpers/geocoding', () => ( {
	geocodeAddress: jest.fn( () =>
		Promise.resolve( { latitude: '1', longitude: '2' } ),
	),
} ) );

jest.mock( '@src/components/AddressAutocompleteField', () => ( {
	__esModule: true,
	default: () => null,
} ) );

import apiFetch from '@wordpress/api-fetch';
import { useSelect } from '@wordpress/data';
import CreateVenueForm from '@src/components/VenueForm';

beforeEach( () => {
	jest.clearAllMocks();

	useSelect.mockImplementation( ( selector ) =>
		selector( ( store ) => {
			if ( 'core' === store ) {
				return {
					getPostType: jest.fn( () => ( { rest_base: 'venues' } ) ),
					getLastEntitySaveError: jest.fn( () => null ),
					isSavingEntityRecord: jest.fn( () => false ),
				};
			}
			if ( 'core/editor' === store ) {
				return { getCurrentPostType: jest.fn( () => 'gatherpress_event' ) };
			}
			return null;
		} ),
	);
} );

/**
 * Coverage for CreateVenueForm's duplicate-submission guard.
 */
test( 'Save button disables immediately and only creates one venue on repeated clicks', async () => {
	let resolveFetch;
	apiFetch.mockImplementation(
		() =>
			new Promise( ( resolve ) => {
				resolveFetch = resolve;
			} ),
	);

	const { container } = render(
		<CreateVenueForm search="My Venue" context={ {} } />,
	);

	const saveButton = container.querySelector( 'button.is-primary' );
	expect( saveButton ).not.toBeNull();
	expect( saveButton ).not.toBeDisabled();

	await act( async () => {
		fireEvent.click( saveButton );
		fireEvent.click( saveButton );
		fireEvent.click( saveButton );
	} );

	expect( saveButton ).toBeDisabled();
	expect( apiFetch ).toHaveBeenCalledTimes( 1 );

	await act( async () => {
		resolveFetch( { id: 1, slug: 'my-venue' } );
		await Promise.resolve();
	} );
} );

test( 'Save button re-enables after the create request settles', async () => {
	apiFetch.mockResolvedValue( { id: 1, slug: 'my-venue' } );

	const { container } = render(
		<CreateVenueForm search="My Venue" context={ {} } />,
	);

	const saveButton = container.querySelector( 'button.is-primary' );

	await act( async () => {
		fireEvent.click( saveButton );
	} );

	expect( apiFetch ).toHaveBeenCalledTimes( 1 );
	expect( saveButton ).not.toBeDisabled();

	await act( async () => {
		fireEvent.click( saveButton );
	} );

	expect( apiFetch ).toHaveBeenCalledTimes( 2 );
} );
