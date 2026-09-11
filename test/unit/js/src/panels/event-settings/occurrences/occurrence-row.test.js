/**
 * External dependencies
 */
import { fireEvent, render, screen } from '@testing-library/react';
import {
	afterEach,
	beforeEach,
	describe,
	expect,
	jest,
	test,
} from '@jest/globals';
import '@testing-library/jest-dom';

/**
 * WordPress dependencies
 */
jest.mock( '@wordpress/i18n', () => ( {
	__: ( str ) => str,
	sprintf: ( format, ...args ) =>
		args.reduce( ( carry, arg ) => carry.replace( '%s', arg ), format ),
} ) );

jest.mock( '@wordpress/components', () => ( {
	Button: ( {
		children,
		onClick,
		disabled,
		isBusy,
		'aria-label': ariaLabel,
	} ) => (
		<button
			type="button"
			onClick={ onClick }
			disabled={ disabled }
			data-busy={ !! isBusy }
			aria-label={ ariaLabel }
		>
			{ children }
		</button>
	),
} ) );

/**
 * Internal dependencies
 */
// `@wordpress/date` is deliberately NOT mocked here. The panel suite's
// `formatted:${ date }` stub is timezone-blind, so it can never catch the
// defect this file exists for: a timezone-free local datetime handed to
// `dateI18n()` is parsed in the browser timezone and then converted to the
// site timezone, printing a different wall-clock time than the occurrence's
// own. Only the real date implementation can fail on that.
//
// The browser timezone itself cannot be pinned per test file: Jest workers
// are threads, and a `process.env.TZ` write inside one never reaches the
// process-wide native timezone cache (measured: the UTC offset stays at the
// machine zone's). Determinism comes from the fixtures instead. The site
// timezone is pinned to Pacific/Kiritimati (UTC+14, no DST, no plausible
// developer or CI machine), so the browser-parse-then-site-convert defect
// renders a Kiritimati wall clock whatever the machine zone is, while every
// expected string below is derived only from the row's GMT instant and the
// row's own named timezone.
import { getSettings, setSettings } from '@wordpress/date';
import OccurrenceRow from '@src/panels/event-settings/occurrences/occurrence-row';

/**
 * Build an occurrence fixture, matching the `occurrences` REST route shape.
 *
 * The datetime facts describe one instant: 6:00 pm in New York on
 * 2026-08-21, which is 22:00 UTC and noon on August 22 in Kiritimati. The
 * three zones in play (browser, site, event) all disagree on the wall-clock
 * time, so a conversion through the wrong zone produces a visibly different
 * string.
 *
 * @param {Object} overrides Field overrides.
 *
 * @return {Object} An occurrence row.
 */
function occurrence( overrides = {} ) {
	return {
		series_post_id: 42,
		recurrence_id: '20260821T180000',
		datetime_start: '2026-08-21 18:00:00',
		datetime_start_gmt: '2026-08-21 22:00:00',
		datetime_end: '2026-08-21 20:00:00',
		datetime_end_gmt: '2026-08-22 00:00:00',
		timezone: 'America/New_York',
		status: 'scheduled',
		...overrides,
	};
}

const originalSettings = getSettings();

beforeEach( () => {
	setSettings( {
		...originalSettings,
		timezone: { string: 'Pacific/Kiritimati', offset: 14, abbr: '' },
	} );
} );

afterEach( () => {
	setSettings( originalSettings );
} );

describe( 'OccurrenceRow date rendering', () => {
	test( 'renders the occurrence wall-clock time in the event timezone, not the browser or site timezone', () => {
		render(
			<OccurrenceRow
				occurrence={ occurrence() }
				onToggle={ () => {} }
				isUpdating={ false }
			/>,
		);

		// 6:00 pm is the New York wall-clock time of the 22:00 UTC instant.
		// The site-timezone rendering would be August 22, 12:00 pm, and a
		// browser-timezone misparse lands on the machine zone's own clock.
		expect(
			screen.getByText( 'August 21, 2026 6:00 pm' ),
		).toBeInTheDocument();
	} );

	test( "honors each row's own timezone rather than one shared zone", () => {
		// 22:00 UTC is 7:00 am the NEXT day in Tokyo: same instant as the
		// default fixture, different named zone, different date and clock.
		render(
			<OccurrenceRow
				occurrence={ occurrence( { timezone: 'Asia/Tokyo' } ) }
				onToggle={ () => {} }
				isUpdating={ false }
			/>,
		);

		expect(
			screen.getByText( 'August 22, 2026 7:00 am' ),
		).toBeInTheDocument();
	} );

	test( 'keeps the calendar date of a near-midnight occurrence', () => {
		// 12:30 am in New York on Aug 22 is 4:30 am UTC on Aug 22: converting
		// through the wrong zone shifts the calendar date, not just the clock.
		render(
			<OccurrenceRow
				occurrence={ occurrence( {
					recurrence_id: '20260822T003000',
					datetime_start: '2026-08-22 00:30:00',
					datetime_start_gmt: '2026-08-22 04:30:00',
					datetime_end: '2026-08-22 02:30:00',
					datetime_end_gmt: '2026-08-22 06:30:00',
				} ) }
				onToggle={ () => {} }
				isUpdating={ false }
			/>,
		);

		expect(
			screen.getByText( 'August 22, 2026 12:30 am' ),
		).toBeInTheDocument();
	} );

	test( 'falls back to the site timezone when the timezone column is empty', () => {
		// The occurrence table's `timezone` column is nullable. With no event
		// zone to honor, the site zone (Kiritimati, noon on August 22) is the
		// correct rendering of the 22:00 UTC instant; only the instant itself
		// must never be reinterpreted through the browser zone.
		render(
			<OccurrenceRow
				occurrence={ occurrence( { timezone: '' } ) }
				onToggle={ () => {} }
				isUpdating={ false }
			/>,
		);

		expect(
			screen.getByText( 'August 22, 2026 12:00 pm' ),
		).toBeInTheDocument();
	} );
} );

describe( 'OccurrenceRow action button naming', () => {
	test( 'names the Cancel button after the occurrence it cancels', () => {
		render(
			<OccurrenceRow
				occurrence={ occurrence() }
				onToggle={ () => {} }
				isUpdating={ false }
			/>,
		);

		const button = screen.getByRole( 'button' );

		// The accessible name carries the date, and it opens with the
		// visible label so a spoken "Cancel" still matches the control
		// (WCAG 2.5.3, Label in Name).
		expect( button ).toHaveAccessibleName(
			'Cancel August 21, 2026 6:00 pm',
		);
		expect( button ).toHaveTextContent( 'Cancel' );
	} );

	test( 'names the Restore button after the occurrence it restores', () => {
		render(
			<OccurrenceRow
				occurrence={ occurrence( { status: 'canceled' } ) }
				onToggle={ () => {} }
				isUpdating={ false }
			/>,
		);

		const button = screen.getByRole( 'button' );

		expect( button ).toHaveAccessibleName(
			'Restore August 21, 2026 6:00 pm',
		);
		expect( button ).toHaveTextContent( 'Restore' );
	} );

	test( 'gives every row in a series a distinct destructive action name', () => {
		// The defect this covers only shows up in a list. A series renders
		// one row per occurrence, and a button named "Cancel" in all of them
		// leaves a screen reader's button list unable to say which meetup a
		// given control would cancel.
		render(
			<>
				<OccurrenceRow
					occurrence={ occurrence() }
					onToggle={ () => {} }
					isUpdating={ false }
				/>
				<OccurrenceRow
					occurrence={ occurrence( {
						recurrence_id: '20260828T180000',
						datetime_start: '2026-08-28 18:00:00',
						datetime_start_gmt: '2026-08-28 22:00:00',
						datetime_end: '2026-08-28 20:00:00',
						datetime_end_gmt: '2026-08-29 00:00:00',
					} ) }
					onToggle={ () => {} }
					isUpdating={ false }
				/>
			</>,
		);

		const names = screen
			.getAllByRole( 'button' )
			.map( ( button ) => button.getAttribute( 'aria-label' ) );

		expect( names ).toEqual( [
			'Cancel August 21, 2026 6:00 pm',
			'Cancel August 28, 2026 6:00 pm',
		] );
		expect( new Set( names ).size ).toBe( names.length );
	} );

	test( 'presses the row it names', () => {
		const onToggle = jest.fn();
		const row = occurrence();

		render(
			<OccurrenceRow
				occurrence={ row }
				onToggle={ onToggle }
				isUpdating={ false }
			/>,
		);

		fireEvent.click(
			screen.getByRole( 'button', {
				name: 'Cancel August 21, 2026 6:00 pm',
			} ),
		);

		expect( onToggle ).toHaveBeenCalledWith( row );
	} );
} );
