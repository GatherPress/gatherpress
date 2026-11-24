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
import RescheduledOffer, { parseStart } from '@src/components/RescheduledOffer';
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
} ) );

/**
 * A `gatherpress_datetime` meta value.
 *
 * @param {string} start    The start, `YYYY-MM-DD HH:mm:ss`.
 * @param {string} timezone The time zone.
 *
 * @return {string} The JSON value.
 */
const datetime = ( start, timezone = 'UTC' ) =>
	JSON.stringify( { dateTimeStart: start, dateTimeEnd: start, timezone } );

describe( 'RescheduledOffer', () => {
	const editPost = jest.fn();
	const tomorrow = moment.utc().add( 1, 'day' ).format( 'YYYY-MM-DD HH:mm:ss' );
	const later = moment.utc().add( 2, 'days' ).format( 'YYYY-MM-DD HH:mm:ss' );
	let editor;

	beforeEach( () => {
		jest.clearAllMocks();

		editor = {
			postStatus: 'publish',
			status: undefined,
			saved: datetime( tomorrow ),
			edited: datetime( later ),
		};

		useDispatch.mockReturnValue( { editPost } );
		useSelect.mockImplementation( ( callback ) =>
			callback( () => ( {
				getCurrentPostAttribute: ( key ) => {
					if ( 'meta' !== key ) {
						return editor.postStatus;
					}

					return editor.noMeta
						? undefined
						: { gatherpress_datetime: editor.saved };
				},
				getEditedPostAttribute: ( key ) => {
					if ( 'meta' !== key ) {
						return editor.status;
					}

					return editor.noMeta
						? undefined
						: { gatherpress_datetime: editor.edited };
				},
			} ) ),
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
		editor.edited = datetime( tomorrow, 'Europe/Paris' );

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
		[ 'the event has no saved start', { saved: undefined } ],
		[ 'the event has no meta at all', { noMeta: true } ],
		[ 'the start did not move', { edited: datetime( tomorrow ) } ],
		[
			'the event has started',
			{
				saved: datetime(
					moment
						.utc()
						.subtract( 1, 'hour' )
						.format( 'YYYY-MM-DD HH:mm:ss' ),
				),
			},
		],
	] )( 'offers nothing when %s', ( _, state ) => {
		editor = { ...editor, ...state };

		const { container } = render( <RescheduledOffer /> );

		expect( container ).toBeEmptyDOMElement();
	} );

	it( 'reads the start and time zone, and survives a value it cannot read', () => {
		expect( parseStart( datetime( tomorrow, 'Europe/Paris' ) ) ).toEqual( {
			dateTimeStart: tomorrow,
			timezone: 'Europe/Paris',
		} );
		expect( parseStart( '{}' ) ).toEqual( { dateTimeStart: '', timezone: '' } );
		expect( parseStart( undefined ) ).toEqual( {
			dateTimeStart: '',
			timezone: '',
		} );
		expect( parseStart( 'not json' ) ).toEqual( {
			dateTimeStart: '',
			timezone: '',
		} );
	} );
} );
