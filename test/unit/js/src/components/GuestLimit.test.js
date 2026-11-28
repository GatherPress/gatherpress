/**
 * External dependencies
 */
import { describe, expect, it, jest, beforeEach } from '@jest/globals';
import { fireEvent, render, screen } from '@testing-library/react';
import '@testing-library/jest-dom';

/**
 * WordPress dependencies
 */
import { useDispatch, useSelect } from '@wordpress/data';

/**
 * Internal dependencies
 */
import GuestLimit from '@src/components/GuestLimit';
import { getFromSettings } from '@src/helpers/editor-settings';

jest.mock( '@wordpress/data', () => ( {
	useDispatch: jest.fn(),
	useSelect: jest.fn(),
} ) );

jest.mock( '@wordpress/components', () => ( {
	__experimentalNumberControl: ( { label, value, onChange, help, max } ) => (
		<div>
			<label htmlFor="mock-guest-limit">{ label }</label>
			<input
				id="mock-guest-limit"
				type="number"
				value={ value }
				max={ max }
				onChange={ ( e ) => onChange( e.target.value ) }
			/>
			<p>{ help }</p>
		</div>
	),
} ) );

jest.mock( '@src/helpers/editor-settings', () => ( {
	getFromSettings: jest.fn(),
} ) );

/**
 * Points the mocked `core/editor` selectors at the given editor state.
 *
 * @since TBD
 *
 * @param {boolean} isNewEvent Whether the editor holds a clean new post.
 * @param {Object}  meta       The post meta the editor returns.
 *
 * @return {void}
 */
const mockEditor = ( isNewEvent, meta ) => {
	useSelect.mockImplementation( ( callback ) =>
		callback( () => ( {
			isCleanNewPost: () => isNewEvent,
			getEditedPostAttribute: () => meta,
		} ) ),
	);
};

const LABEL = 'Maximum Number of Guests';

describe( 'GuestLimit component', () => {
	const mockEditPost = jest.fn();
	const mockUnlockPostSaving = jest.fn();

	beforeEach( () => {
		jest.clearAllMocks();
		useDispatch.mockReturnValue( {
			editPost: mockEditPost,
			unlockPostSaving: mockUnlockPostSaving,
		} );
	} );

	it( 'shows the stored limit for an existing event without saving', () => {
		mockEditor( false, { gatherpress_guest_limit: 3 } );

		render( <GuestLimit /> );

		expect( screen.getByLabelText( LABEL ) ).toHaveValue( 3 );
		expect( screen.getByLabelText( LABEL ) ).toHaveAttribute( 'max', '5' );
		expect( getFromSettings ).not.toHaveBeenCalled();
		expect( mockEditPost ).not.toHaveBeenCalled();
	} );

	it( 'shows 0 when an existing event stores false', () => {
		mockEditor( false, { gatherpress_guest_limit: false } );

		render( <GuestLimit /> );

		expect( screen.getByLabelText( LABEL ) ).toHaveValue( 0 );
		expect( mockEditPost ).not.toHaveBeenCalled();
	} );

	it( 'uses and saves the sitewide default for a new event', () => {
		mockEditor( true, { gatherpress_guest_limit: 0 } );
		getFromSettings.mockReturnValue( 2 );

		render( <GuestLimit /> );

		expect( getFromSettings ).toHaveBeenCalledWith( 'guestLimit' );
		expect( screen.getByLabelText( LABEL ) ).toHaveValue( 2 );
		expect( mockEditPost ).toHaveBeenCalledWith( {
			meta: { gatherpress_guest_limit: 2 },
		} );
		expect( mockUnlockPostSaving ).toHaveBeenCalledTimes( 1 );
	} );

	it( 'saves nothing for a new event when the sitewide default is false', () => {
		mockEditor( true, { gatherpress_guest_limit: 0 } );
		getFromSettings.mockReturnValue( false );

		render( <GuestLimit /> );

		expect( screen.getByLabelText( LABEL ) ).toHaveValue( 0 );
		expect( mockEditPost ).not.toHaveBeenCalled();
	} );

	it( 'saves the new value as a number and unlocks saving on change', () => {
		mockEditor( false, { gatherpress_guest_limit: 0 } );

		render( <GuestLimit /> );
		fireEvent.change( screen.getByLabelText( LABEL ), {
			target: { value: '4' },
		} );

		expect( screen.getByLabelText( LABEL ) ).toHaveValue( 4 );
		expect( mockEditPost ).toHaveBeenCalledWith( {
			meta: { gatherpress_guest_limit: 4 },
		} );
		expect( mockUnlockPostSaving ).toHaveBeenCalledTimes( 1 );
	} );
} );
