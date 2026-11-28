/**
 * External dependencies
 */
import { describe, expect, it, jest, beforeEach } from '@jest/globals';
import { fireEvent, render, screen } from '@testing-library/react';
import '@testing-library/jest-dom';
import moment from 'moment';

/**
 * WordPress dependencies
 */
import { useDispatch, useSelect } from '@wordpress/data';

/**
 * Internal dependencies
 */
import RescheduledOffer from '@src/components/RescheduledOffer';
import { EVENT_STATUS_ID } from '@src/components/EventStatus';

jest.mock( '@wordpress/data', () => ( {
	useDispatch: jest.fn(),
	useSelect: jest.fn(),
} ) );

jest.mock( '@wordpress/components', () => ( {
	Notice: ( { children, actions } ) => (
		<div>
			<p>{ children }</p>
			{ actions.map( ( action ) => (
				<button key={ action.label } onClick={ action.onClick }>
					{ action.label }
				</button>
			) ) }
		</div>
	),
	PanelRow: ( { children } ) => <div>{ children }</div>,
	SelectControl: () => null,
} ) );

jest.mock( '@src/helpers/editor-settings', () => ( {
	getFromConfig: () => undefined,
} ) );

// The real helper module loads the core data stores. Its IANA branch is all
// the offer needs, and the saved values here always use one.
jest.mock( '@src/helpers/datetime', () => ( {
	createMomentWithTimezone: ( datetime, timezone ) =>
		jest.requireActual( 'moment-timezone' ).tz( datetime, timezone ),
	dateTimeDatabaseFormat: 'YYYY-MM-DD HH:mm:ss',
} ) );

describe( 'RescheduledOffer', () => {
	const editPost = jest.fn();
	const tomorrow = moment.utc().add( 1, 'day' ).format( 'YYYY-MM-DD HH:mm:ss' );
	const later = moment.utc().add( 2, 'days' ).format( 'YYYY-MM-DD HH:mm:ss' );
	let editor;

	beforeEach( () => {
		jest.clearAllMocks();

		// The saved values come from the derived meta, the edited ones from
		// the date store.
		editor = {
			postStatus: 'publish',
			status: undefined,
			savedStart: tomorrow,
			savedTimezone: 'UTC',
			editedStart: later,
			editedTimezone: 'UTC',
		};

		useDispatch.mockReturnValue( { editPost } );
		useSelect.mockImplementation( ( callback ) =>
			callback( ( store ) => {
				if ( 'gatherpress/datetime' === store ) {
					return {
						getDateTimeStart: () => editor.editedStart,
						getTimezone: () => editor.editedTimezone,
					};
				}

				return {
					getCurrentPostAttribute: ( key ) => {
						if ( 'meta' !== key ) {
							return editor.postStatus;
						}

						return editor.noMeta
							? undefined
							: {
								gatherpress_datetime_start: editor.savedStart,
								gatherpress_timezone: editor.savedTimezone,
							};
					},
					getEditedPostAttribute: () => editor.status,
				};
			} ),
		);
	} );

	it( 'offers Rescheduled when the start of a published event moves', () => {
		render( <RescheduledOffer /> );

		expect(
			screen.getByText(
				'The start changed. Do you want to set the event status to Rescheduled?',
			),
		).toBeInTheDocument();
	} );

	it( 'offers it when only the time zone moves', () => {
		editor.editedStart = tomorrow;
		editor.editedTimezone = 'Europe/Paris';

		render( <RescheduledOffer /> );

		expect(
			screen.getByRole( 'button', { name: 'Mark as Rescheduled' } ),
		).toBeInTheDocument();
	} );

	it( 'sets the status and keeps focus on the status select', () => {
		document.body.innerHTML = `<select id="${ EVENT_STATUS_ID }"></select>`;
		const select = document.getElementById( EVENT_STATUS_ID );
		const { getByRole } = render( <RescheduledOffer />, {
			container: document.body.appendChild( document.createElement( 'div' ) ),
		} );

		fireEvent.click( getByRole( 'button', { name: 'Mark as Rescheduled' } ) );

		expect( editPost ).toHaveBeenCalledWith( {
			gatherpress_status: 'rescheduled',
		} );
		expect( select ).toHaveFocus();
	} );

	it( 'still sets the status when the select is not on the page', () => {
		document.body.innerHTML = '';
		render( <RescheduledOffer /> );

		fireEvent.click(
			screen.getByRole( 'button', { name: 'Mark as Rescheduled' } ),
		);

		expect( editPost ).toHaveBeenCalledWith( {
			gatherpress_status: 'rescheduled',
		} );
	} );

	it.each( [
		[ 'the event is not published', { postStatus: 'draft' } ],
		[ 'the event already has another status', { status: 'canceled' } ],
		[ 'the event has no saved start', { savedStart: '' } ],
		[ 'the event has no meta at all', { noMeta: true } ],
		[ 'the start did not move', { editedStart: tomorrow } ],
		[
			'the same start is written in another format',
			{ editedStart: tomorrow.replace( ' ', 'T' ) },
		],
		[
			'the event has started',
			{
				savedStart: moment
					.utc()
					.subtract( 1, 'hour' )
					.format( 'YYYY-MM-DD HH:mm:ss' ),
			},
		],
	] )( 'offers nothing when %s', ( _, state ) => {
		editor = { ...editor, ...state };

		const { container } = render( <RescheduledOffer /> );

		expect( container ).toBeEmptyDOMElement();
	} );
} );
