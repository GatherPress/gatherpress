/**
 * External dependencies
 */
import { render, fireEvent, screen } from '@testing-library/react';
import { beforeEach, describe, expect, jest, test } from '@jest/globals';
import '@testing-library/jest-dom';

/**
 * WordPress dependencies
 */
jest.mock( '@wordpress/i18n', () => ( {
	__: ( str ) => str,
	_n: ( single, plural, number ) => ( 1 === number ? single : plural ),
	sprintf: ( format, ...args ) =>
		format.replace( /%d/g, () => String( args.shift() ) ),
} ) );

// The two children that talk to the server are stubbed here and tested in
// their own suites. They are asynchronous by nature, and leaving them live
// would make every synchronous assertion in this file race a fetch.
jest.mock( '@src/panels/event-settings/recurrence/apply-scope', () => ( {
	__esModule: true,
	default: ( { postId } ) => <div data-testid="apply-scope">{ postId }</div>,
} ) );

jest.mock( '@src/panels/event-settings/recurrence/rsvp-impact', () => ( {
	__esModule: true,
	default: ( { postId, rule } ) => (
		<div data-testid="rsvp-impact" data-post-id={ postId }>
			{ JSON.stringify( rule ) }
		</div>
	),
} ) );

jest.mock( '@wordpress/data', () => ( {
	useSelect: jest.fn(),
	useDispatch: jest.fn(),
} ) );

jest.mock( '@wordpress/components', () => ( {
	PanelRow: ( { children } ) => <div>{ children }</div>,
	ToggleControl: ( { label, checked, disabled, onChange } ) => (
		<input
			aria-label={ label }
			type="checkbox"
			checked={ checked }
			disabled={ disabled }
			onChange={ ( event ) => onChange( event.target.checked ) }
		/>
	),
	CheckboxControl: ( { label, checked, onChange } ) => (
		<input
			aria-label={ label }
			type="checkbox"
			checked={ checked }
			onChange={ ( event ) => onChange( event.target.checked ) }
		/>
	),
	SelectControl: ( { label, value, options, onChange } ) => (
		<select
			aria-label={ label }
			value={ value }
			onChange={ ( event ) => onChange( event.target.value ) }
		>
			{ options.map( ( option ) => (
				<option key={ option.value } value={ option.value }>
					{ option.label }
				</option>
			) ) }
		</select>
	),
	TextControl: ( { label, value, onChange, type } ) => (
		<input
			aria-label={ label }
			type={ type }
			value={ value }
			onChange={ ( event ) => onChange( event.target.value ) }
		/>
	),
	__experimentalNumberControl: ( { label, value, onChange } ) => (
		<input
			aria-label={ label }
			type="number"
			value={ value }
			onChange={ ( event ) => onChange( event.target.value ) }
		/>
	),
} ) );

jest.mock( '@src/helpers/event', () => ( {
	usePostTypeSupports: jest.fn( () => true ),
} ) );

/**
 * Internal dependencies
 */
import { useDispatch, useSelect } from '@wordpress/data';
import { usePostTypeSupports } from '@src/helpers/event';
import RecurrencePanel from '@src/panels/event-settings/recurrence';

/**
 * Build a `select()` stand-in returning the given recurrence meta blob and
 * timezone, matching what `core/editor` and `gatherpress/datetime` expose.
 *
 * @param {string|undefined} recurrenceMeta Raw `gatherpress_recurrence` meta value.
 * @param {string}           timezone       Timezone value from the datetime store.
 * @param {number}           postId         Current post ID from the editor store.
 *
 * @return {Function} A `select( storeName )` stand-in.
 */
function makeSelect( recurrenceMeta, timezone = 'America/New_York', postId = 42 ) {
	return ( storeName ) => {
		if ( 'core/editor' === storeName ) {
			return {
				getCurrentPostId: () => postId,
				getEditedPostAttribute: () => ( {
					gatherpress_recurrence: recurrenceMeta,
				} ),
			};
		}

		if ( 'gatherpress/datetime' === storeName ) {
			return { getTimezone: () => timezone };
		}

		return undefined;
	};
}

let editPost;

/**
 * Parse the recurrence blob from the last `editPost` call.
 *
 * @return {Object|string} The parsed blob, or '' when the call cleared it.
 */
function lastPersistedBlob() {
	const call = editPost.mock.calls.at( -1 );
	const raw = call[ 0 ].meta.gatherpress_recurrence;

	return '' === raw ? '' : JSON.parse( raw );
}

/**
 * The repair-state message shown for a stored rule the server would reject.
 *
 * @type {string}
 */
const INVALID_STORED_MESSAGE =
	'The stored repeat rule for this event is invalid, so the event does not repeat. Turn on Repeat to author a new rule.';

/**
 * The message shown while an authored count rule exceeds the expander budget.
 *
 * @type {string}
 */
const BUDGET_MESSAGE =
	'That many repeats at this interval covers too long a span. Reduce the number of occurrences or the interval.';

/**
 * Serialize a complete stored recurrence blob with the given overrides.
 *
 * @param {Object} overrides Field overrides.
 *
 * @return {string} The serialized blob.
 */
function storedBlob( overrides = {} ) {
	return JSON.stringify( {
		frequency: 'daily',
		interval: 1,
		weekdays: [],
		monthly_mode: 'day_of_month',
		monthly_day: 1,
		monthly_ordinal: 1,
		monthly_weekday: 1,
		end_type: 'never',
		until: '',
		count: 0,
		...overrides,
	} );
}

/**
 * Mount the panel over a stored recurrence blob.
 *
 * @param {string} blob Raw `gatherpress_recurrence` meta value.
 *
 * @return {Object} The render result.
 */
function renderWithStoredBlob( blob ) {
	useSelect.mockImplementation( ( selector ) =>
		selector( makeSelect( blob ) ),
	);

	return render( <RecurrencePanel /> );
}

/**
 * Assert the panel presents the repair state for a server-rejected stored
 * rule: repeat off, the explanatory message shown, and nothing written back.
 *
 * A rejected blob leaves the post non-recurring server-side
 * (`Meta::write_recurrence()` clears the mirrors and the projection when
 * `Rule::from_array()` returns null), so an enabled presentation would
 * document a recurrence storage does not hold. Not writing matters too: the
 * panel must not silently "repair" meta the user has not touched.
 *
 * @return {void}
 */
function expectRepairState() {
	expect( screen.getByLabelText( 'Repeat' ) ).not.toBeChecked();
	expect( screen.getByText( INVALID_STORED_MESSAGE ) ).toBeInTheDocument();
	expect( editPost ).not.toHaveBeenCalled();
}

beforeEach( () => {
	jest.clearAllMocks();
	editPost = jest.fn();
	useDispatch.mockReturnValue( { editPost } );
	usePostTypeSupports.mockReturnValue( true );
	useSelect.mockImplementation( ( selector ) =>
		selector( makeSelect( undefined ) ),
	);
} );

describe( 'RecurrencePanel', () => {
	test( 'renders nothing when the post type does not support gatherpress-event-date', () => {
		usePostTypeSupports.mockReturnValue( false );

		const { container } = render( <RecurrencePanel /> );

		expect( container ).toBeEmptyDOMElement();
	} );

	test( 'writes an empty blob when the repeat toggle is off', () => {
		render( <RecurrencePanel /> );

		expect( editPost ).not.toHaveBeenCalled();

		fireEvent.click( screen.getByLabelText( 'Repeat' ) );
		fireEvent.click( screen.getByLabelText( 'Repeat' ) );

		expect( lastPersistedBlob() ).toBe( '' );
	} );

	test( 'writes a sensible default blob when the repeat toggle is turned on', () => {
		render( <RecurrencePanel /> );

		fireEvent.click( screen.getByLabelText( 'Repeat' ) );

		expect( lastPersistedBlob() ).toEqual( {
			frequency: 'daily',
			interval: 1,
			weekdays: [],
			monthly_mode: 'day_of_month',
			monthly_day: 1,
			monthly_ordinal: 1,
			monthly_weekday: 1,
			end_type: 'never',
			until: '',
			count: 0,
		} );
	} );

	test( 'round-trips a weekly interval-2 Tue/Thu rule ending on a date', () => {
		render( <RecurrencePanel /> );

		fireEvent.click( screen.getByLabelText( 'Repeat' ) );
		fireEvent.change( screen.getByLabelText( 'Frequency' ), {
			target: { value: 'weekly' },
		} );
		fireEvent.change( screen.getByLabelText( 'Repeat every' ), {
			target: { value: '2' },
		} );
		fireEvent.click( screen.getByLabelText( 'Tuesday' ) );
		fireEvent.click( screen.getByLabelText( 'Thursday' ) );
		fireEvent.change( screen.getByLabelText( 'Ends' ), {
			target: { value: 'until' },
		} );
		fireEvent.change( screen.getByLabelText( 'End date' ), {
			target: { value: '2026-12-31' },
		} );

		expect( lastPersistedBlob() ).toEqual( {
			frequency: 'weekly',
			interval: 2,
			weekdays: [ 2, 4 ],
			monthly_mode: 'day_of_month',
			monthly_day: 1,
			monthly_ordinal: 1,
			monthly_weekday: 1,
			end_type: 'until',
			until: '2026-12-31',
			count: 0,
		} );
	} );

	test( 'round-trips a monthly day-of-month-15 rule', () => {
		render( <RecurrencePanel /> );

		fireEvent.click( screen.getByLabelText( 'Repeat' ) );
		fireEvent.change( screen.getByLabelText( 'Frequency' ), {
			target: { value: 'monthly' },
		} );
		fireEvent.change( screen.getByLabelText( 'Day of the month' ), {
			target: { value: '15' },
		} );

		const blob = lastPersistedBlob();

		expect( blob.frequency ).toBe( 'monthly' );
		expect( blob.monthly_mode ).toBe( 'day_of_month' );
		expect( blob.monthly_day ).toBe( 15 );
		expect( blob.weekdays ).toEqual( [] );
	} );

	test( 'round-trips a monthly last-Wednesday rule', () => {
		render( <RecurrencePanel /> );

		fireEvent.click( screen.getByLabelText( 'Repeat' ) );
		fireEvent.change( screen.getByLabelText( 'Frequency' ), {
			target: { value: 'monthly' },
		} );
		fireEvent.change( screen.getByLabelText( 'Repeat by' ), {
			target: { value: 'nth_weekday' },
		} );
		fireEvent.change( screen.getByLabelText( 'Week' ), {
			target: { value: '-1' },
		} );
		fireEvent.change( screen.getByLabelText( 'Day' ), {
			target: { value: '3' },
		} );

		const blob = lastPersistedBlob();

		expect( blob.frequency ).toBe( 'monthly' );
		expect( blob.monthly_mode ).toBe( 'nth_weekday' );
		expect( blob.monthly_ordinal ).toBe( -1 );
		expect( blob.monthly_weekday ).toBe( 3 );
	} );

	test( 'does not leak weekday selection into monthly, or back again', () => {
		render( <RecurrencePanel /> );

		fireEvent.click( screen.getByLabelText( 'Repeat' ) );
		fireEvent.change( screen.getByLabelText( 'Frequency' ), {
			target: { value: 'weekly' },
		} );
		fireEvent.click( screen.getByLabelText( 'Tuesday' ) );
		fireEvent.click( screen.getByLabelText( 'Thursday' ) );

		expect( lastPersistedBlob().weekdays ).toEqual( [ 2, 4 ] );

		fireEvent.change( screen.getByLabelText( 'Frequency' ), {
			target: { value: 'monthly' },
		} );

		expect( lastPersistedBlob().weekdays ).toEqual( [] );

		fireEvent.change( screen.getByLabelText( 'Day of the month' ), {
			target: { value: '20' },
		} );

		fireEvent.change( screen.getByLabelText( 'Frequency' ), {
			target: { value: 'weekly' },
		} );

		// Switching back to weekly with no weekday selected yet is withheld. The
		// last persisted blob is still the monthly one until a weekday is chosen,
		// proving no leak either way once that happens.
		expect( lastPersistedBlob().monthly_day ).toBe( 20 );

		fireEvent.click( screen.getByLabelText( 'Monday' ) );

		const blob = lastPersistedBlob();

		expect( blob.weekdays ).toEqual( [ 1 ] );
		expect( blob.monthly_day ).toBe( 1 );
	} );

	test( 'switching to Yearly clears weekly and monthly state and hides their controls', () => {
		render( <RecurrencePanel /> );

		fireEvent.click( screen.getByLabelText( 'Repeat' ) );
		fireEvent.change( screen.getByLabelText( 'Frequency' ), {
			target: { value: 'weekly' },
		} );
		fireEvent.click( screen.getByLabelText( 'Tuesday' ) );

		expect( lastPersistedBlob().weekdays ).toEqual( [ 2 ] );

		fireEvent.change( screen.getByLabelText( 'Frequency' ), {
			target: { value: 'monthly' },
		} );
		fireEvent.change( screen.getByLabelText( 'Day of the month' ), {
			target: { value: '20' },
		} );

		expect( lastPersistedBlob().monthly_day ).toBe( 20 );

		fireEvent.change( screen.getByLabelText( 'Frequency' ), {
			target: { value: 'yearly' },
		} );

		const blob = lastPersistedBlob();

		expect( blob.frequency ).toBe( 'yearly' );
		expect( blob.weekdays ).toEqual( [] );
		expect( blob.monthly_day ).toBe( 1 );
		expect( blob.monthly_mode ).toBe( 'day_of_month' );
		expect( blob.monthly_ordinal ).toBe( 1 );
		expect( blob.monthly_weekday ).toBe( 1 );

		// The weekly/monthly-specific controls must not remain mounted once
		// the frequency has moved on to Yearly.
		expect( screen.queryByLabelText( 'Tuesday' ) ).not.toBeInTheDocument();
		expect(
			screen.queryByLabelText( 'Day of the month' ),
		).not.toBeInTheDocument();
	} );

	test( 'never persists both an end date and a count, either direction', () => {
		render( <RecurrencePanel /> );

		fireEvent.click( screen.getByLabelText( 'Repeat' ) );
		fireEvent.change( screen.getByLabelText( 'Ends' ), {
			target: { value: 'until' },
		} );
		fireEvent.change( screen.getByLabelText( 'End date' ), {
			target: { value: '2026-06-15' },
		} );

		let blob = lastPersistedBlob();
		expect( blob.until ).toBe( '2026-06-15' );
		expect( blob.count ).toBe( 0 );

		fireEvent.change( screen.getByLabelText( 'Ends' ), {
			target: { value: 'count' },
		} );
		fireEvent.change( screen.getByLabelText( 'Number of occurrences' ), {
			target: { value: '10' },
		} );

		blob = lastPersistedBlob();
		expect( blob.count ).toBe( 10 );
		expect( blob.until ).toBe( '' );

		// Switching back to "On date" with no date chosen yet leaves the rule
		// incomplete (`end_type: 'until'` with an empty `until`, which
		// `Rule::is_valid_end_shape()` rejects). The panel withholds the
		// write rather than persisting it, so the last known-good blob (the
		// count-10 shape) is still what is on the post, and a message tells
		// the organizer why.
		fireEvent.change( screen.getByLabelText( 'Ends' ), {
			target: { value: 'until' },
		} );

		blob = lastPersistedBlob();
		expect( blob.count ).toBe( 10 );
		expect( blob.until ).toBe( '' );
		expect(
			screen.getByText( 'Choose an end date to save this recurrence.' ),
		).toBeInTheDocument();

		fireEvent.change( screen.getByLabelText( 'End date' ), {
			target: { value: '2026-06-15' },
		} );

		blob = lastPersistedBlob();
		expect( blob.until ).toBe( '2026-06-15' );
		expect( blob.count ).toBe( 0 );

		fireEvent.change( screen.getByLabelText( 'Ends' ), {
			target: { value: 'never' },
		} );

		blob = lastPersistedBlob();
		expect( blob.end_type ).toBe( 'never' );
		expect( blob.count ).toBe( 0 );
		expect( blob.until ).toBe( '' );
	} );

	test( 'withholds the write when "On date" is chosen with no date yet', () => {
		render( <RecurrencePanel /> );

		fireEvent.click( screen.getByLabelText( 'Repeat' ) );
		fireEvent.change( screen.getByLabelText( 'Ends' ), {
			target: { value: 'until' },
		} );

		expect( lastPersistedBlob().end_type ).toBe( 'never' );
		expect(
			screen.getByText( 'Choose an end date to save this recurrence.' ),
		).toBeInTheDocument();
	} );

	test( 'withholds the write when "After" is chosen with no count yet', () => {
		render( <RecurrencePanel /> );

		fireEvent.click( screen.getByLabelText( 'Repeat' ) );
		fireEvent.change( screen.getByLabelText( 'Ends' ), {
			target: { value: 'count' },
		} );

		expect( lastPersistedBlob().end_type ).toBe( 'never' );
		expect(
			screen.getByText( 'Enter how many times this event repeats.' ),
		).toBeInTheDocument();
	} );

	test( 'surfaces a validation message when weekly has no weekday selected', () => {
		render( <RecurrencePanel /> );

		fireEvent.click( screen.getByLabelText( 'Repeat' ) );
		fireEvent.change( screen.getByLabelText( 'Frequency' ), {
			target: { value: 'weekly' },
		} );

		expect(
			screen.getByText( 'Select at least one day of the week.' ),
		).toBeInTheDocument();

		fireEvent.click( screen.getByLabelText( 'Monday' ) );

		expect(
			screen.queryByText( 'Select at least one day of the week.' ),
		).not.toBeInTheDocument();
	} );

	test( 'clamps interval 0 and negative input to 1', () => {
		render( <RecurrencePanel /> );

		fireEvent.click( screen.getByLabelText( 'Repeat' ) );

		fireEvent.change( screen.getByLabelText( 'Repeat every' ), {
			target: { value: '0' },
		} );
		expect( lastPersistedBlob().interval ).toBe( 1 );

		fireEvent.change( screen.getByLabelText( 'Repeat every' ), {
			target: { value: '-5' },
		} );
		expect( lastPersistedBlob().interval ).toBe( 1 );
	} );

	test( 'clamps interval 53 to 52', () => {
		render( <RecurrencePanel /> );

		fireEvent.click( screen.getByLabelText( 'Repeat' ) );

		fireEvent.change( screen.getByLabelText( 'Repeat every' ), {
			target: { value: '53' },
		} );
		expect( lastPersistedBlob().interval ).toBe( 52 );
	} );

	test.each( [
		[ '0', 1 ],
		[ '32', 31 ],
		[ '-1', 1 ],
		[ '1.9', 1 ],
		[ '31.9', 31 ],
	] )(
		'normalizes typed monthly day %s to %i rather than persisting it',
		( typed, expected ) => {
			render( <RecurrencePanel /> );

			fireEvent.click( screen.getByLabelText( 'Repeat' ) );
			fireEvent.change( screen.getByLabelText( 'Frequency' ), {
				target: { value: 'monthly' },
			} );
			fireEvent.change( screen.getByLabelText( 'Day of the month' ), {
				target: { value: typed },
			} );

			expect( lastPersistedBlob().monthly_day ).toBe( expected );
		},
	);

	test.each( [ [ '' ], [ '31abc' ], [ 'not a day' ] ] )(
		'withholds the write and warns when the monthly day reads %s',
		( typed ) => {
			render( <RecurrencePanel /> );

			fireEvent.click( screen.getByLabelText( 'Repeat' ) );
			fireEvent.change( screen.getByLabelText( 'Frequency' ), {
				target: { value: 'monthly' },
			} );

			const writesBefore = editPost.mock.calls.length;

			fireEvent.change( screen.getByLabelText( 'Day of the month' ), {
				target: { value: typed },
			} );

			// No write at all, so the last known-good blob stays on the post.
			expect( editPost.mock.calls ).toHaveLength( writesBefore );
			expect( lastPersistedBlob().monthly_day ).toBe( 1 );
			expect(
				screen.getByText(
					'Enter a day of the month between 1 and 31.',
				),
			).toBeInTheDocument();
		},
	);

	test( 'recovers and persists once a valid monthly day is typed again', () => {
		render( <RecurrencePanel /> );

		fireEvent.click( screen.getByLabelText( 'Repeat' ) );
		fireEvent.change( screen.getByLabelText( 'Frequency' ), {
			target: { value: 'monthly' },
		} );
		fireEvent.change( screen.getByLabelText( 'Day of the month' ), {
			target: { value: '' },
		} );

		expect( screen.getByLabelText( 'Day of the month' ) ).toHaveValue(
			null,
		);

		fireEvent.change( screen.getByLabelText( 'Day of the month' ), {
			target: { value: '15' },
		} );

		expect( lastPersistedBlob().monthly_day ).toBe( 15 );
		expect(
			screen.queryByText(
				'Enter a day of the month between 1 and 31.',
			),
		).not.toBeInTheDocument();
	} );

	test( 'leaves an out-of-range monthly day alone while the mode is nth_weekday', () => {
		render( <RecurrencePanel /> );

		fireEvent.click( screen.getByLabelText( 'Repeat' ) );
		fireEvent.change( screen.getByLabelText( 'Frequency' ), {
			target: { value: 'monthly' },
		} );
		fireEvent.change( screen.getByLabelText( 'Repeat by' ), {
			target: { value: 'nth_weekday' },
		} );
		fireEvent.change( screen.getByLabelText( 'Week' ), {
			target: { value: '2' },
		} );

		// The day field is not rendered in this mode, so there is nothing to
		// warn about and the write proceeds.
		expect( lastPersistedBlob().monthly_ordinal ).toBe( 2 );
		expect(
			screen.queryByText(
				'Enter a day of the month between 1 and 31.',
			),
		).not.toBeInTheDocument();
	} );

	test( 'shows the repair state for a stored monthly day the server rejects', () => {
		// The server agreement this pins: `Rule::from_array()` rejects any
		// monthly day above 31 (class-test-rule.php) and
		// `Meta::write_recurrence()` clears every mirror and the projection
		// for the rejected blob (class-test-meta.php), so the post is not
		// recurring. Clamping 99 to 31 here would present an enabled rule
		// that storage does not hold.
		renderWithStoredBlob(
			storedBlob( { frequency: 'monthly', monthly_day: 99 } ),
		);

		expectRepairState();
		expect(
			screen.queryByLabelText( 'Day of the month' ),
		).not.toBeInTheDocument();
	} );

	test( 'shows the repair state for a stored blob with no usable monthly day', () => {
		// PHP casts 'the fifteenth' to 0, and a day-of-month rule with day 0
		// fails `Rule::is_valid_monthly_shape()`: rejected, not warned about.
		renderWithStoredBlob(
			storedBlob( {
				frequency: 'monthly',
				monthly_day: 'the fifteenth',
			} ),
		);

		expectRepairState();
	} );

	test( 'clamps count 731 to 730', () => {
		render( <RecurrencePanel /> );

		fireEvent.click( screen.getByLabelText( 'Repeat' ) );
		fireEvent.change( screen.getByLabelText( 'Ends' ), {
			target: { value: 'count' },
		} );
		fireEvent.change( screen.getByLabelText( 'Number of occurrences' ), {
			target: { value: '731' },
		} );

		expect( lastPersistedBlob().count ).toBe( 730 );
	} );

	test( 'refuses recurrence and shows a message on a fixed-offset timezone', () => {
		useSelect.mockImplementation( ( selector ) =>
			selector( makeSelect( undefined, 'UTC+5:30' ) ),
		);

		render( <RecurrencePanel /> );

		expect(
			screen.getByText(
				'Recurring events require a named timezone (e.g. America/New_York) rather than a fixed UTC offset. Change the timezone in the Date & Time panel to enable repeat.',
			),
		).toBeInTheDocument();

		const toggle = screen.getByLabelText( 'Repeat' );
		expect( toggle ).toBeDisabled();
	} );

	test( 'keeps refusing edits when recurrence was already enabled and the timezone becomes fixed-offset', () => {
		const existingBlob = JSON.stringify( {
			frequency: 'daily',
			interval: 1,
			weekdays: [],
			monthly_mode: 'day_of_month',
			monthly_day: 1,
			monthly_ordinal: 1,
			monthly_weekday: 1,
			end_type: 'never',
			until: '',
			count: 0,
		} );

		useSelect.mockImplementation( ( selector ) =>
			selector( makeSelect( existingBlob, 'UTC-8:00' ) ),
		);

		render( <RecurrencePanel /> );

		expect( screen.getByLabelText( 'Repeat' ) ).toBeDisabled();
		expect(
			screen.queryByLabelText( 'Frequency' ),
		).not.toBeInTheDocument();
	} );

	test( 'treats a missing timezone as not fixed-offset', () => {
		useSelect.mockImplementation( ( selector ) =>
			selector( makeSelect( undefined, undefined ) ),
		);

		render( <RecurrencePanel /> );

		expect( screen.getByLabelText( 'Repeat' ) ).not.toBeDisabled();
	} );

	test( 'falls back to the default, disabled state when the meta is malformed JSON', () => {
		useSelect.mockImplementation( ( selector ) =>
			selector( makeSelect( '{not valid json' ) ),
		);

		render( <RecurrencePanel /> );

		expect( screen.getByLabelText( 'Repeat' ) ).not.toBeChecked();
		expect( screen.queryByLabelText( 'Frequency' ) ).not.toBeInTheDocument();
	} );

	test( 'falls back to the default, disabled state when the meta decodes to a non-object', () => {
		useSelect.mockImplementation( ( selector ) =>
			selector( makeSelect( '"just a string"' ) ),
		);

		render( <RecurrencePanel /> );

		expect( screen.getByLabelText( 'Repeat' ) ).not.toBeChecked();
	} );

	test( 'restores a previously stored, valid recurrence blob as enabled', () => {
		const existingBlob = JSON.stringify( {
			frequency: 'weekly',
			interval: 3,
			weekdays: [ 1, 5 ],
			monthly_mode: 'day_of_month',
			monthly_day: 1,
			monthly_ordinal: 1,
			monthly_weekday: 1,
			end_type: 'never',
			until: '',
			count: 0,
		} );

		useSelect.mockImplementation( ( selector ) =>
			selector( makeSelect( existingBlob ) ),
		);

		render( <RecurrencePanel /> );

		expect( screen.getByLabelText( 'Repeat' ) ).toBeChecked();
		expect( screen.getByLabelText( 'Frequency' ) ).toHaveValue( 'weekly' );
		expect( screen.getByLabelText( 'Repeat every' ) ).toHaveValue( 3 );
	} );

	test( 'shows the repair state for a stored non-array weekdays value on a weekly rule', () => {
		// `Rule::from_array()` coerces a non-array to an empty weekday list,
		// and a weekly rule with no weekdays fails `Rule::is_valid()`:
		// server-side the blob is rejected outright, so presenting it as an
		// enabled weekly rule waiting for a day would misstate storage.
		renderWithStoredBlob(
			storedBlob( { frequency: 'weekly', weekdays: 3 } ),
		);

		expectRepairState();
	} );

	test( 'shows the repair state for stored out-of-range weekday values', () => {
		// The server keeps every intval-coerced weekday and rejects the rule
		// when any falls outside 0 through 6; it never drops just the bad
		// ones. Filtering to Tuesday and Thursday here would show a rule the
		// server refused to expand.
		renderWithStoredBlob(
			storedBlob( { frequency: 'weekly', weekdays: [ 2, 9, -1, 4 ] } ),
		);

		expectRepairState();
	} );

	test( 're-syncs from meta once the post entity resolves after mount, without clobbering it via the toggle', () => {
		useSelect.mockImplementation( ( selector ) =>
			selector( makeSelect( undefined ) ),
		);

		const { rerender } = render( <RecurrencePanel /> );

		expect( screen.getByLabelText( 'Repeat' ) ).not.toBeChecked();

		const existingBlob = JSON.stringify( {
			frequency: 'weekly',
			interval: 3,
			weekdays: [ 1, 5 ],
			monthly_mode: 'day_of_month',
			monthly_day: 1,
			monthly_ordinal: 1,
			monthly_weekday: 1,
			end_type: 'never',
			until: '',
			count: 0,
		} );

		// The entity resolves after mount, for example on a slow fetch, or when
		// the site editor mounts the panel before `core/editor` has the post.
		useSelect.mockImplementation( ( selector ) =>
			selector( makeSelect( existingBlob ) ),
		);
		rerender( <RecurrencePanel /> );

		expect( screen.getByLabelText( 'Repeat' ) ).toBeChecked();
		expect( screen.getByLabelText( 'Frequency' ) ).toHaveValue( 'weekly' );
	} );

	test( 're-syncs when the post changes under a byte-identical blob', () => {
		// Gutenberg can change the current post without unmounting this
		// panel, which the occurrences panel in this same directory already
		// documents and guards against. Two posts can also carry
		// byte-identical blobs, because the same weekly Tuesday schedule
		// serializes to the same string.
		//
		// Comparing only the string, the switch looked like "our own write
		// came back" and the sync effect returned early, leaving post A's
		// withheld local edit on screen as though it were post B's rule. From
		// there, checking one more weekday could persist A's stale rule onto
		// B.
		const sharedBlob = storedBlob( {
			frequency: 'weekly',
			weekdays: [ 2 ],
		} );

		useSelect.mockImplementation( ( selector ) =>
			selector( makeSelect( sharedBlob, 'America/New_York', 1 ) ),
		);

		const { rerender } = render( <RecurrencePanel /> );

		expect( screen.getByLabelText( 'Tuesday' ) ).toBeChecked();

		// Unchecking the only weekday is a rule the server would reject, so
		// the panel keeps it in local state and withholds the write. That is
		// the state that must not survive the post switch.
		fireEvent.click( screen.getByLabelText( 'Tuesday' ) );

		expect( screen.getByLabelText( 'Tuesday' ) ).not.toBeChecked();
		expect( editPost ).not.toHaveBeenCalled();

		// Same blob, different post.
		useSelect.mockImplementation( ( selector ) =>
			selector( makeSelect( sharedBlob, 'America/New_York', 2 ) ),
		);
		rerender( <RecurrencePanel /> );

		expect( screen.getByLabelText( 'Tuesday' ) ).toBeChecked();
		expect( editPost ).not.toHaveBeenCalled();
	} );

	// Every test in this block pins the write side of the same agreement the
	// stored-blob block below pins for the read side: the panel must never
	// persist a blob `Rule::from_array()` rejects, because the rejection
	// clears every mirror and the projection while surfacing nothing in the
	// editor. The write-side predicate is the read-side one, so the two can
	// never disagree again.
	describe( 'write-side server-schema agreement', () => {
		/**
		 * Author a weekly rule through the controls up to its end condition.
		 *
		 * @param {string} interval Value typed into "Repeat every".
		 *
		 * @return {void}
		 */
		function authorWeeklyCountRule( interval ) {
			fireEvent.click( screen.getByLabelText( 'Repeat' ) );
			fireEvent.change( screen.getByLabelText( 'Frequency' ), {
				target: { value: 'weekly' },
			} );
			fireEvent.click( screen.getByLabelText( 'Monday' ) );
			fireEvent.change( screen.getByLabelText( 'Repeat every' ), {
				target: { value: interval },
			} );
			fireEvent.change( screen.getByLabelText( 'Ends' ), {
				target: { value: 'count' },
			} );
			fireEvent.change( screen.getByLabelText( 'Number of occurrences' ), {
				target: { value: '730' },
			} );
		}

		test( 'withholds a within-control-range rule whose iteration budget the server refuses', () => {
			render( <RecurrencePanel /> );

			authorWeeklyCountRule( '52' );

			// 730 x 7 x 52 + 366 = 266,086 iterations, above
			// `Expander::MAX_ITERATIONS` (200,000), even though every control
			// value is inside its own advertised min/max. Persisting the blob
			// would silently disable the series server-side, so the write is
			// withheld: the last known-good blob still carries the end
			// condition authored before the count landed.
			const blob = lastPersistedBlob();

			expect( blob.end_type ).toBe( 'never' );
			expect( blob.count ).toBe( 0 );
			expect( screen.getByText( BUDGET_MESSAGE ) ).toBeInTheDocument();
		} );

		test( 'persists and clears the budget message once the interval shrinks', () => {
			render( <RecurrencePanel /> );

			authorWeeklyCountRule( '52' );

			fireEvent.change( screen.getByLabelText( 'Repeat every' ), {
				target: { value: '1' },
			} );

			// 730 x 7 x 1 + 366 = 5,476 iterations, inside the budget: the
			// same count now persists and the message withdraws.
			const blob = lastPersistedBlob();

			expect( blob.count ).toBe( 730 );
			expect( blob.interval ).toBe( 1 );
			expect( blob.end_type ).toBe( 'count' );
			expect(
				screen.queryByText( BUDGET_MESSAGE ),
			).not.toBeInTheDocument();
		} );

		test( 'withholds an end date the server cannot parse rather than persisting it', () => {
			render( <RecurrencePanel /> );

			fireEvent.click( screen.getByLabelText( 'Repeat' ) );
			fireEvent.change( screen.getByLabelText( 'Ends' ), {
				target: { value: 'until' },
			} );
			// Typing `20` into Chrome's year field yields `0020`, a valid HTML
			// date value the control hands over as-is, and a truthy `until`.
			// The server's reading disagrees: `Date.UTC` maps year 20 to 1920,
			// so the round-trip check fails exactly as `Rule::from_array()`'s
			// strict `!Y-m-d` parse does, and the blob would be rejected.
			fireEvent.change( screen.getByLabelText( 'End date' ), {
				target: { value: '0020-01-15' },
			} );

			const blob = lastPersistedBlob();

			expect( blob.end_type ).toBe( 'never' );
			expect( blob.until ).toBe( '' );
			expect(
				screen.getByText(
					'Choose an end date to save this recurrence.',
				),
			).toBeInTheDocument();
		} );
	} );

	// Every test in this block pins one arm of the client-side equivalent of
	// the server schema (`Rule::from_array()` plus `Rule::is_valid()`): a
	// stored blob the server rejects must never present as an enabled rule,
	// and a stored blob the server accepts, including through its coercions,
	// must present exactly as the server reads it.
	describe( 'stored blob server-schema agreement', () => {
		test( 'rejects an unknown frequency', () => {
			renderWithStoredBlob( storedBlob( { frequency: 'hourly' } ) );

			expectRepairState();
		} );

		test( 'rejects an interval above the authoring cap of 52', () => {
			renderWithStoredBlob( storedBlob( { interval: 53 } ) );

			expectRepairState();
		} );

		test( 'rejects an unknown end type', () => {
			renderWithStoredBlob( storedBlob( { end_type: 'eventually' } ) );

			expectRepairState();
		} );

		test( 'rejects a weekly rule with an empty weekday list', () => {
			renderWithStoredBlob(
				storedBlob( { frequency: 'weekly', weekdays: [] } ),
			);

			expectRepairState();
		} );

		test( 'rejects an unknown monthly mode', () => {
			renderWithStoredBlob(
				storedBlob( { frequency: 'monthly', monthly_mode: 'weird' } ),
			);

			expectRepairState();
		} );

		test( 'rejects an nth-weekday rule with an out-of-range ordinal', () => {
			renderWithStoredBlob(
				storedBlob( {
					frequency: 'monthly',
					monthly_mode: 'nth_weekday',
					monthly_ordinal: 5,
				} ),
			);

			expectRepairState();
		} );

		test( 'rejects an nth-weekday rule with an out-of-range weekday', () => {
			renderWithStoredBlob(
				storedBlob( {
					frequency: 'monthly',
					monthly_mode: 'nth_weekday',
					monthly_weekday: 7,
				} ),
			);

			expectRepairState();
		} );

		test( 'rejects an end date that does not parse as a date', () => {
			renderWithStoredBlob(
				storedBlob( { end_type: 'until', until: 'soon' } ),
			);

			expectRepairState();
		} );

		test( 'rejects an end date that rolls over an invalid calendar day', () => {
			// PHP's strict `!Y-m-d` parse flags 2026-02-31 with a warning
			// rather than rolling it into March, and `from_array()` treats
			// the warning as no date at all.
			renderWithStoredBlob(
				storedBlob( { end_type: 'until', until: '2026-02-31' } ),
			);

			expectRepairState();
		} );

		test( 'rejects an until rule that also carries a count', () => {
			renderWithStoredBlob(
				storedBlob( {
					end_type: 'until',
					until: '2026-12-31',
					count: 5,
				} ),
			);

			expectRepairState();
		} );

		test( 'rejects a count rule that also carries a parseable end date', () => {
			renderWithStoredBlob(
				storedBlob( {
					end_type: 'count',
					count: 5,
					until: '2026-12-31',
				} ),
			);

			expectRepairState();
		} );

		test( 'rejects a count rule with a zero count', () => {
			renderWithStoredBlob(
				storedBlob( { end_type: 'count', count: 0 } ),
			);

			expectRepairState();
		} );

		test( 'rejects a count above the authoring cap of 730', () => {
			renderWithStoredBlob(
				storedBlob( { end_type: 'count', count: 731 } ),
			);

			expectRepairState();
		} );

		test( 'rejects a never-ending rule that still carries a count', () => {
			renderWithStoredBlob( storedBlob( { count: 3 } ) );

			expectRepairState();
		} );

		test( 'rejects a never-ending rule that still carries an end date', () => {
			renderWithStoredBlob( storedBlob( { until: '2026-12-31' } ) );

			expectRepairState();
		} );

		test( 'rejects a count rule whose iteration budget the expander refuses', () => {
			// 730 weekly occurrences at interval 52 cost
			// 730 * 7 * 52 + 366 = 266,086 iterations, above
			// `Expander::MAX_ITERATIONS` (200,000): `is_valid_end_shape()`
			// rejects it even though count and interval are each in range.
			renderWithStoredBlob(
				storedBlob( {
					frequency: 'weekly',
					weekdays: [ 1 ],
					interval: 52,
					end_type: 'count',
					count: 730,
				} ),
			);

			expectRepairState();
		} );

		test( 'rejects an empty stored object', () => {
			// With every field missing, the server reads an empty frequency
			// and end type and rejects the rule.
			renderWithStoredBlob( JSON.stringify( {} ) );

			expectRepairState();
		} );

		test( 'rejects a stored partial blob missing its end type', () => {
			// An older or hand-written blob without `end_type` reads as ''
			// server-side, which `Rule::is_valid()` rejects. Filling in a
			// default here would enable a rule the server cleared.
			renderWithStoredBlob(
				JSON.stringify( { frequency: 'daily', interval: 1 } ),
			);

			expectRepairState();
		} );

		test( 'accepts a within-budget count rule at the same caps', () => {
			// The daily counterpart of the budget rejection above:
			// 730 * 1 * 52 + 366 = 38,326, inside the budget.
			renderWithStoredBlob(
				storedBlob( {
					end_type: 'count',
					count: 730,
					interval: 52,
				} ),
			);

			expect( screen.getByLabelText( 'Repeat' ) ).toBeChecked();
			expect(
				screen.queryByText( INVALID_STORED_MESSAGE ),
			).not.toBeInTheDocument();
		} );

		test( 'accepts stringified weekdays the way the server casts them', () => {
			// PHP maps `intval` over the list, so a JSON array of numeric
			// strings is the same weekly rule to the server.
			renderWithStoredBlob(
				storedBlob( { frequency: 'weekly', weekdays: [ '3' ] } ),
			);

			expect( screen.getByLabelText( 'Repeat' ) ).toBeChecked();
			expect( screen.getByLabelText( 'Wednesday' ) ).toBeChecked();
		} );

		test( 'accepts a sub-one interval by clamping it to 1 like the server', () => {
			renderWithStoredBlob( storedBlob( { interval: 0 } ) );

			expect( screen.getByLabelText( 'Repeat' ) ).toBeChecked();
			expect( screen.getByLabelText( 'Repeat every' ) ).toHaveValue( 1 );
		} );

		test( 'refuses a fractional interval, which the server refuses too', () => {
			// `Rule::to_int()` accepts an integer or a canonical integer
			// string and nothing else, so 2.9 is not interval 2 to the
			// server, it is a rejected rule. Truncating here presented an
			// enabled schedule for a blob whose mirrors and projection the
			// server had already cleared.
			renderWithStoredBlob( storedBlob( { interval: 2.9 } ) );

			expectRepairState();
		} );

		test( 'refuses stored booleans, which the server refuses too', () => {
			// `to_int()` never reads a boolean as a number, so neither of
			// these is a value the server would accept.
			renderWithStoredBlob(
				storedBlob( {
					interval: false,
					end_type: 'count',
					count: true,
				} ),
			);

			expectRepairState();
		} );

		test( 'accepts a stringified count the way the server casts it', () => {
			renderWithStoredBlob(
				storedBlob( { end_type: 'count', count: '5' } ),
			);

			expect( screen.getByLabelText( 'Repeat' ) ).toBeChecked();
			expect(
				screen.getByLabelText( 'Number of occurrences' ),
			).toHaveValue( 5 );
		} );

		test( 'keeps a valid stored end date in the control', () => {
			renderWithStoredBlob(
				storedBlob( { end_type: 'until', until: '2026-12-31' } ),
			);

			expect( screen.getByLabelText( 'Repeat' ) ).toBeChecked();
			expect( screen.getByLabelText( 'End date' ) ).toHaveValue(
				'2026-12-31',
			);
		} );

		test( 'refuses a count rule carrying an unparsable end date', () => {
			// `from_array()` decides both of its `until` rejections on the
			// raw field's presence, before anything parses it: a nonempty
			// `until` that does not parse is rejected rather than erased, and
			// that check is what stops an unparsable date slipping a count
			// rule past RFC 5545's ban on carrying both.
			renderWithStoredBlob(
				storedBlob( {
					end_type: 'count',
					count: 5,
					until: 'garbage',
				} ),
			);

			expectRepairState();
		} );

		test( 'refuses a junk day field even on an nth-weekday rule', () => {
			// `is_valid_monthly_shape()` does check only the active mode's
			// companions, but `from_array()` runs `to_int()` over all five
			// integer fields before any shape check, and one null rejects the
			// whole rule. So an unused `monthly_day` is not dead weight.
			// Measured against the production PHP: rejected.
			renderWithStoredBlob(
				storedBlob( {
					frequency: 'monthly',
					monthly_mode: 'nth_weekday',
					monthly_ordinal: 2,
					monthly_weekday: 3,
					monthly_day: 'x',
				} ),
			);

			expectRepairState();
		} );

		test( 'clears the repair state the moment the user authors a fresh rule', () => {
			renderWithStoredBlob(
				storedBlob( { frequency: 'monthly', monthly_day: 99 } ),
			);

			expectRepairState();

			fireEvent.click( screen.getByLabelText( 'Repeat' ) );

			expect(
				screen.queryByText( INVALID_STORED_MESSAGE ),
			).not.toBeInTheDocument();
			expect( screen.getByLabelText( 'Repeat' ) ).toBeChecked();
			expect( lastPersistedBlob().frequency ).toBe( 'daily' );
		} );
	} );

	test( 'renders no apply-scope chooser or impact notice while recurrence is off', () => {
		render( <RecurrencePanel /> );

		expect( screen.queryByTestId( 'apply-scope' ) ).not.toBeInTheDocument();
		expect( screen.queryByTestId( 'rsvp-impact' ) ).not.toBeInTheDocument();
	} );

	test( 'hands the current post and the live rule to the scope chooser and impact notice', () => {
		const blob = JSON.stringify( {
			frequency: 'weekly',
			interval: 2,
			weekdays: [ 2, 4 ],
			monthly_mode: 'day_of_month',
			monthly_day: 1,
			monthly_ordinal: 1,
			monthly_weekday: 1,
			end_type: 'count',
			until: '',
			count: 6,
		} );

		useSelect.mockImplementation( ( selector ) =>
			selector( makeSelect( blob ) ),
		);

		render( <RecurrencePanel /> );

		expect( screen.getByTestId( 'apply-scope' ) ).toHaveTextContent( '42' );
		expect( screen.getByTestId( 'rsvp-impact' ) ).toHaveAttribute(
			'data-post-id',
			'42',
		);
		expect(
			JSON.parse( screen.getByTestId( 'rsvp-impact' ).textContent ).count,
		).toBe( 6 );

		// The impact notice must see the rule as it is being edited, not the
		// rule as it was stored: an organizer shortening the series has to be
		// told about the RSVPs *before* they save.
		fireEvent.change( screen.getByLabelText( 'Repeat every' ), {
			target: { value: '4' },
		} );

		expect(
			JSON.parse( screen.getByTestId( 'rsvp-impact' ).textContent )
				.interval,
		).toBe( 4 );
	} );
} );
