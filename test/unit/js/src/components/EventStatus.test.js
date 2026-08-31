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
import EventStatus from '@src/components/EventStatus';

jest.mock( '@wordpress/data', () => ( {
	useDispatch: jest.fn(),
	useSelect: jest.fn(),
} ) );

jest.mock( '@wordpress/components', () => ( {
	SelectControl: ( { label, value, onChange, children } ) => (
		<div>
			<label htmlFor="mock-event-status-select">{ label }</label>
			<select
				id="mock-event-status-select"
				value={ value }
				onChange={ ( e ) => onChange( e.target.value ) }
			>
				{ children }
			</select>
		</div>
	),
} ) );

describe( 'EventStatus component', () => {
	const mockEditPost = jest.fn();
	const mockUnlockPostSaving = jest.fn();

	beforeEach( () => {
		jest.clearAllMocks();
		useDispatch.mockReturnValue( {
			editPost: mockEditPost,
			unlockPostSaving: mockUnlockPostSaving,
		} );
	} );

	it( 'renders with default scheduled status', () => {
		useSelect.mockImplementation( ( callback ) =>
			callback( () => ( {
				getEditedPostAttribute: () => ( {} ),
			} ) )
		);

		render( <EventStatus /> );

		const select = screen.getByLabelText( 'Event status' );
		expect( select ).toBeInTheDocument();
		expect( select.value ).toBe( 'scheduled' );
	} );

	it( 'renders with stored cancelled status', () => {
		useSelect.mockImplementation( ( callback ) =>
			callback( () => ( {
				getEditedPostAttribute: () => ( {
					gatherpress_status: 'cancelled',
				} ),
			} ) )
		);

		render( <EventStatus /> );

		const select = screen.getByLabelText( 'Event status' );
		expect( select.value ).toBe( 'cancelled' );
	} );

	it( 'dispatches editPost and unlockPostSaving on status change', () => {
		useSelect.mockImplementation( ( callback ) =>
			callback( () => ( {
				getEditedPostAttribute: () => ( {
					gatherpress_status: 'scheduled',
				} ),
			} ) )
		);

		render( <EventStatus /> );

		const select = screen.getByLabelText( 'Event status' );
		fireEvent.change( select, { target: { value: 'postponed' } } );

		expect( mockEditPost ).toHaveBeenCalledWith( {
			meta: { gatherpress_status: 'postponed' },
		} );
		expect( mockUnlockPostSaving ).toHaveBeenCalledTimes( 1 );
	} );
} );
