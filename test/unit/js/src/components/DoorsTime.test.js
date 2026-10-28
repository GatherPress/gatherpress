/**
 * External dependencies
 */
import { render, screen, fireEvent } from '@testing-library/react';
import { describe, expect, it, jest, beforeEach } from '@jest/globals';
import '@testing-library/jest-dom';

/**
 * Internal dependencies
 */
import DoorsTime from '@src/components/DoorsTime';

let mockMeta = {};
const mockEditPost = jest.fn();
const mockUnlockPostSaving = jest.fn();

jest.mock( '@wordpress/data', () => ( {
	useSelect: ( selector ) =>
		selector( () => ( { getEditedPostAttribute: () => mockMeta } ) ),
	useDispatch: () => ( {
		editPost: mockEditPost,
		unlockPostSaving: mockUnlockPostSaving,
	} ),
} ) );

jest.mock( '@wordpress/components', () => ( {
	TextControl: ( { label, value, onChange } ) => (
		<input
			type="time"
			aria-label={ label }
			value={ value }
			onChange={ ( event ) => onChange( event.target.value ) }
		/>
	),
} ) );

describe( 'DoorsTime', () => {
	beforeEach( () => {
		mockMeta = {};
		mockEditPost.mockClear();
		mockUnlockPostSaving.mockClear();
	} );

	it( 'shows the stored times', () => {
		mockMeta = {
			gatherpress_doors_open: '18:30',
			gatherpress_doors_close: '23:00',
		};

		render( <DoorsTime /> );

		expect( screen.getByLabelText( 'Doors open' ) ).toHaveValue( '18:30' );
		expect( screen.getByLabelText( 'Doors close' ) ).toHaveValue( '23:00' );
	} );

	it( 'shows empty fields when no meta is set', () => {
		mockMeta = undefined;

		render( <DoorsTime /> );

		expect( screen.getByLabelText( 'Doors open' ) ).toHaveValue( '' );
		expect( screen.getByLabelText( 'Doors close' ) ).toHaveValue( '' );
	} );

	it( 'writes each time to its own meta key', () => {
		render( <DoorsTime /> );

		fireEvent.change( screen.getByLabelText( 'Doors open' ), {
			target: { value: '19:00' },
		} );
		fireEvent.change( screen.getByLabelText( 'Doors close' ), {
			target: { value: '22:15' },
		} );

		expect( mockEditPost ).toHaveBeenCalledWith( {
			meta: { gatherpress_doors_open: '19:00' },
		} );
		expect( mockEditPost ).toHaveBeenCalledWith( {
			meta: { gatherpress_doors_close: '22:15' },
		} );
		expect( mockUnlockPostSaving ).toHaveBeenCalledTimes( 2 );
	} );

	it( 'clears a time', () => {
		mockMeta = { gatherpress_doors_open: '18:30' };

		render( <DoorsTime /> );

		fireEvent.change( screen.getByLabelText( 'Doors open' ), {
			target: { value: '' },
		} );

		expect( mockEditPost ).toHaveBeenCalledWith( {
			meta: { gatherpress_doors_open: '' },
		} );
	} );
} );
