/**
 * External dependencies
 */
import { describe, expect, it, jest, beforeEach } from '@jest/globals';
import { fireEvent, render, screen } from '@testing-library/react';
import '@testing-library/jest-dom';
import moment from 'moment';
import 'moment-timezone';

/**
 * WordPress dependencies
 */
import { useDispatch, useSelect } from '@wordpress/data';

/**
 * Internal dependencies
 */
import RepeatSchedule from '@src/components/RepeatSchedule';

// A Proxy replaces only the two hooks, since the helpers need the real store.
jest.mock( '@wordpress/data', () => {
	const mockActual = jest.requireActual( '@wordpress/data' );
	const mockHooks = {
		useDispatch: jest.fn(),
		useSelect: jest.fn(),
	};

	return new Proxy( mockActual, {
		get: ( target, prop ) =>
			prop in mockHooks ? mockHooks[ prop ] : target[ prop ],
	} );
} );

jest.mock( '@wordpress/components', () => ( {
	PanelRow: ( { children } ) => <div>{ children }</div>,
	Notice: ( { children } ) => <div role="note">{ children }</div>,
	SelectControl: ( { label, value, options, onChange } ) => (
		<select aria-label={ label } value={ value } onChange={ ( e ) => onChange( e.target.value ) }>
			{ options.map( ( option ) => (
				<option key={ option.value } value={ option.value }>
					{ option.label }
				</option>
			) ) }
		</select>
	),
	TextControl: ( { label, value, type, onChange } ) => (
		<input
			aria-label={ label }
			type={ type }
			value={ value }
			onChange={ ( e ) => onChange( e.target.value ) }
		/>
	),
	CheckboxControl: ( { label, checked, disabled, onChange } ) => (
		<input
			type="checkbox"
			aria-label={ label }
			checked={ checked }
			disabled={ disabled }
			onChange={ ( e ) => onChange( e.target.checked ) }
		/>
	),
	RadioControl: ( { label, selected, options, onChange } ) => (
		<fieldset aria-label={ label }>
			{ options.map( ( option ) => (
				<input
					key={ option.value }
					type="radio"
					aria-label={ option.label }
					checked={ selected === option.value }
					onChange={ () => onChange( option.value ) }
				/>
			) ) }
		</fieldset>
	),
} ) );

const past = JSON.stringify( { dateTimeStart: '2020-01-06 19:00:00', timezone: 'UTC' } );
const future = JSON.stringify( { dateTimeStart: '2099-01-05 19:00:00', timezone: 'UTC' } );
const weekly = {
	frequency: 'WEEKLY',
	interval: 1,
	weekdays: [ 'MO' ],
	month_day: null,
	ordinal_weekday: null,
	ends_on: null,
	times: [ '19:00' ],
	duration: 90,
	starts_on: '2020-01-06',
};

/**
 * Points the mocked stores at an editor state.
 *
 * @since TBD
 *
 * @param {Object} state The saved and edited field, status, saved JSON, start and all-day flag.
 *
 * @return {void}
 */
const mockEditor = ( state ) => {
	const {
		saved = null,
		edited = saved,
		status = 'draft',
		json = future,
		start = '2099-01-05 19:00:00',
		isAllDay = false,
	} = state;
	const current = {
		gatherpress_repeat: saved,
		status,
		meta: { gatherpress_datetime: json },
	};

	useSelect.mockImplementation( ( callback ) =>
		callback( ( store ) =>
			'core/editor' === store
				? {
					getCurrentPostAttribute: ( key ) => current[ key ],
					getEditedPostAttribute: ( key ) =>
						'meta' === key ? { gatherpress_is_all_day: isAllDay } : edited,
				}
				: {
					getDateTimeStart: () => start,
					getTimezone: () => 'UTC',
				},
		),
	);
};

describe( 'RepeatSchedule component', () => {
	const mockEditPost = jest.fn();
	const lastSaved = () => mockEditPost.mock.calls.at( -1 )[ 0 ].gatherpress_repeat;

	beforeEach( () => {
		jest.clearAllMocks();
		useDispatch.mockReturnValue( { editPost: mockEditPost } );
	} );

	it( 'leaves a schedule it can\'t show alone, with a note', () => {
		mockEditor( { saved: { pattern: weekly, showable: false } } );
		render( <RepeatSchedule /> );

		expect( screen.getByRole( 'note' ) ).toHaveTextContent( 'can\'t be changed here' );
		expect( screen.queryByLabelText( 'Repeats' ) ).not.toBeInTheDocument();
	} );

	it( 'starts a draft repeating from its start date, without a change date', () => {
		mockEditor( { saved: { pattern: null, showable: true } } );
		render( <RepeatSchedule /> );

		expect( screen.getByText( 'Does not repeat.' ) ).toBeInTheDocument();

		fireEvent.change( screen.getByLabelText( 'Repeats' ), { target: { value: 'WEEKLY' } } );

		// January 5, 2099 is a Monday.
		expect( lastSaved() ).toEqual( {
			pattern: expect.objectContaining( { frequency: 'WEEKLY', weekdays: [ 'MO' ] } ),
		} );
	} );

	it( 'edits a draft\'s interval, weekdays and end date', () => {
		mockEditor( { saved: { pattern: weekly, showable: true } } );
		render( <RepeatSchedule /> );

		expect( screen.getByText( 'Every week on Monday.' ) ).toBeInTheDocument();
		expect( screen.getByLabelText( 'Monday' ) ).toBeDisabled();

		fireEvent.change( screen.getByLabelText( 'Every how many weeks' ), { target: { value: '0' } } );
		expect( lastSaved().pattern.interval ).toBe( 1 );

		fireEvent.change( screen.getByLabelText( 'Every how many weeks' ), { target: { value: '2' } } );
		expect( lastSaved().pattern.interval ).toBe( 2 );

		fireEvent.click( screen.getByLabelText( 'Friday' ) );
		expect( lastSaved().pattern.weekdays ).toEqual( [ 'MO', 'FR' ] );

		fireEvent.click( screen.getByLabelText( 'On a date' ) );
		expect( lastSaved().pattern.ends_on ).toBe( '2099-01-05' );

		fireEvent.change( screen.getByLabelText( 'Repeats' ), { target: { value: '' } } );
		expect( lastSaved() ).toEqual( { pattern: null } );
	} );

	it( 'reads back an unsaved edit, and changes or clears its end date', () => {
		const edited = { pattern: { ...weekly, weekdays: [ 'MO', 'FR' ], ends_on: '2099-03-01' } };

		mockEditor( { saved: { pattern: weekly, showable: true }, edited } );
		render( <RepeatSchedule /> );

		expect( screen.getByLabelText( 'Friday' ) ).toBeChecked();
		expect( screen.getByLabelText( 'Monday' ) ).not.toBeDisabled();

		fireEvent.click( screen.getByLabelText( 'Monday' ) );
		expect( lastSaved().pattern.weekdays ).toEqual( [ 'FR' ] );

		fireEvent.change( screen.getByLabelText( 'End date' ), { target: { value: '2099-04-01' } } );
		expect( lastSaved().pattern.ends_on ).toBe( '2099-04-01' );

		fireEvent.change( screen.getByLabelText( 'End date' ), { target: { value: '' } } );
		expect( lastSaved().pattern.ends_on ).toBeNull();

		fireEvent.click( screen.getByLabelText( 'Never' ) );
		expect( lastSaved().pattern.ends_on ).toBeNull();
	} );

	it( 'chooses how a monthly pattern picks its day', () => {
		const monthly = { ...weekly, frequency: 'MONTHLY', weekdays: [], month_day: 5 };

		mockEditor( { saved: { pattern: monthly, showable: true } } );
		render( <RepeatSchedule /> );

		// January 5, 2099 is the first Monday.
		fireEvent.click( screen.getByLabelText( 'On the first Monday' ) );
		expect( lastSaved().pattern ).toEqual(
			expect.objectContaining( { month_day: null, ordinal_weekday: [ 1, 'MO' ] } ),
		);
	} );

	it( 'changes a started series from tomorrow by default, with a new time and length', () => {
		const tomorrow = moment().tz( 'UTC' ).add( 1, 'day' ).format( 'YYYY-MM-DD' );

		mockEditor( { saved: { pattern: weekly, showable: true }, status: 'publish', json: past } );
		render( <RepeatSchedule /> );

		expect( screen.getByText( /The first date has passed/ ) ).toBeInTheDocument();
		expect( screen.getByLabelText( 'Changes apply from' ) ).toHaveValue( tomorrow );

		fireEvent.change( screen.getByLabelText( 'Time from then on' ), { target: { value: '20:00' } } );
		expect( lastSaved() ).toEqual( { pattern: expect.objectContaining( { times: [ '20:00' ] } ), from: tomorrow } );

		fireEvent.change( screen.getByLabelText( 'Time from then on' ), { target: { value: '' } } );
		expect( lastSaved().pattern.times ).toEqual( [ '19:00' ] );

		fireEvent.change( screen.getByLabelText( 'Length from then on, in minutes' ), { target: { value: '60' } } );
		expect( lastSaved().pattern.duration ).toBe( 60 );

		fireEvent.change( screen.getByLabelText( 'Length from then on, in minutes' ), { target: { value: '' } } );
		expect( lastSaved().pattern.duration ).toBe( 0 );

		fireEvent.change( screen.getByLabelText( 'Changes apply from' ), { target: { value: '2099-06-01' } } );
		expect( lastSaved().from ).toBe( '2099-06-01' );

		fireEvent.change( screen.getByLabelText( 'Changes apply from' ), { target: { value: '2000-01-01' } } );
		expect( lastSaved().from ).toBe( tomorrow );
	} );

	it( 'keeps a started all-day series\' change date, without a time', () => {
		const edited = { pattern: { ...weekly, times: undefined, duration: undefined }, from: '2099-06-01' };

		mockEditor( {
			saved: { pattern: weekly, showable: true },
			edited,
			status: 'publish',
			json: past,
			isAllDay: true,
		} );
		render( <RepeatSchedule /> );

		expect( screen.getByLabelText( 'Changes apply from' ) ).toHaveValue( '2099-06-01' );
		expect( screen.queryByLabelText( 'Time from then on' ) ).not.toBeInTheDocument();
	} );

	it( 'says when a series ended, and shows a scheduled change', () => {
		mockEditor( {
			saved: { pattern: { ...weekly, ends_on: '2020-03-02' }, ended: true, showable: true },
			status: 'publish',
			json: past,
		} );
		const { unmount } = render( <RepeatSchedule /> );

		expect( screen.getByText( /This series ended on/ ) ).toBeInTheDocument();
		expect( screen.getByLabelText( 'Repeats' ) ).toHaveValue( '' );
		unmount();

		mockEditor( {
			saved: {
				pattern: weekly,
				scheduled: { ...weekly, weekdays: [ 'TH' ], starts_on: '2099-02-05' },
				showable: true,
			},
		} );
		render( <RepeatSchedule /> );

		expect( screen.getByText( /From .*: Every week on Thursday\./ ) ).toBeInTheDocument();
	} );
	it( 'reads an edit without a pattern as not repeating, and blank time and length fields', () => {
		mockEditor( { saved: { pattern: weekly, showable: true }, edited: {} } );
		const { unmount } = render( <RepeatSchedule /> );

		expect( screen.getByLabelText( 'Repeats' ) ).toHaveValue( '' );
		unmount();

		const edited = { pattern: { ...weekly, times: undefined, duration: undefined } };

		mockEditor( { saved: { pattern: weekly, showable: true }, edited, status: 'publish', json: past } );
		render( <RepeatSchedule /> );

		expect( screen.getByLabelText( 'Time from then on' ) ).toHaveValue( '' );
		expect( screen.getByLabelText( 'Length from then on, in minutes' ) ).toHaveValue( null );
	} );
} );
