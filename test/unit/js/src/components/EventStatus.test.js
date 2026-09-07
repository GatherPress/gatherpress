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
	SelectControl: ( { label, value, onChange, options, help } ) => (
		<div>
			<label htmlFor="mock-event-status-select">{ label }</label>
			<select
				id="mock-event-status-select"
				value={ value }
				onChange={ ( e ) => onChange( e.target.value ) }
			>
				{ ( options || [] ).map( ( option ) => (
					<option key={ option.value } value={ option.value }>
						{ option.label }
					</option>
				) ) }
			</select>
			<p>{ help }</p>
		</div>
	),
} ) );

// The vocabulary PHP publishes through the editor settings, which the status
// helper reads. Stated here so these tests exercise the real lookup.
const mockStatuses = {
	scheduled: {
		label: 'Scheduled',
		description: 'Event is planned and confirmed to take place.',
	},
	canceled: { label: 'Canceled', description: 'Event will not take place.' },
	postponed: { label: 'Postponed', description: 'Event is delayed.' },
	rescheduled: {
		label: 'Rescheduled',
		description: 'Event date and time have been changed.',
	},
	moved: { label: 'Moved', description: 'Event is taking place elsewhere.' },
};

jest.mock( '@src/helpers/editor-settings', () => ( {
	getFromConfig: ( key ) =>
		'eventStatuses' === key ? mockStatuses : undefined,
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
				getEditedPostAttribute: () => undefined,
			} ) )
		);

		render( <EventStatus /> );

		const select = screen.getByLabelText( 'Event status' );
		expect( select ).toBeInTheDocument();
		expect( select.value ).toBe( 'scheduled' );
	} );

	it( 'renders with stored canceled status', () => {
		useSelect.mockImplementation( ( callback ) =>
			callback( () => ( {
				getEditedPostAttribute: () => 'canceled',
			} ) )
		);

		render( <EventStatus /> );

		const select = screen.getByLabelText( 'Event status' );
		expect( select.value ).toBe( 'canceled' );
	} );

	it( 'dispatches editPost and unlockPostSaving on status change', () => {
		useSelect.mockImplementation( ( callback ) =>
			callback( () => ( {
				getEditedPostAttribute: () => 'scheduled',
			} ) )
		);

		render( <EventStatus /> );

		const select = screen.getByLabelText( 'Event status' );
		fireEvent.change( select, { target: { value: 'postponed' } } );

		expect( mockEditPost ).toHaveBeenCalledWith( {
			gatherpress_status: 'postponed',
		} );
		expect( mockUnlockPostSaving ).toHaveBeenCalledTimes( 1 );
	} );
} );
