/**
 * Date utility functions for GatherPress Calendar.
 *
 * @package
 */

import { date as formatSiteDate, gmdateI18n } from '@wordpress/date';
import { _x, sprintf } from '@wordpress/i18n';
import { isWeekendDay } from './calendar-utils';
import { getDatetimeSeparator } from './source-utils';

/**
 * Formats a calendar Date object into YYYY-MM-DD without timezone shifting.
 *
 * @param {Date} date Calendar date object.
 * @return {string} YYYY-MM-DD date string.
 */
export function formatDate( date ) {
	const year = date.getFullYear();
	const month = String( date.getMonth() + 1 ).padStart( 2, '0' );
	const day = String( date.getDate() ).padStart( 2, '0' );
	return `${ year }-${ month }-${ day }`;
}

/**
 * Formats a calendar date in the site's locale, without a time zone shift.
 *
 * The calendar keeps its dates at browser-local noon. dateI18n() converts a
 * moment to the site time zone, which moves the date when the browser and
 * the site are more than 12 hours apart. The same date at noon UTC, formatted
 * in UTC, keeps its day.
 *
 * @param {string} dateFormat PHP-style date format.
 * @param {Date}   date       Calendar date object.
 * @return {string} Formatted date.
 */
export function formatCalendarDate( dateFormat, date ) {
	return gmdateI18n( dateFormat, `${ formatDate( date ) }T12:00:00Z` );
}

/**
 * Gets today in the site time zone, as the server does with current_datetime().
 *
 * @return {Date} Today at browser-local noon.
 */
export function getSiteToday() {
	const [ year, month, day ] = formatSiteDate( 'Y-m-d' )
		.split( '-' )
		.map( Number );

	return new Date( year, month - 1, day, 12, 0, 0 );
}

/**
 * Calculate the target date based on viewType, selectedDate, and modifiers.
 *
 * @param {Object} [options]              Options object.
 * @param {string} [options.viewType]     One of 'month', 'week', or 'day'.
 * @param {string} [options.selectedDate] The date string.
 * @param {number} [options.dateModifier] Modifier offset.
 * @return {Date} Calculated target date.
 */
export function calculateTargetDate( {
	viewType = 'month',
	selectedDate = '',
	dateModifier = 0,
} = {} ) {
	let targetDate;

	if ( selectedDate && /^\d{4}-\d{2}-\d{2}$/.test( selectedDate ) ) {
		const [ year, month, day ] = selectedDate.split( '-' ).map( Number );
		targetDate = new Date( year, month - 1, day, 12, 0, 0 );
	} else if ( selectedDate && /^\d{4}-\d{2}$/.test( selectedDate ) ) {
		const [ year, month ] = selectedDate.split( '-' ).map( Number );
		targetDate = new Date( year, month - 1, 1, 12, 0, 0 );
	} else {
		targetDate = getSiteToday();
	}

	if ( 'month' === viewType ) {
		targetDate.setDate( 1 );
	}

	if ( 0 !== dateModifier ) {
		if ( 'week' === viewType ) {
			targetDate.setDate( targetDate.getDate() + ( dateModifier * 7 ) );
		} else if ( 'day' === viewType ) {
			targetDate.setDate( targetDate.getDate() + dateModifier );
		} else {
			targetDate.setDate( 1 );
			targetDate.setMonth( targetDate.getMonth() + dateModifier );
		}
	}

	return targetDate;
}

/**
 * Calculates the number of calendar units spanned by an event's date range.
 *
 * @param {string} viewType     View type ('month', 'week', 'day').
 * @param {string} startDateStr Start date (YYYY-MM-DD).
 * @param {string} endDateStr   End date (YYYY-MM-DD).
 * @param {number} startOfWeek  Start of week (0-6).
 *
 * @return {number} Clamped unit count.
 */
export function calculatePostSpanUnits(
	viewType,
	startDateStr,
	endDateStr,
	startOfWeek = 0,
) {
	if ( ! startDateStr || ! endDateStr ) {
		return 1;
	}

	const [ sY, sM, sD ] = startDateStr.split( '-' ).map( Number );
	const [ eY, eM, eD ] = endDateStr.split( '-' ).map( Number );
	const startObj = new Date( sY, sM - 1, sD, 12, 0, 0 );
	const endObj = new Date( eY, eM - 1, eD, 12, 0, 0 );

	if ( 'day' === viewType ) {
		const daysDiff = Math.max(
			1,
			Math.round( ( endObj - startObj ) / ( 1000 * 60 * 60 * 24 ) ) + 1,
		);
		return Math.min( 7, daysDiff );
	}

	if ( 'week' === viewType ) {
		const currentDow = startObj.getDay();
		const diff = ( currentDow - startOfWeek + 7 ) % 7;
		const rawWeekStart = new Date( startObj );
		rawWeekStart.setDate( startObj.getDate() - diff );

		const endDow = endObj.getDay();
		const endDiff = ( endDow - startOfWeek + 7 ) % 7;
		const rawWeekEnd = new Date( endObj );
		rawWeekEnd.setDate( endObj.getDate() - endDiff );

		const weeksDiff = Math.max(
			1,
			Math.round(
				( rawWeekEnd - rawWeekStart ) / ( 1000 * 60 * 60 * 24 * 7 ),
			) + 1,
		);
		return Math.min( 5, weeksDiff );
	}

	const monthsDiff =
		( ( endObj.getFullYear() - startObj.getFullYear() ) * 12 ) +
		( endObj.getMonth() - startObj.getMonth() ) +
		1;

	return Math.max( 1, Math.min( 12, monthsDiff ) );
}

/**
 * Calculates start, end, and raw week boundaries for week view.
 *
 * @param {Date}    targetDate   Base target date.
 * @param {number}  unitCount    Number of weeks.
 * @param {number}  startOfWeek  Start of week (0-6).
 * @param {boolean} showWeekends Weekend visibility.
 * @return {Object} { startDate, endDate, rawWeekStart }
 */
function calculateWeekViewBounds(
	targetDate,
	unitCount,
	startOfWeek,
	showWeekends,
) {
	const currentDow = targetDate.getDay();
	const diff = ( currentDow - startOfWeek + 7 ) % 7;
	const rawWeekStart = new Date( targetDate );
	rawWeekStart.setDate( targetDate.getDate() - diff );

	const totalDays = unitCount * 7;
	const visibleDays = [];
	for ( let i = 0; i < totalDays; i++ ) {
		const d = new Date( rawWeekStart );
		d.setDate( rawWeekStart.getDate() + i );
		if ( showWeekends || ! isWeekendDay( d.getDay() ) ) {
			visibleDays.push( d );
		}
	}

	if ( 0 < visibleDays.length ) {
		return {
			startDate: visibleDays[ 0 ],
			endDate: visibleDays.at( -1 ),
			rawWeekStart,
		};
	}

	const endDate = new Date( rawWeekStart );
	endDate.setDate( rawWeekStart.getDate() + totalDays - 1 );

	return {
		startDate: rawWeekStart,
		endDate,
		rawWeekStart,
	};
}

/**
 * Calculate range and boundaries for a given date selection.
 *
 * @param {Object} options     Options containing viewType, selectedDate, unitCount, dateModifier, etc.
 * @param {number} startOfWeek Start of week index (0-6).
 *
 * @return {Object} Range object.
 */
export function calculateDateRange( options = {}, startOfWeek = 0 ) {
	const viewType = options.viewType || 'month';
	const showWeekends =
		false !== options.showWeekends && 'false' !== options.showWeekends;
	const unitCount = Math.max( 1, options.unitCount || 1 );
	const targetDate = calculateTargetDate( options );

	let startDate;
	let endDate;
	let rawWeekStart;

	if ( 'week' === viewType ) {
		const bounds = calculateWeekViewBounds(
			targetDate,
			unitCount,
			startOfWeek,
			showWeekends,
		);
		startDate = bounds.startDate;
		endDate = bounds.endDate;
		rawWeekStart = bounds.rawWeekStart;
	} else if ( 'day' === viewType ) {
		startDate = new Date( targetDate );
		endDate = new Date( targetDate );
		endDate.setDate( targetDate.getDate() + unitCount - 1 );
	} else {
		const year = targetDate.getFullYear();
		const month = targetDate.getMonth();
		startDate = new Date( year, month, 1, 12, 0, 0 );
		endDate = new Date( year, month + unitCount, 0, 12, 0, 0 );
	}

	return {
		startDate: formatDate( startDate ),
		endDate: formatDate( endDate ),
		startDateObj: startDate,
		endDateObj: endDate,
		rawWeekStart: rawWeekStart || startDate,
		year: targetDate.getFullYear(),
		month: targetDate.getMonth() + 1,
		targetDate,
		viewType,
		unitCount,
	};
}

/**
 * Calculate date query parameters for REST requests.
 *
 * @param {Object} options     Configuration.
 * @param {number} startOfWeek Start of week index.
 *
 * @return {Object} Query parameters.
 */
export function calculateDateQuery( options = {}, startOfWeek = 0 ) {
	const range = calculateDateRange( options, startOfWeek );

	return {
		year: range.year,
		month: range.month,
		startDate: range.startDate,
		endDate: range.endDate,
		viewType: range.viewType,
		unitCount: range.unitCount,
	};
}

/**
 * Formats day or week heading date spans.
 *
 * @param {Date}   start     Range start date object.
 * @param {Date}   end       Range end date object.
 * @param {string} separator Datetime separator.
 * @return {string} Formatted heading.
 */
function formatDayOrWeekHeading( start, end, separator ) {
	let startFormat;
	let endFormat;

	if ( start.getFullYear() !== end.getFullYear() ) {
		startFormat = _x(
			/* translators: Date format, see https://www.php.net/manual/datetime.format.php */
			'M j, Y',
			'Calendar heading: start of date range across years',
			'gatherpress',
		);
		endFormat = _x(
			/* translators: Date format, see https://www.php.net/manual/datetime.format.php */
			'M j, Y',
			'Calendar heading: end of date range',
			'gatherpress',
		);
	} else if ( start.getMonth() !== end.getMonth() ) {
		startFormat = _x(
			/* translators: Date format, see https://www.php.net/manual/datetime.format.php */
			'M j',
			'Calendar heading: start of date range across months',
			'gatherpress',
		);
		endFormat = _x(
			/* translators: Date format, see https://www.php.net/manual/datetime.format.php */
			'M j, Y',
			'Calendar heading: end of date range',
			'gatherpress',
		);
	} else {
		startFormat = _x(
			/* translators: Date format, see https://www.php.net/manual/datetime.format.php */
			'M j',
			'Calendar heading: start of date range within a month',
			'gatherpress',
		);
		endFormat = _x(
			/* translators: Date format, see https://www.php.net/manual/datetime.format.php */
			'j, Y',
			'Calendar heading: end of date range within a month',
			'gatherpress',
		);
	}

	return sprintf(
		/* translators: %1$s: start date, %2$s: separator, %3$s: end date. */
		_x(
			'%1$s %2$s %3$s',
			'Calendar heading: date range',
			'gatherpress',
		),
		formatCalendarDate( startFormat, start ),
		separator,
		formatCalendarDate( endFormat, end ),
	);
}

/**
 * Formats month heading date spans.
 *
 * @param {Date}   startDate Range start date object.
 * @param {Date}   endDate   Range end date object.
 * @param {string} separator Datetime separator.
 * @return {string} Formatted heading.
 */
function formatMonthHeading( startDate, endDate, separator ) {
	const start = new Date(
		startDate.getFullYear(),
		startDate.getMonth(),
		15,
		12,
		0,
		0,
	);
	const end = new Date(
		endDate.getFullYear(),
		endDate.getMonth(),
		15,
		12,
		0,
		0,
	);

	if (
		start.getFullYear() === end.getFullYear() &&
		start.getMonth() === end.getMonth()
	) {
		return formatCalendarDate(
			/* translators: Date format, see https://www.php.net/manual/datetime.format.php */
			_x(
				'F Y',
				'Calendar heading: single month',
				'gatherpress',
			),
			start,
		);
	}

	let startFormat;
	let endFormat;

	if ( start.getFullYear() !== end.getFullYear() ) {
		startFormat = _x(
			/* translators: Date format, see https://www.php.net/manual/datetime.format.php */
			'M Y',
			'Calendar heading: month range across years',
			'gatherpress',
		);
		endFormat = startFormat;
	} else {
		startFormat = _x(
			/* translators: Date format, see https://www.php.net/manual/datetime.format.php */
			'F',
			'Calendar heading: start of month range within a year',
			'gatherpress',
		);
		endFormat = _x(
			/* translators: Date format, see https://www.php.net/manual/datetime.format.php */
			'F Y',
			'Calendar heading: end of month range within a year',
			'gatherpress',
		);
	}

	return sprintf(
		/* translators: %1$s: start date, %2$s: separator, %3$s: end date. */
		_x(
			'%1$s %2$s %3$s',
			'Calendar heading: date range',
			'gatherpress',
		),
		formatCalendarDate( startFormat, start ),
		separator,
		formatCalendarDate( endFormat, end ),
	);
}

/**
 * Format calendar heading based on view type and date range.
 *
 * @param {string} viewType  View type: 'month' | 'week' | 'day'.
 * @param {Date}   startDate Range start date object.
 * @param {Date}   endDate   Range end date object.
 *
 * @return {string} Formatted localized heading string.
 */
export function formatHeading( viewType, startDate, endDate ) {
	const isSameDay = startDate.toDateString() === endDate.toDateString();

	if ( 'day' === viewType && isSameDay ) {
		return formatCalendarDate(
			/* translators: Date format, see https://www.php.net/manual/datetime.format.php */
			_x(
				'l, F j, Y',
				'Calendar heading: single day',
				'gatherpress',
			),
			startDate,
		);
	}

	const separator = getDatetimeSeparator();

	if ( 'day' === viewType || 'week' === viewType ) {
		return formatDayOrWeekHeading( startDate, endDate, separator );
	}

	return formatMonthHeading( startDate, endDate, separator );
}
