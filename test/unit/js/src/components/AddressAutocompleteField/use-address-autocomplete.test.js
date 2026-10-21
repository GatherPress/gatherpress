/**
 * External dependencies
 */
import { describe, expect, it, jest, beforeEach, afterEach } from '@jest/globals';
import { act, renderHook } from '@testing-library/react';

/**
 * Internal dependencies
 */
import { useAddressAutocomplete } from '@src/components/AddressAutocompleteField/use-address-autocomplete';
import { fetchAddressSuggestions } from '@src/helpers/geocoding';

jest.mock( '@src/helpers/geocoding', () => ( {
	fetchAddressSuggestions: jest.fn(),
	getAddressSearchMinQueryLength: () => 3,
	primeGeocodeCache: jest.fn(),
} ) );

/**
 * The real `useDebounce` is used here (the field tests replace it), so these
 * tests can type while a search is still waiting for its debounce delay.
 */
describe( 'useAddressAutocomplete', () => {
	beforeEach( () => {
		jest.useFakeTimers();
		jest.clearAllMocks();
		fetchAddressSuggestions.mockResolvedValue( [
			{ label: 'First St', latitude: '1', longitude: '2' },
		] );
	} );

	afterEach( () => {
		jest.useRealTimers();
	} );

	/**
	 * Lets the debounce delay pass and the mocked request resolve.
	 *
	 * @param {number} ms Time to advance, in milliseconds.
	 */
	const settle = async ( ms ) => {
		await act( async () => {
			jest.advanceTimersByTime( ms );
			await Promise.resolve();
		} );
	};

	const keyEvent = ( key ) => ( {
		key,
		shiftKey: false,
		preventDefault: jest.fn(),
		stopPropagation: jest.fn(),
	} );

	it.each( [ 'Enter', 'Escape' ] )(
		'%s cancels a search that is still waiting, so the list stays closed',
		async ( key ) => {
			const { result } = renderHook( () =>
				useAddressAutocomplete( { onChange: jest.fn() } ),
			);

			act( () => result.current.handleChange( 'First' ) );
			await settle( 400 );

			expect( result.current.suggestions ).toHaveLength( 1 );
			expect( fetchAddressSuggestions ).toHaveBeenCalledTimes( 1 );

			// Type more, then close the list before the new search runs.
			act( () => result.current.handleChange( 'First S' ) );
			act( () => result.current.handleKeyDown( keyEvent( key ) ) );

			expect( result.current.suggestions ).toHaveLength( 0 );

			await settle( 1000 );

			expect( fetchAddressSuggestions ).toHaveBeenCalledTimes( 1 );
			expect( result.current.suggestions ).toHaveLength( 0 );
		},
	);
} );
