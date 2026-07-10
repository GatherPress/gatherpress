/**
 * External dependencies
 */
import {
	afterEach,
	beforeEach,
	describe,
	expect,
	it,
	jest,
} from '@jest/globals';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import '@testing-library/jest-dom';

/**
 * Mocks — declared before the component import so jest hoists them.
 */
const mockApiFetch = jest.fn();
const mockCreateErrorNotice = jest.fn();

let mockPostId;
let mockUserList;

jest.mock( '@wordpress/api-fetch', () => ( {
	__esModule: true,
	default: ( args ) => mockApiFetch( args ),
} ) );

jest.mock( '@wordpress/data', () => ( {
	useDispatch: ( store ) => {
		if ( 'core/notices' === store ) {
			return { createErrorNotice: mockCreateErrorNotice };
		}
		return {};
	},
	// The component calls useSelect twice: first for the post ID, then for
	// the user list. Hand each call the value it expects by inspecting what
	// the callback reads.
	useSelect: ( callback ) => {
		const result = callback( () => ( {
			getCurrentPostId: () => mockPostId,
			getEntityRecords: () => mockUserList,
		} ) );
		return result?.userList !== undefined ? result : mockPostId;
	},
} ) );

jest.mock( '@wordpress/core-data', () => ( {
	store: 'core',
} ) );

// Lightweight stand-ins so we don't pull in the full @wordpress/components
// runtime. FormTokenField exposes buttons that drive its onChange the way a
// user adding/removing a token would.
jest.mock( '@wordpress/components', () => ( {
	SelectControl: () => <div data-testid="select-control" />,
	FormTokenField: ( { value, onChange } ) => (
		<div data-testid="token-field">
			<span data-testid="token-count">{ value?.length ?? 0 }</span>
			<button
				type="button"
				data-testid="add-token"
				onClick={ () => onChange( [ 'alice' ] ) }
			>
				add
			</button>
			<button
				type="button"
				data-testid="remove-token"
				onClick={ () => onChange( [] ) }
			>
				remove
			</button>
		</div>
	),
} ) );

/**
 * Internal dependencies
 */
import RsvpManager from '@src/blocks/rsvp-response/rsvp-manager';

/**
 * Builds a populated responses payload with one attending member.
 *
 * @param {Object[]} attending Records for the attending bucket.
 * @return {Object} A responses object shaped like the REST payload.
 */
function responsesWith( attending = [] ) {
	return {
		attending: { records: attending },
		waiting_list: { records: [] },
		not_attending: { records: [] },
	};
}

const ATTENDEE = { userId: 5, name: 'Alice' };

describe( 'RsvpManager', () => {
	const defaultProps = {
		defaultStatus: 'attending',
		setDefaultStatus: jest.fn(),
	};

	beforeEach( () => {
		mockApiFetch.mockReset();
		mockCreateErrorNotice.mockReset();
		mockPostId = 123;
		mockUserList = [ { id: 5, username: 'alice' } ];
	} );

	afterEach( () => {
		jest.clearAllMocks();
	} );

	it( 'surfaces an error notice when the responses fetch rejects', async () => {
		mockApiFetch.mockRejectedValueOnce( new Error( 'network down' ) );

		render( <RsvpManager { ...defaultProps } /> );

		await waitFor( () =>
			expect( mockCreateErrorNotice ).toHaveBeenCalledWith(
				'network down',
				{ type: 'snackbar' },
			),
		);
	} );

	it( 'falls back to a default message when the responses rejection has no message', async () => {
		mockApiFetch.mockRejectedValueOnce( {} );

		render( <RsvpManager { ...defaultProps } /> );

		await waitFor( () =>
			expect( mockCreateErrorNotice ).toHaveBeenCalledWith(
				'Could not load RSVP responses. Please try again.',
				{ type: 'snackbar' },
			),
		);
	} );

	it( 'surfaces an error notice when updating an RSVP rejects', async () => {
		// First call resolves the responses; the /rsvp update rejects.
		mockApiFetch.mockImplementation( ( { path } ) => {
			if ( path.includes( 'rsvp-responses' ) ) {
				return Promise.resolve( {
					success: true,
					data: responsesWith( [] ),
				} );
			}
			return Promise.reject( new Error( 'save failed' ) );
		} );

		render( <RsvpManager { ...defaultProps } /> );

		await waitFor( () =>
			expect( screen.getByTestId( 'token-field' ) ).toBeInTheDocument(),
		);

		fireEvent.click( screen.getByTestId( 'add-token' ) );

		await waitFor( () =>
			expect( mockCreateErrorNotice ).toHaveBeenCalledWith( 'save failed', {
				type: 'snackbar',
			} ),
		);
	} );

	it( 'falls back to a default message when the update rejection has no message', async () => {
		mockApiFetch.mockImplementation( ( { path } ) => {
			if ( path.includes( 'rsvp-responses' ) ) {
				return Promise.resolve( {
					success: true,
					data: responsesWith( [] ),
				} );
			}
			return Promise.reject( {} );
		} );

		render( <RsvpManager { ...defaultProps } /> );

		await waitFor( () =>
			expect( screen.getByTestId( 'token-field' ) ).toBeInTheDocument(),
		);

		fireEvent.click( screen.getByTestId( 'add-token' ) );

		await waitFor( () =>
			expect( mockCreateErrorNotice ).toHaveBeenCalledWith(
				'Could not update the RSVP. Please try again.',
				{ type: 'snackbar' },
			),
		);
	} );

	it( 'does not wipe the attendee list when the update response has no responses key', async () => {
		mockApiFetch.mockImplementation( ( { path } ) => {
			if ( path.includes( 'rsvp-responses' ) ) {
				return Promise.resolve( {
					success: true,
					data: responsesWith( [ ATTENDEE ] ),
				} );
			}
			// Error-shaped response: success false, no `responses` key.
			return Promise.resolve( { success: false, message: 'nope' } );
		} );

		render( <RsvpManager { ...defaultProps } /> );

		await waitFor( () =>
			expect( screen.getByTestId( 'token-count' ) ).toHaveTextContent( '1' ),
		);

		// Trigger a removal, which posts to /rsvp and gets the error shape back.
		fireEvent.click( screen.getByTestId( 'remove-token' ) );

		// The manager must stay rendered with its attendee, not collapse to null.
		await waitFor( () =>
			expect( screen.getByTestId( 'token-field' ) ).toBeInTheDocument(),
		);
		expect( screen.getByTestId( 'token-count' ) ).toHaveTextContent( '1' );
		expect( mockCreateErrorNotice ).not.toHaveBeenCalled();
	} );

	it( 'updates the attendee list when the update response carries responses', async () => {
		mockApiFetch.mockImplementation( ( { path } ) => {
			if ( path.includes( 'rsvp-responses' ) ) {
				return Promise.resolve( {
					success: true,
					data: responsesWith( [] ),
				} );
			}
			return Promise.resolve( { responses: responsesWith( [ ATTENDEE ] ) } );
		} );

		render( <RsvpManager { ...defaultProps } /> );

		await waitFor( () =>
			expect( screen.getByTestId( 'token-count' ) ).toHaveTextContent( '0' ),
		);

		fireEvent.click( screen.getByTestId( 'add-token' ) );

		await waitFor( () =>
			expect( screen.getByTestId( 'token-count' ) ).toHaveTextContent( '1' ),
		);
		expect( mockCreateErrorNotice ).not.toHaveBeenCalled();
	} );
} );
