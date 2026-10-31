/**
 * External dependencies.
 */
import { describe, expect, it } from '@jest/globals';

/**
 * Internal dependencies.
 */
import { getDayClassNames } from '@src/blocks/calendar/utils/day-classes';

const TODAY = '2026-10-15';
const FLAT = { ribbon: false, grid: false };

describe( 'getDayClassNames', () => {
	it( 'marks today, past and future days', () => {
		expect( getDayClassNames( { date: TODAY }, TODAY, FLAT ) ).toEqual( [
			'gatherpress-calendar__day',
			'is-today',
		] );
		expect(
			getDayClassNames( { date: '2026-10-14' }, TODAY, FLAT ),
		).toContain( 'is-past' );
		expect(
			getDayClassNames( { date: '2026-10-16' }, TODAY, FLAT ),
		).toContain( 'is-future' );
	} );

	it( 'gives an empty cell no date classes', () => {
		expect(
			getDayClassNames( { date: TODAY, isEmpty: true }, TODAY, FLAT ),
		).toEqual( [ 'gatherpress-calendar__day', 'is-empty' ] );
	} );

	it( 'marks the entries, weekday and multi-day events', () => {
		const classes = getDayClassNames(
			{
				date: '2026-10-17',
				isWeekend: true,
				weekday: 'saturday',
				posts: [ { totalDays: 1 }, { totalDays: 2 } ],
			},
			TODAY,
			FLAT,
		);

		expect( classes ).toEqual(
			expect.arrayContaining( [
				'has-posts',
				'has-multi-day-event',
				'is-weekend',
				'is-saturday',
			] ),
		);
		expect(
			getDayClassNames(
				{ date: TODAY, posts: [ { isAllDay: true } ] },
				TODAY,
				FLAT,
			),
		).toContain( 'has-multi-day-event' );
		expect(
			getDayClassNames( { date: TODAY, posts: [ {} ] }, TODAY, FLAT ),
		).not.toContain( 'has-multi-day-event' );
	} );

	it( 'gives the time grid the Ribbon layout', () => {
		const day = { date: TODAY };

		expect(
			getDayClassNames( day, TODAY, { ribbon: true, grid: false } ),
		).toContain( 'is-style-ribbon' );
		expect(
			getDayClassNames( day, TODAY, { ribbon: false, grid: true } ),
		).toEqual( expect.arrayContaining( [ 'is-style-ribbon', 'has-time-grid' ] ) );
	} );
} );
