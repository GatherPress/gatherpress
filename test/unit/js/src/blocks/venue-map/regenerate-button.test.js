/**
 * External dependencies.
 */
import { describe, expect, it, jest, beforeEach } from '@jest/globals';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import '@testing-library/jest-dom';

/**
 * Mocks — declared before the component import so jest hoists them.
 */
const mockApiFetch = jest.fn();
const mockInvalidateResolution = jest.fn();
const mockReceiveEntityRecords = jest.fn();
let mockCurrentEntityRecord;

jest.mock( '@wordpress/api-fetch', () => ( {
	__esModule: true,
	default: ( args ) => mockApiFetch( args ),
} ) );

jest.mock( '@wordpress/data', () => ( {
	useDispatch: () => ( {
		invalidateResolution: mockInvalidateResolution,
		receiveEntityRecords: mockReceiveEntityRecords,
	} ),
	useSelect: ( callback ) =>
		callback( () => ( {
			getEntityRecord: () => mockCurrentEntityRecord,
		} ) ),
} ) );

// Lightweight stand-ins for the two @wordpress/components primitives this
// component uses. Importing the real package pulls in @wordpress/rich-text
// which needs a full @wordpress/data runtime wired up, which we don't.
jest.mock( '@wordpress/components', () => ( {
	Button: ( { children, ...props } ) => (
		<button type="button" { ...props }>
			{ children }
		</button>
	),
	Spinner: () => <span data-testid="spinner" />,
} ) );

/**
 * Internal dependencies.
 */
import RegenerateMapButton from '@src/blocks/venue-map/regenerate-button';

describe( 'RegenerateMapButton', () => {
	const defaultProps = {
		venuePostId: 42,
		venuePostType: 'gatherpress_venue',
	};

	beforeEach( () => {
		mockApiFetch.mockReset();
		mockInvalidateResolution.mockReset();
		mockReceiveEntityRecords.mockReset();
		mockApiFetch.mockResolvedValue( { descriptors: {}, reason: '' } );
		mockCurrentEntityRecord = {
			id: 42,
			meta: { gatherpress_venue_information: '{}' },
		};
	} );

	it( 'renders the default "Regenerate map" label', () => {
		render( <RegenerateMapButton { ...defaultProps } /> );
		expect(
			screen.getByRole( 'button', { name: /regenerate map/i } )
		).toBeInTheDocument();
	} );

	it( 'renders a custom label when provided', () => {
		render(
			<RegenerateMapButton { ...defaultProps } label="Generate map" />
		);
		expect(
			screen.getByRole( 'button', { name: /generate map/i } )
		).toBeInTheDocument();
	} );

	it( 'is disabled when the disabled prop is true', () => {
		render( <RegenerateMapButton { ...defaultProps } disabled /> );
		expect( screen.getByRole( 'button' ) ).toBeDisabled();
	} );

	it( 'POSTs to the correct REST path on click', async () => {
		render( <RegenerateMapButton { ...defaultProps } /> );
		fireEvent.click( screen.getByRole( 'button' ) );

		await waitFor( () => {
			expect( mockApiFetch ).toHaveBeenCalledWith(
				expect.objectContaining( {
					path: '/gatherpress/v1/venue/42/regenerate-map',
					method: 'POST',
				} )
			);
		} );
	} );

	it( 'forwards current zoom/height in the POST body so the server renders this combo', async () => {
		render(
			<RegenerateMapButton
				{ ...defaultProps }
				zoom={ 8 }
				height={ 295 }
			/>
		);
		fireEvent.click( screen.getByRole( 'button' ) );

		await waitFor( () => {
			expect( mockApiFetch ).toHaveBeenCalledWith(
				expect.objectContaining( {
					data: { zoom: 8, height: 295 },
				} )
			);
		} );
	} );

	it( 'invalidates the core entity record on success so the editor re-reads meta', async () => {
		render( <RegenerateMapButton { ...defaultProps } /> );
		fireEvent.click( screen.getByRole( 'button' ) );

		await waitFor( () => {
			expect( mockInvalidateResolution ).toHaveBeenCalledWith(
				'getEntityRecord',
				[ 'postType', 'gatherpress_venue', 42 ]
			);
		} );
	} );

	it( 'patches fresh descriptors into the core store cache', async () => {
		mockApiFetch.mockResolvedValueOnce( {
			descriptors: {
				'18x300': {
					url: 'https://example.test/42-abc.png',
					hash: 'abc',
					zoom: 18,
					height: 300,
				},
			},
			reason: '',
		} );

		render( <RegenerateMapButton { ...defaultProps } /> );
		fireEvent.click( screen.getByRole( 'button' ) );

		await waitFor( () => {
			expect( mockReceiveEntityRecords ).toHaveBeenCalledTimes( 1 );
		} );

		const [ kind, name, records ] =
			mockReceiveEntityRecords.mock.calls[ 0 ];
		expect( kind ).toBe( 'postType' );
		expect( name ).toBe( 'gatherpress_venue' );
		expect( records[ 0 ].id ).toBe( 42 );
		expect(
			records[ 0 ].meta.gatherpress_venue_static_map[ '18x300' ].url
		).toBe( 'https://example.test/42-abc.png' );
		// Existing meta fields must be preserved.
		expect( records[ 0 ].meta.gatherpress_venue_information ).toBe( '{}' );
	} );

	it( 'skips the store patch when no cached record exists yet', async () => {
		mockCurrentEntityRecord = null;

		render( <RegenerateMapButton { ...defaultProps } /> );
		fireEvent.click( screen.getByRole( 'button' ) );

		await waitFor( () => {
			expect( mockApiFetch ).toHaveBeenCalled();
		} );

		expect( mockReceiveEntityRecords ).not.toHaveBeenCalled();
	} );

	it( 'does not call the REST endpoint when venuePostId is missing', () => {
		render( <RegenerateMapButton venuePostId={ 0 } venuePostType="" /> );
		fireEvent.click( screen.getByRole( 'button' ) );
		expect( mockApiFetch ).not.toHaveBeenCalled();
	} );

	it( 'skips the store invalidation when no venuePostType is known', async () => {
		render(
			<RegenerateMapButton venuePostId={ 42 } venuePostType="" />
		);
		fireEvent.click( screen.getByRole( 'button' ) );

		await waitFor( () => {
			expect( mockApiFetch ).toHaveBeenCalled();
		} );

		expect( mockInvalidateResolution ).not.toHaveBeenCalled();
	} );

	it( 'ignores repeat clicks while a request is in flight', async () => {
		let resolveFetch;
		mockApiFetch.mockImplementationOnce(
			() => new Promise( ( resolve ) => ( resolveFetch = resolve ) )
		);

		render( <RegenerateMapButton { ...defaultProps } /> );
		const button = screen.getByRole( 'button' );

		fireEvent.click( button );
		fireEvent.click( button );
		fireEvent.click( button );

		resolveFetch( { descriptors: {}, reason: '' } );

		await waitFor( () => {
			expect( mockApiFetch ).toHaveBeenCalledTimes( 1 );
		} );
	} );
} );
