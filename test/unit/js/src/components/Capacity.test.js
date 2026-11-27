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
import Capacity from '@src/components/Capacity';
import { getFromSettings } from '@src/helpers/editor-settings';

jest.mock( '@wordpress/data', () => ( {
	useDispatch: jest.fn(),
	useSelect: jest.fn(),
} ) );

jest.mock( '@wordpress/components', () => ( {
	__experimentalNumberControl: ( { label, value, onChange, help } ) => (
		<div>
			<label htmlFor="mock-capacity">{ label }</label>
			<input
				id="mock-capacity"
				type="number"
				value={ value }
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

describe( 'Capacity component', () => {
	const mockEditPost = jest.fn();
	const mockUnlockPostSaving = jest.fn();

	beforeEach( () => {
		jest.clearAllMocks();
		useDispatch.mockReturnValue( {
			editPost: mockEditPost,
			unlockPostSaving: mockUnlockPostSaving,
		} );
	} );

	it( 'shows the stored capacity for an existing event without saving', () => {
		mockEditor( false, { gatherpress_capacity: 50 } );

		render( <Capacity /> );

		expect( screen.getByLabelText( 'Capacity' ) ).toHaveValue( 50 );
		expect( getFromSettings ).not.toHaveBeenCalled();
		expect( mockEditPost ).not.toHaveBeenCalled();
	} );

	it( 'shows 0 when an existing event stores false', () => {
		mockEditor( false, { gatherpress_capacity: false } );

		render( <Capacity /> );

		expect( screen.getByLabelText( 'Capacity' ) ).toHaveValue( 0 );
		expect( mockEditPost ).not.toHaveBeenCalled();
	} );

	it( 'uses and saves the sitewide default for a new event', () => {
		mockEditor( true, { gatherpress_capacity: 0 } );
		getFromSettings.mockReturnValue( 20 );

		render( <Capacity /> );

		expect( getFromSettings ).toHaveBeenCalledWith( 'capacity' );
		expect( screen.getByLabelText( 'Capacity' ) ).toHaveValue( 20 );
		expect( mockEditPost ).toHaveBeenCalledWith( {
			meta: { gatherpress_capacity: 20 },
		} );
		expect( mockUnlockPostSaving ).toHaveBeenCalledTimes( 1 );
	} );

	it( 'saves nothing for a new event when the sitewide default is false', () => {
		mockEditor( true, { gatherpress_capacity: 0 } );
		getFromSettings.mockReturnValue( false );

		render( <Capacity /> );

		expect( screen.getByLabelText( 'Capacity' ) ).toHaveValue( 0 );
		expect( mockEditPost ).not.toHaveBeenCalled();
	} );

	it( 'saves the new value as a number and unlocks saving on change', () => {
		mockEditor( false, { gatherpress_capacity: 0 } );

		render( <Capacity /> );
		fireEvent.change( screen.getByLabelText( 'Capacity' ), {
			target: { value: '15' },
		} );

		expect( screen.getByLabelText( 'Capacity' ) ).toHaveValue( 15 );
		expect( mockEditPost ).toHaveBeenCalledWith( {
			meta: { gatherpress_capacity: 15 },
		} );
		expect( mockUnlockPostSaving ).toHaveBeenCalledTimes( 1 );
	} );
} );
