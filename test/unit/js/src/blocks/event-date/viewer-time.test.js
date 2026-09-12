/**
 * External dependencies
 */
import { describe, expect, it } from '@jest/globals';

/**
 * Internal dependencies
 */
import {
	formatInTimezone,
	getViewerTimeLabel,
	getViewerTimezone,
} from '@src/blocks/event-date/viewer-time';

describe( 'getViewerTimezone', () => {
	it( 'returns the browser timezone', () => {
		expect( getViewerTimezone() ).toEqual( expect.any( String ) );
	} );

	it( 'returns an empty string when Intl names no timezone', () => {
		const original = global.Intl;

		global.Intl = {
			DateTimeFormat: () => ( {
				resolvedOptions: () => ( {} ),
			} ),
		};

		try {
			expect( getViewerTimezone() ).toBe( '' );
		} finally {
			global.Intl = original;
		}
	} );

	it( 'returns an empty string when Intl refuses the call', () => {
		const original = global.Intl;

		global.Intl = {
			DateTimeFormat: () => {
				throw new Error( 'Intl is unavailable.' );
			},
		};

		try {
			expect( getViewerTimezone() ).toBe( '' );
		} finally {
			global.Intl = original;
		}
	} );
} );

describe( 'formatInTimezone', () => {
	it( 'formats a stored GMT datetime in the requested timezone', () => {
		expect(
			formatInTimezone(
				'2030-06-15 22:00:00',
				'America/New_York',
				{ hour: 'numeric', minute: '2-digit' },
				'en-US'
			)
		).toBe( '6:00 PM' );
	} );

	it( 'returns an empty string without a datetime or a timezone', () => {
		expect( formatInTimezone( '', 'America/New_York', {}, 'en-US' ) ).toBe( '' );
		expect( formatInTimezone( '2030-06-15 22:00:00', '', {}, 'en-US' ) ).toBe( '' );
	} );

	it( 'returns an empty string for an unparsable datetime', () => {
		expect(
			formatInTimezone( 'not a date', 'America/New_York', {}, 'en-US' )
		).toBe( '' );
	} );

	it( 'handles a manual UTC offset, which Intl accepts as a timezone', () => {
		expect(
			formatInTimezone(
				'2030-06-15 22:00:00',
				'+05:30',
				{ hour: 'numeric', minute: '2-digit' },
				'en-US'
			)
		).toBe( '3:30 AM' );
	} );

	it( 'returns an empty string for a timezone Intl will not take', () => {
		expect(
			formatInTimezone( '2030-06-15 22:00:00', 'Not/AZone', {}, 'en-US' )
		).toBe( '' );
	} );
} );

describe( 'getViewerTimeLabel', () => {
	it( 'reports the start time in the viewer timezone', () => {
		expect(
			getViewerTimeLabel( {
				startGmt: '2030-06-15 16:00:00',
				eventTimezone: 'America/New_York',
				viewerTimezone: 'Europe/Warsaw',
				locale: 'en-US',
			} )
		).toBe( '6:00 PM your time' );
	} );

	it( 'reports a range when an end is given', () => {
		expect(
			getViewerTimeLabel( {
				startGmt: '2030-06-15 16:00:00',
				endGmt: '2030-06-15 18:00:00',
				eventTimezone: 'America/New_York',
				viewerTimezone: 'Europe/Warsaw',
				locale: 'en-US',
			} )
		).toBe( '6:00 PM to 8:00 PM your time' );
	} );

	it( 'includes the date when the event lands on another day for the viewer', () => {
		const label = getViewerTimeLabel( {
			startGmt: '2030-06-15 22:00:00',
			eventTimezone: 'America/New_York',
			viewerTimezone: 'Asia/Tokyo',
			locale: 'en-US',
		} );

		expect( label ).toContain( '6/16/2030' );
		expect( label ).toContain( 'your time' );
	} );

	it( 'dates the end when it crosses midnight for the viewer', () => {
		// 18:00 to 20:00 in New York, which is 23:00 to 01:00 the next day in
		// London: without the date the end reads as an earlier time than the start.
		expect(
			getViewerTimeLabel( {
				startGmt: '2030-06-15 22:00:00',
				endGmt: '2030-06-16 00:00:00',
				eventTimezone: 'America/New_York',
				viewerTimezone: 'Europe/London',
				locale: 'en-US',
			} )
		).toBe( '11:00 PM to 6/16/2030, 1:00 AM your time' );
	} );

	it( 'dates the end of a multi-day event', () => {
		expect(
			getViewerTimeLabel( {
				startGmt: '2030-06-15 13:00:00',
				endGmt: '2030-06-17 21:00:00',
				eventTimezone: 'America/New_York',
				viewerTimezone: 'Europe/London',
				locale: 'en-US',
			} )
		).toBe( '2:00 PM to 6/17/2030, 10:00 PM your time' );
	} );

	it( 'leaves the end undated when it shares the viewer day with the start', () => {
		// The start carries a date here because the viewer is a day ahead of the
		// event, but the end still falls on the same viewer day as the start.
		expect(
			getViewerTimeLabel( {
				startGmt: '2030-06-15 22:00:00',
				endGmt: '2030-06-16 00:00:00',
				eventTimezone: 'America/New_York',
				viewerTimezone: 'Asia/Tokyo',
				locale: 'en-US',
			} )
		).toBe( '6/16/2030, 7:00 AM to 9:00 AM your time' );
	} );

	it( 'says nothing when a manual offset resolves to the viewer timezone', () => {
		expect(
			getViewerTimeLabel( {
				startGmt: '2030-06-15 22:00:00',
				endGmt: '2030-06-16 00:00:00',
				eventTimezone: '+05:30',
				viewerTimezone: 'Asia/Kolkata',
				locale: 'en-US',
			} )
		).toBe( '' );
	} );

	it( 'still speaks up when the event timezone is one Intl will not take', () => {
		expect(
			getViewerTimeLabel( {
				startGmt: '2030-06-15 22:00:00',
				eventTimezone: 'Not/AZone',
				viewerTimezone: 'Europe/Warsaw',
				locale: 'en-US',
			} )
		).toBe( '12:00 AM your time' );
	} );

	it( 'still speaks up when the event timezone is missing', () => {
		expect(
			getViewerTimeLabel( {
				startGmt: '2030-06-15 22:00:00',
				viewerTimezone: 'Europe/Warsaw',
				locale: 'en-US',
			} )
		).toBe( '12:00 AM your time' );
	} );

	it( 'says nothing to a viewer already in the event timezone', () => {
		expect(
			getViewerTimeLabel( {
				startGmt: '2030-06-15 22:00:00',
				eventTimezone: 'America/New_York',
				viewerTimezone: 'America/New_York',
				locale: 'en-US',
			} )
		).toBe( '' );
	} );

	it( 'converts the end alone when there is no start to convert', () => {
		// What a block set to display only the end asks for: 14:00 in New York
		// is 19:00 in London, on the same calendar day for both.
		expect(
			getViewerTimeLabel( {
				endGmt: '2030-06-15 18:00:00',
				eventTimezone: 'America/New_York',
				viewerTimezone: 'Europe/London',
				locale: 'en-US',
			} )
		).toBe( '7:00 PM your time' );
	} );

	it( 'dates a lone end that lands on another day for the viewer', () => {
		// 20:00 on the 15th in New York, which is 01:00 on the 16th in London.
		expect(
			getViewerTimeLabel( {
				endGmt: '2030-06-16 00:00:00',
				eventTimezone: 'America/New_York',
				viewerTimezone: 'Europe/London',
				locale: 'en-US',
			} )
		).toBe( '6/16/2030, 1:00 AM your time' );
	} );

	it( 'says nothing without a start or an end', () => {
		expect(
			getViewerTimeLabel( {
				eventTimezone: 'America/New_York',
				viewerTimezone: 'Europe/Warsaw',
			} )
		).toBe( '' );
	} );

	it( 'says nothing when the viewer timezone is unknown', () => {
		expect(
			getViewerTimeLabel( {
				startGmt: '2030-06-15 22:00:00',
				eventTimezone: 'America/New_York',
				viewerTimezone: '',
			} )
		).toBe( '' );
	} );

	it( 'says nothing when the start cannot be formatted', () => {
		expect(
			getViewerTimeLabel( {
				startGmt: 'not a date',
				eventTimezone: 'America/New_York',
				viewerTimezone: 'Europe/Warsaw',
			} )
		).toBe( '' );
	} );

	it( 'reports the time alone when a manual offset lands on the same viewer day', () => {
		expect(
			getViewerTimeLabel( {
				startGmt: '2030-06-15 22:00:00',
				eventTimezone: '+05:30',
				viewerTimezone: 'Europe/Warsaw',
				locale: 'en-US',
			} )
		).toBe( '12:00 AM your time' );
	} );

	it( 'fills the caller\'s own sentence formats', () => {
		expect(
			getViewerTimeLabel( {
				startGmt: '2030-06-15 16:00:00',
				endGmt: '2030-06-15 18:00:00',
				eventTimezone: 'America/New_York',
				viewerTimezone: 'Europe/Warsaw',
				locale: 'en-US',
				rangeFormat: 'u ciebie %1$s do %2$s',
				singleFormat: 'u ciebie %s',
			} )
		).toBe( 'u ciebie 6:00 PM do 8:00 PM' );

		expect(
			getViewerTimeLabel( {
				startGmt: '2030-06-15 16:00:00',
				eventTimezone: 'America/New_York',
				viewerTimezone: 'Europe/Warsaw',
				locale: 'en-US',
				rangeFormat: 'u ciebie %1$s do %2$s',
				singleFormat: 'u ciebie %s',
			} )
		).toBe( 'u ciebie 6:00 PM' );
	} );

	it( 'fills a lone placeholder a translation has renumbered', () => {
		// Translators normalize a single `%s` to `%1$s` routinely, and sprintf
		// treats the two as the same placeholder. A literal replace does not,
		// and the reader would be left staring at the raw token.
		expect(
			getViewerTimeLabel( {
				startGmt: '2030-06-15 16:00:00',
				eventTimezone: 'America/New_York',
				viewerTimezone: 'Europe/Warsaw',
				locale: 'en-US',
				singleFormat: '%1$s czasu u ciebie',
			} )
		).toBe( '6:00 PM czasu u ciebie' );
	} );

	it( 'fills a range written with unnumbered placeholders', () => {
		expect(
			getViewerTimeLabel( {
				startGmt: '2030-06-15 16:00:00',
				endGmt: '2030-06-15 18:00:00',
				eventTimezone: 'America/New_York',
				viewerTimezone: 'Europe/Warsaw',
				locale: 'en-US',
				rangeFormat: '%s to %s your time',
			} )
		).toBe( '6:00 PM to 8:00 PM your time' );
	} );

	it( 'fills every copy of a repeated placeholder', () => {
		expect(
			getViewerTimeLabel( {
				startGmt: '2030-06-15 16:00:00',
				endGmt: '2030-06-15 18:00:00',
				eventTimezone: 'America/New_York',
				viewerTimezone: 'Europe/Warsaw',
				locale: 'en-US',
				rangeFormat: 'od %1$s do %2$s, czyli %1$s u ciebie',
			} )
		).toBe( 'od 6:00 PM do 8:00 PM, czyli 6:00 PM u ciebie' );
	} );

	it( 'unescapes a doubled percent sign', () => {
		expect(
			getViewerTimeLabel( {
				startGmt: '2030-06-15 16:00:00',
				eventTimezone: 'America/New_York',
				viewerTimezone: 'Europe/Warsaw',
				locale: 'en-US',
				singleFormat: '%s your time (100%% local)',
			} )
		).toBe( '6:00 PM your time (100% local)' );
	} );

	it( 'leaves a placeholder alone when nothing fills it', () => {
		// A translation inventing a third placeholder should show the token
		// rather than the word "undefined".
		expect(
			getViewerTimeLabel( {
				startGmt: '2030-06-15 16:00:00',
				eventTimezone: 'America/New_York',
				viewerTimezone: 'Europe/Warsaw',
				locale: 'en-US',
				singleFormat: '%s your time (%3$s)',
			} )
		).toBe( '6:00 PM your time (%3$s)' );
	} );

	it( 'says nothing when called with no arguments', () => {
		expect( getViewerTimeLabel() ).toBe( '' );
	} );
} );
