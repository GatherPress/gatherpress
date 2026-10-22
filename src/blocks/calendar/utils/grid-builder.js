/**
 * Grid builder functions for calendar units, weeks, and days.
 *
 * @package
 * @since TBD
 */

import { dateI18n, format } from '@wordpress/date';

import { DATE_FORMAT } from '../constants';
import { formatDate } from './date-utils';
import { WEEKDAY_SLUGS, isWeekendDay, getDayNames } from './calendar-utils';

/**
 * Maximum number of days of one event that the calendar places.
 *
 * Mirrors Post_Organizer::MAX_EVENT_SPAN_DAYS: twelve months, the largest view.
 *
 * @since TBD
 */
const MAX_EVENT_SPAN_DAYS = 366;

/**
 * Milliseconds in one day.
 *
 * @since TBD
 */
const DAY_MS = 1000 * 60 * 60 * 24;

/**
 * Read an hour setting, keeping 0.
 *
 * Like the PHP side: a numeric hour stays, 0 included; anything else uses the default.
 *
 * @since TBD
 *
 * @param {*}      value    Hour from an attribute or the time grid configuration.
 * @param {number} fallback Hour to use when the value is not a number.
 *
 * @return {number} The hour.
 */
export function toHour( value, fallback ) {
	return '' !== value && null !== value && Number.isFinite( Number( value ) )
		? Number( value )
		: fallback;
}

/**
 * Create a Date at noon local time from a YYYY-MM-DD string.
 *
 * Noon keeps the day the same when daylight saving time changes the clock.
 *
 * @since TBD
 *
 * @param {string} dateStr Date (YYYY-MM-DD).
 *
 * @return {Date} Date at noon.
 */
function toNoonDate( dateStr ) {
	const [ year, month, day ] = dateStr.split( '-' ).map( Number );

	return new Date( year, month - 1, day, 12, 0, 0 );
}

/**
 * Count the days from one date to a later date.
 *
 * @since TBD
 *
 * @param {Date} from Earlier date at noon.
 * @param {Date} to   Later date at noon.
 *
 * @return {number} Number of days.
 */
function daysBetween( from, to ) {
	return Math.round( ( to - from ) / DAY_MS );
}

/**
 * Classifies the span position for a day within a multi-day event.
 *
 * @param {number} dayIndex  1-based day index.
 * @param {number} totalDays Total days of the event.
 * @return {string} 'single', 'start', 'middle', or 'end'.
 */
export function determineSpanType( dayIndex, totalDays ) {
	if ( 1 >= totalDays ) {
		return 'single';
	}
	if ( 1 === dayIndex ) {
		return 'start';
	}
	if ( dayIndex === totalDays ) {
		return 'end';
	}
	return 'middle';
}

/**
 * Calculates start object, total days, and string dates for a post.
 *
 * Mirrors Post_Organizer::calculate_date_span(). `totalDays` is the full length
 * of the event. `firstDay` (0-based) and `shownDays` give the part of the event
 * inside the visible range, so an event that starts before the range still
 * shows on the visible days.
 *
 * @param {Object}        post           Post entity record.
 * @param {Object|null}   range          Visible range, { startDate, endDate } as YYYY-MM-DD, or null.
 * @param {Array<string>} eventPostTypes Slugs of the post types that support gatherpress-event-date.
 * @return {Object|null} PostDateSpan descriptor object, or null when the post is outside the range.
 */
export function resolvePostDateSpan( post, range = null, eventPostTypes = [] ) {
	const isEvent = eventPostTypes.includes( post.type );
	const postDate = isEvent
		? post.meta?.gatherpress_datetime_start
		: post.date;

	if ( ! postDate ) {
		return null;
	}

	// When post supports gatherpress-event-date, postDate from meta is a string like:
	// "2026-09-30 23:30:00" (an event starting at 11:30 PM in its local timezone).
	// Here is why new Date( postDate ) causes the bug in the editor:
	// new Date( "2026-09-30 23:30:00" ) creates a JavaScript Date object at 23:30 in the browser's timezone.
	// dateI18n( DATE_FORMAT, ... ) converts that Date object into the WordPress site's timezone.
	// If the site is just 1 hour ahead of the browser (or the event's local time), 23:30 becomes 00:30 on 2026-10-01.
	// As a result, dateStr becomes "2026-10-01". The event gets placed into October 1st in the editor grid instead of September 30th!
	//
	// We directly take the first 10 characters ("YYYY-MM-DD") from the event's local datetime string,
	// preventing JavaScript's browser/site timezone conversion from pushing a late-night event into the wrong day.
	const startDateStr =
		isEvent && post.meta?.gatherpress_datetime_start
			? post.meta.gatherpress_datetime_start.slice( 0, 10 )
			: dateI18n( DATE_FORMAT, new Date( postDate ) );

	let endDateStr = startDateStr;
	if ( isEvent && post.meta?.gatherpress_datetime_end ) {
		const rawEnd = post.meta.gatherpress_datetime_end.slice( 0, 10 );
		if ( rawEnd >= startDateStr ) {
			endDateStr = rawEnd;
		}
	}

	const startObj = toNoonDate( startDateStr );
	const totalDays = Math.max(
		1,
		daysBetween( startObj, toNoonDate( endDateStr ) ) + 1,
	);
	let firstDay = 0;
	let lastDay = totalDays - 1;

	if ( 'string' === typeof range?.startDate && 'string' === typeof range?.endDate ) {
		// YYYY-MM-DD strings compare in date order.
		if ( endDateStr < range.startDate || startDateStr > range.endDate ) {
			return null;
		}

		if ( startDateStr < range.startDate ) {
			firstDay = daysBetween( startObj, toNoonDate( range.startDate ) );
		}

		if ( endDateStr > range.endDate ) {
			lastDay = daysBetween( startObj, toNoonDate( range.endDate ) );
		}
	}

	const shownDays = Math.min( MAX_EVENT_SPAN_DAYS, lastDay - firstDay + 1 );
	const isAllDay = Boolean(
		true === post.meta?.gatherpress_is_all_day ||
		'1' === post.meta?.gatherpress_is_all_day ||
		1 === post.meta?.gatherpress_is_all_day ||
		'true' === post.meta?.gatherpress_is_all_day,
	);

	return {
		post,
		startObj,
		startDateStr,
		totalDays,
		firstDay,
		shownDays,
		isAllDay,
	};
}

/**
 * Computes quarter-slot row tracks for a timed event within configured day boundaries.
 *
 * @param {Object}        post           Post entity record.
 * @param {Object}        gridConfig     Time grid configuration.
 * @param {Array<string>} eventPostTypes Slugs of the post types that support gatherpress-event-date.
 *
 * @return {Object|null} { timeRowStart, timeRowEnd } or null if outside visible window.
 */
export function calculateTimedEventTracks( post, gridConfig, eventPostTypes = [] ) {
	if ( ! gridConfig?.enabled ) {
		return null;
	}

	const isEvent = eventPostTypes.includes( post.type );
	const startTimeStr = isEvent
		? post.meta?.gatherpress_datetime_start
		: post.date;
	const endTimeStr = isEvent ? post.meta?.gatherpress_datetime_end : null;

	if ( ! startTimeStr ) {
		return null;
	}

	const startHour = toHour( gridConfig.startHour, 8 );
	const endHour = toHour( gridConfig.endHour, 20 );
	const quarterMins = Number( gridConfig.quarterMins ) || 15;

	const startD = new Date( startTimeStr );
	const endD = endTimeStr
		? new Date( endTimeStr )
		: new Date( startD.getTime() + 3600000 );

	const startMin = ( startD.getHours() * 60 ) + startD.getMinutes();
	const endMin = ( endD.getHours() * 60 ) + endD.getMinutes();

	const dayStartMin = startHour * 60;
	const dayEndMin = endHour * 60;

	// Skip if entirely outside the visible day window
	if ( endMin <= dayStartMin || startMin >= dayEndMin ) {
		return null;
	}

	// Clamp to visible day boundaries
	const clampedStart = Math.max( dayStartMin, startMin );
	const clampedEnd = Math.min( dayEndMin, Math.max( startMin + 15, endMin ) );

	// Round to 1/4 of slot duration
	const roundedStart = Math.floor( clampedStart / quarterMins ) * quarterMins;
	const roundedEnd = Math.ceil( clampedEnd / quarterMins ) * quarterMins;

	const finalEnd =
		roundedEnd <= roundedStart ? roundedStart + quarterMins : roundedEnd;

	const startTrack =
		Math.floor( ( roundedStart - dayStartMin ) / quarterMins ) + 1;
	const endTrack = Math.floor( ( finalEnd - dayStartMin ) / quarterMins ) + 1;

	return {
		timeRowStart: startTrack,
		timeRowEnd: Math.max( startTrack + 1, endTrack ),
	};
}

/**
 * Finds the lowest 0-indexed vertical slot free across all dates of an event's span.
 *
 * @param {string[]} dates         Array of YYYY-MM-DD date strings.
 * @param {Object}   occupiedSlots Map of date strings to Set of occupied slots.
 * @return {number} Lowest available slot index.
 */
export function findAvailableSlot( dates, occupiedSlots ) {
	let slot = 0;

	while ( true ) {
		const isFree = dates.every(
			( date ) => ! occupiedSlots[ date ]?.has( slot ),
		);
		if ( isFree ) {
			return slot;
		}
		slot++;
	}
}

/**
 * Allocates a single timed event into the time grid entries map.
 *
 * @param {Object}        rawEvent       Event data.
 * @param {Object}        gridConfig     Time grid config.
 * @param {Object}        grouped        Grouped date entries map.
 * @param {Array<string>} eventPostTypes Slugs of the post types that support gatherpress-event-date.
 * @return {void}
 */
function allocateTimedEvent( rawEvent, gridConfig, grouped, eventPostTypes ) {
	const { post } = rawEvent;
	const tracks = calculateTimedEventTracks( post, gridConfig, eventPostTypes );
	if ( ! tracks ) {
		return;
	}

	const dateStr = rawEvent.startDateStr;
	if ( ! grouped[ dateStr ] ) {
		grouped[ dateStr ] = [];
	}

	grouped[ dateStr ].push( {
		...post,
		span: 'single',
		dayIndex: 1,
		totalDays: 1,
		isAllDay: false,
		isTimed: true,
		timeRowStart: tracks.timeRowStart,
		timeRowEnd: tracks.timeRowEnd,
		slot: 0,
	} );
}

/**
 * Allocates an all-day or multi-day ribbon event into the daily entries map.
 *
 * Only the visible days get an entry. Each entry keeps its day number in the
 * full event.
 *
 * @param {Object} rawEvent      Event data.
 * @param {Object} grouped       Grouped date entries map.
 * @param {Object} occupiedSlots Map of occupied slots.
 * @return {void}
 */
function allocateRibbonEvent( rawEvent, grouped, occupiedSlots ) {
	const { post, startObj, totalDays, firstDay, shownDays, isAllDay } =
		rawEvent;

	const eventDates = [];
	for ( let i = 0; i < shownDays; i++ ) {
		const current = new Date( startObj );
		current.setDate( startObj.getDate() + firstDay + i );
		eventDates.push( formatDate( current ) );
	}

	const slot = findAvailableSlot( eventDates, occupiedSlots );

	eventDates.forEach( ( dateStr, i ) => {
		if ( ! occupiedSlots[ dateStr ] ) {
			occupiedSlots[ dateStr ] = new Set();
		}
		occupiedSlots[ dateStr ].add( slot );

		const dayIndex = firstDay + i + 1;
		const span = determineSpanType( dayIndex, totalDays );

		if ( ! grouped[ dateStr ] ) {
			grouped[ dateStr ] = [];
		}

		grouped[ dateStr ].push( {
			...post,
			span,
			dayIndex,
			totalDays,
			slot,
			isAllDay,
			isTimed: false,
		} );
	} );
}

/**
 * Resolves concurrent overlapping columns for timed events in a day's entries.
 *
 * Mirrors Post_Organizer::resolve_day_timed_overlaps(). Timed entries that
 * overlap, directly or through other entries, form a group. Each entry goes in
 * the first column of its group that is free at its start time, so no two
 * overlapping entries share a column and all entries of a group have the same
 * width.
 *
 * @param {Array<Object>} dayEntries Entries for a single date.
 * @return {void}
 */
function resolveDayOverlaps( dayEntries ) {
	// Array.prototype.sort is stable, so equal entries keep their order.
	const timed = dayEntries
		.filter( ( entry ) => entry.isTimed )
		.sort(
			( a, b ) =>
				a.timeRowStart - b.timeRowStart || a.timeRowEnd - b.timeRowEnd,
		);
	let group = [];
	let columnEnds = [];
	let groupEnd = 0;

	const assignGroup = () => {
		group.forEach( ( { entry, column } ) => {
			entry.overlapTotal = columnEnds.length;
			entry.overlapIndex = column;
		} );
	};

	timed.forEach( ( entry ) => {
		// An entry that starts after every entry of the group ends starts a new group.
		if ( entry.timeRowStart >= groupEnd ) {
			assignGroup();
			group = [];
			columnEnds = [];
		}

		let column = 0;
		while (
			column < columnEnds.length &&
			columnEnds[ column ] > entry.timeRowStart
		) {
			column++;
		}

		columnEnds[ column ] = entry.timeRowEnd;
		group.push( { entry, column } );
		groupEnd = Math.max( groupEnd, entry.timeRowEnd );
	} );

	assignGroup();
}

/**
 * Organizes posts by YYYY-MM-DD date string,
 * spanning multi-day events across all active days
 * and supporting time-grid coordinates.
 *
 * @param {Array}         posts          Raw post entities.
 * @param {Object}        gridConfig     Time grid configuration.
 * @param {Object|null}   range          Visible range, { startDate, endDate } as YYYY-MM-DD, or null.
 * @param {Array<string>} eventPostTypes Slugs of the post types that support gatherpress-event-date.
 *
 * @return {Object} Posts grouped by date string.
 */
export function groupPostsByDate(
	posts = [],
	gridConfig = {},
	range = null,
	eventPostTypes = [],
) {
	const grouped = {};
	if ( ! posts?.length ) {
		return grouped;
	}

	const rawEvents = posts
		.map( ( post ) => resolvePostDateSpan( post, range, eventPostTypes ) )
		.filter( Boolean );
	const isTimeGrid = Boolean( gridConfig?.enabled );

	// Sort events: multi-day/all-day first, then start date, then ID
	rawEvents.sort( ( a, b ) => {
		const aPromoted = 1 < a.totalDays || a.isAllDay ? 1 : 0;
		const bPromoted = 1 < b.totalDays || b.isAllDay ? 1 : 0;
		if ( aPromoted !== bPromoted ) {
			return bPromoted - aPromoted;
		}
		if ( a.totalDays !== b.totalDays ) {
			return b.totalDays - a.totalDays;
		}
		if ( a.startDateStr !== b.startDateStr ) {
			return a.startDateStr.localeCompare( b.startDateStr );
		}
		return ( a.post.id || 0 ) - ( b.post.id || 0 );
	} );

	const occupiedSlots = {};

	rawEvents.forEach( ( rawEvent ) => {
		const isMultiDay = 1 < rawEvent.totalDays || rawEvent.isAllDay;

		if ( isTimeGrid && ! isMultiDay ) {
			allocateTimedEvent( rawEvent, gridConfig, grouped, eventPostTypes );
		} else {
			allocateRibbonEvent( rawEvent, grouped, occupiedSlots );
		}
	} );

	if ( isTimeGrid ) {
		Object.keys( grouped ).forEach( ( dateStr ) => {
			resolveDayOverlaps( grouped[ dateStr ] );
		} );
	}

	Object.keys( grouped ).forEach( ( dateStr ) => {
		grouped[ dateStr ].sort(
			( a, b ) => ( a.slot || 0 ) - ( b.slot || 0 ),
		);
	} );

	return grouped;
}

/**
 * Helper to build an active day descriptor object.
 *
 * @param {Date}   dateObj     Date object.
 * @param {Object} postsByDate Posts grouped by date string.
 * @param {string} todayStr    Today's date string.
 * @return {Object} Day descriptor object.
 */
export function createDayEntry( dateObj, postsByDate, todayStr ) {
	const dayOfWeek = dateObj.getDay();
	const dateStr = formatDate( dateObj );

	return {
		day: dateObj.getDate(),
		date: dateStr,
		posts: postsByDate?.[ dateStr ] ?? [],
		isEmpty: false,
		isToday: dateStr === todayStr,
		isPast: dateStr < todayStr,
		isFuture: dateStr > todayStr,
		dayOfWeek,
		weekday: WEEKDAY_SLUGS[ dayOfWeek ],
		isWeekend: isWeekendDay( dayOfWeek ),
	};
}

/**
 * Build the weeks array for the requested month.
 *
 * @param {number}  year         Target year.
 * @param {number}  month        Target month (1-12).
 * @param {number}  startOfWeek  Start of week (0-6).
 * @param {number}  daysInMonth  Number of days in month.
 * @param {Object}  postsByDate  Posts grouped by date.
 * @param {boolean} showWeekends Weekend visibility.
 *
 * @return {Array[]} Weeks array.
 */
export function buildWeeks(
	year,
	month,
	startOfWeek,
	daysInMonth,
	postsByDate = {},
	showWeekends = true,
) {
	const today = dateI18n( DATE_FORMAT, new Date() );
	const activeDaysOfWeek = [];

	for ( let i = 0; 7 > i; i++ ) {
		const dow = ( startOfWeek + i ) % 7;
		if ( ! showWeekends && isWeekendDay( dow ) ) {
			continue;
		}
		activeDaysOfWeek.push( dow );
	}

	const daysPerWeek = activeDaysOfWeek.length;
	const weeks = [];
	let currentWeek = [];
	let firstDayPlaced = false;

	for ( let day = 1; day <= daysInMonth; day++ ) {
		const dateObj = new Date( year, month - 1, day );
		const dayOfWeek = dateObj.getDay();

		// Skip weekends if hidden
		if ( ! showWeekends && isWeekendDay( dayOfWeek ) ) {
			continue;
		}

		// Pre-pad leading empty days before the first visible day of the month
		if ( ! firstDayPlaced ) {
			firstDayPlaced = true;
			const startCol = activeDaysOfWeek.indexOf( dayOfWeek );
			const emptyDays = -1 !== startCol ? startCol : 0;

			// Fill initial empty days before the month starts.
			for ( let i = 0; i < emptyDays; i++ ) {
				currentWeek.push( { isEmpty: true, posts: [] } );
			}
		}

		currentWeek.push( createDayEntry( dateObj, postsByDate, today ) );

		// When week is complete (5 or 7 days), start a new week.
		if ( currentWeek.length === daysPerWeek ) {
			weeks.push( currentWeek );
			currentWeek = [];
		}
	}

	// Fill remaining empty days after the month ends.
	while ( 0 < currentWeek.length && currentWeek.length < daysPerWeek ) {
		currentWeek.push( { isEmpty: true, posts: [] } );
	}

	if ( 0 < currentWeek.length ) {
		weeks.push( currentWeek );
	}

	return weeks;
}

/**
 * Build continuous weeks without artificial empty padding.
 *
 * @param {Date}    weekStartObj Start date of the week.
 * @param {number}  unitCount    Number of units to show.
 * @param {Object}  postsByDate  Posts grouped by date.
 * @param {boolean} showWeekends Weekend visibility.
 *
 * @return {Array[]} Consecutive weeks array.
 */
export function buildConsecutiveWeeks(
	weekStartObj,
	unitCount,
	postsByDate = {},
	showWeekends = true,
) {
	const today = dateI18n( DATE_FORMAT, new Date() );
	const weeks = [];

	for ( let w = 0; w < unitCount; w++ ) {
		const week = [];
		const currentWeekStart = new Date( weekStartObj );
		currentWeekStart.setDate( weekStartObj.getDate() + ( w * 7 ) );

		for ( let i = 0; 7 > i; i++ ) {
			const dateObj = new Date( currentWeekStart );
			dateObj.setDate( currentWeekStart.getDate() + i );

			if ( ! showWeekends && isWeekendDay( dateObj.getDay() ) ) {
				continue;
			}

			week.push( createDayEntry( dateObj, postsByDate, today ) );
		}

		weeks.push( week );
	}

	return weeks;
}

/**
 * Build single or multi-day continuous rows.
 *
 * @param {Date}   startDayObj Day Date object.
 * @param {number} unitCount   Number of units to show.
 * @param {Object} postsByDate Posts grouped by date.
 *
 * @return {Array[]} Single-element array with consecutive days.
 */
export function buildConsecutiveDays(
	startDayObj,
	unitCount,
	postsByDate = {},
) {
	const today = dateI18n( DATE_FORMAT, new Date() );
	const days = [];

	for ( let i = 0; i < unitCount; i++ ) {
		const dateObj = new Date( startDayObj );
		dateObj.setDate( startDayObj.getDate() + i );
		days.push( createDayEntry( dateObj, postsByDate, today ) );
	}

	return [ days ];
}

/**
 * Get the ISO 8601 week number of a calendar row.
 *
 * Mirrors Date_Calculator::get_week_number(). A row that does not start on
 * Monday spans two ISO weeks, and its fourth day is always in the one that
 * holds most of the row.
 *
 * @since TBD
 *
 * @param {Array}  week        Day entries of the row.
 * @param {number} startOfWeek Start of week (0-6).
 *
 * @return {number} Week number, or 0 when the row has no dated day.
 */
export function getWeekNumber( week, startOfWeek = 0 ) {
	const day = week.find( ( entry ) => entry.date );
	if ( ! day ) {
		return 0;
	}

	const [ year, month, dayOfMonth ] = day.date.split( '-' ).map( Number );
	const offset = ( day.dayOfWeek - startOfWeek + 7 ) % 7;

	// format() keeps the local date, dateI18n() would shift it to the site timezone.
	return Number(
		format( 'W', new Date( year, month - 1, dayOfMonth - offset + 3 ) ),
	);
}

/**
 * Calculate maximum ribbon slot index across a week's days.
 *
 * @param {Array<Object>} weekDays   Array of day descriptors.
 * @param {boolean}       isTimeGrid Whether time grid is enabled.
 * @return {number} Number of ribbon slots.
 */
function getWeekNumRibbonSlots( weekDays, isTimeGrid ) {
	if ( ! isTimeGrid ) {
		return 0;
	}
	let maxSlot = -1;
	weekDays.forEach( ( day ) => {
		( day.posts || [] ).forEach( ( p ) => {
			if ( ! p.isTimed ) {
				maxSlot = Math.max( maxSlot, Number( p.slot ) || 0 );
			}
		} );
	} );
	return maxSlot + 1;
}

/**
 * Generate calendar structure for any viewType, calculating week-level ribbon slots.
 *
 * @param {Array<Object>} posts          Posts from the query.
 * @param {number}        startOfWeek    Start of week (0-6).
 * @param {Object}        dateRange      Resolved date range.
 * @param {boolean}       showWeekends   Weekend visibility.
 * @param {Object}        timeGridConfig Time grid configuration.
 * @param {Array<string>} eventPostTypes Slugs of the post types that support gatherpress-event-date.
 *
 * @return {Object} Calendar data structure.
 */
export function generateCalendar(
	posts = [],
	startOfWeek = 0,
	dateRange,
	showWeekends = true,
	timeGridConfig = {},
	eventPostTypes = [],
) {
	const postsByDate = groupPostsByDate(
		posts,
		timeGridConfig,
		dateRange,
		eventPostTypes,
	);
	const viewType = dateRange.viewType || 'month';
	const requestedCount = Math.max( 1, dateRange.unitCount || 1 );
	const startObj = dateRange.startDateObj || new Date( dateRange.startDate );
	const isTimeGrid = Boolean( timeGridConfig?.enabled );

	if ( 'day' === viewType ) {
		const dayNames = [];
		for ( let i = 0; i < requestedCount; i++ ) {
			const d = new Date( startObj );
			d.setDate( startObj.getDate() + i );
			dayNames.push( dateI18n( 'D', d ) );
		}
		const weeks = buildConsecutiveDays( startObj, requestedCount, postsByDate );
		const numRibbonSlots = getWeekNumRibbonSlots(
			weeks[ 0 ] || [],
			isTimeGrid,
		);
		const units = [
			{
				dayNames,
				weeks,
				numRibbonSlots,
			},
		];

		return {
			dayNames,
			weeks,
			units,
			viewType,
			unitCount: requestedCount,
			numRibbonSlots,
		};
	}

	if ( 'week' === viewType ) {
		const weekStart =
			dateRange.rawWeekStart ||
			dateRange.startDateObj ||
			new Date( dateRange.startDate );
		const dayNames = getDayNames( startOfWeek, showWeekends );
		const weeks = buildConsecutiveWeeks(
			weekStart,
			requestedCount,
			postsByDate,
			showWeekends,
		);
		const numRibbonSlots = getWeekNumRibbonSlots(
			weeks[ 0 ] || [],
			isTimeGrid,
		);
		// Only several week rows need a week number to tell them apart.
		const units = [
			{
				dayNames,
				weeks,
				weekNumbers:
					1 < requestedCount
						? weeks.map( ( week ) =>
							getWeekNumber( week, startOfWeek ),
						)
						: undefined,
				numRibbonSlots,
			},
		];

		return {
			dayNames,
			weeks,
			units,
			viewType,
			unitCount: requestedCount,
			numRibbonSlots,
		};
	}

	const units = [];

	for ( let i = 0; i < requestedCount; i++ ) {
		const monthDate = new Date(
			startObj.getFullYear(),
			startObj.getMonth() + i,
			1,
		);
		const mYear = monthDate.getFullYear();
		const mMonth = monthDate.getMonth() + 1;
		const daysInMonth = new Date( mYear, mMonth, 0 ).getDate();
		const mWeeks = buildWeeks(
			mYear,
			mMonth,
			startOfWeek,
			daysInMonth,
			postsByDate,
			showWeekends,
		);
		const mDayNames = getDayNames( startOfWeek, showWeekends );

		units.push( {
			dayNames: mDayNames,
			weeks: mWeeks,
			weekNumbers: mWeeks.map( ( week ) =>
				getWeekNumber( week, startOfWeek ),
			),
			numRibbonSlots: 0,
			// Position of this month in the range, for the table caption.
			monthOffset: i,
		} );
	}

	const { dayNames, weeks } = units[ 0 ];

	return {
		dayNames,
		weeks,
		units,
		viewType,
		unitCount: requestedCount,
		numRibbonSlots: 0,
	};
}

/**
 * Pick a sensible default "active" (live-editable) day for the calendar
 * preview: today's date when it falls inside the displayed month,
 * otherwise the first non-empty day of the month.
 *
 * @param {Object} calendar Calendar structure.
 * @return {string} Date string.
 */
export function getDefaultActiveDate( calendar ) {
	const allWeeks = calendar.units
		? calendar.units.flatMap( ( u ) => u.weeks )
		: calendar.weeks;
	const days = allWeeks.flat();
	const todayEntry = days.find( ( day ) => ! day.isEmpty && day.isToday );

	if ( todayEntry ) {
		return todayEntry.date;
	}

	const firstDay = days.find( ( day ) => ! day.isEmpty );
	return firstDay ? firstDay.date : '';
}
