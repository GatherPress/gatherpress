/**
 * External dependencies.
 */
import { describe, expect, it } from '@jest/globals';
import fs from 'fs';
import path from 'path';

/**
 * Internal dependencies.
 */
import { calculateDateRange } from '@src/blocks/calendar/utils/date-utils';
import {
	generateCalendar,
	getTimeGridConfig,
	getWeekNumber,
} from '@src/blocks/calendar/utils/grid-builder';

/**
 * The JS half of the calendar grid parity tests.
 *
 * The editor builds the calendar grid in JS and the front end builds it in PHP.
 * Both run the fixtures in test/unit/fixtures/calendar-grid and must give the
 * grid each fixture expects, so a change to one side that the other does not
 * make fails. The PHP half is
 * test/unit/php/includes/tests/core/classes/calendar/class-test-grid-parity.php.
 */
const FIXTURES = path.resolve( __dirname, '../../../../../fixtures/calendar-grid' );
const EVENT_TYPE = 'gatherpress_event';

/**
 * Normalize a day of the grid to the shape of the fixtures.
 *
 * @param {Object} day  Day entry.
 * @param {Object} keys Fixture keys of the events, by post ID.
 *
 * @return {Object|string} The day, or 'empty' for a padding cell.
 */
const normalizeDay = ( day, keys ) => {
	if ( day.isEmpty ) {
		return 'empty';
	}

	return {
		date: day.date,
		isWeekend: day.isWeekend,
		posts: day.posts.map( ( entry ) => ( {
			event: keys[ entry.id ],
			span: entry.span,
			dayIndex: entry.dayIndex,
			totalDays: entry.totalDays,
			slot: entry.slot,
			allDay: entry.isAllDay,
			...( entry.isTimed
				? {
					rows: [ entry.timeRowStart, entry.timeRowEnd ],
					overlap: [ entry.overlapIndex, entry.overlapTotal ],
				}
				: {} ),
		} ) ),
	};
};

const fixtures = fs
	.readdirSync( FIXTURES )
	.filter( ( file ) => file.endsWith( '.json' ) )
	.map( ( file ) => [
		file.replace( '.json', '' ),
		JSON.parse( fs.readFileSync( path.join( FIXTURES, file ), 'utf8' ) ),
	] );

describe( 'calendar grid parity with PHP', () => {
	it.each( fixtures )( '%s', ( name, fixture ) => {
		const { attributes, startOfWeek } = fixture;
		const keys = {};
		const posts = fixture.events.map( ( event, index ) => {
			keys[ index + 1 ] = event.key;

			if ( 'post' === event.type ) {
				return { id: index + 1, type: 'post', date: event.start };
			}

			return {
				id: index + 1,
				type: EVENT_TYPE,
				meta: {
					gatherpress_datetime_start: event.start,
					gatherpress_datetime_end: event.end,
					gatherpress_is_all_day: Boolean( event.allDay ),
				},
			};
		} );
		const dateRange = calculateDateRange( attributes, startOfWeek );
		const timeGridConfig = getTimeGridConfig( {
			enabled: Boolean( attributes.enableTimeGrid ),
			startHour: fixture.week?.dayStartHour,
			endHour: fixture.week?.dayEndHour,
			slotHours: 1,
			slotHeight: 48,
		} );
		const calendar = generateCalendar(
			posts,
			startOfWeek,
			dateRange,
			attributes.showWeekends,
			timeGridConfig,
			[ EVENT_TYPE ],
		);

		const actual = {
			range: {
				start: dateRange.startDate,
				end: dateRange.endDate,
				unitCount: dateRange.unitCount,
			},
			units: calendar.units.map( ( unit ) => ( {
				weeks: unit.weeks.map( ( week ) =>
					week.map( ( day ) => normalizeDay( day, keys ) ),
				),
				weekNumbers: unit.weeks.map( ( week ) =>
					getWeekNumber( week, startOfWeek ),
				),
			} ) ),
			...( timeGridConfig.enabled
				? {
					timeGrid: {
						slots: timeGridConfig.slots,
						totalQuarters: timeGridConfig.totalQuarters,
					},
				}
				: {} ),
		};

		expect( actual ).toEqual( fixture.expected );
	} );
} );
