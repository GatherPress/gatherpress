/**
 * WordPress dependencies
 */
import { __, _n, _x, sprintf } from '@wordpress/i18n';
import { dateI18n, getSettings } from '@wordpress/date';
import { useSelect } from '@wordpress/data';

/**
 * External dependencies
 */
import moment from 'moment';

/**
 * Internal dependencies
 */
import { createMomentWithTimezone, isManualOffset } from './datetime';

/**
 * The REST field the schedule is read and saved through.
 *
 * @since TBD
 *
 * @type {string}
 */
export const REPEAT_FIELD = 'gatherpress_repeat';

/**
 * The id of the note that says a started series' first date can't change.
 *
 * @since TBD
 *
 * @type {string}
 */
export const FROZEN_NOTE_ID = 'gatherpress-repeat-schedule-frozen';

/**
 * Weekday codes, in week order from Monday, as rules store them.
 *
 * @since TBD
 *
 * @type {string[]}
 */
export const WEEKDAYS = [ 'MO', 'TU', 'WE', 'TH', 'FR', 'SA', 'SU' ];

/**
 * The statuses whose dates people may already have seen.
 *
 * @since TBD
 *
 * @type {string[]}
 */
const PUBLISHED_STATUSES = [ 'publish', 'private' ];

/**
 * A weekday's full name.
 *
 * @since TBD
 *
 * @param {string} code A weekday code, such as `MO`.
 *
 * @return {string} The translated name.
 */
export function weekdayName( code ) {
	const names = {
		MO: __( 'Monday', 'gatherpress' ),
		TU: __( 'Tuesday', 'gatherpress' ),
		WE: __( 'Wednesday', 'gatherpress' ),
		TH: __( 'Thursday', 'gatherpress' ),
		FR: __( 'Friday', 'gatherpress' ),
		SA: __( 'Saturday', 'gatherpress' ),
		SU: __( 'Sunday', 'gatherpress' ),
	};

	return names[ code ] ?? code;
}

/**
 * The weekday code of a date.
 *
 * @since TBD
 *
 * @param {string} date A date, as `YYYY-MM-DD` or a datetime starting with it.
 *
 * @return {string} The code, such as `MO`.
 */
export function weekdayOf( date ) {
	// moment's isoWeekday() runs 1 (Monday) to 7 (Sunday).
	return WEEKDAYS[ moment( date.slice( 0, 10 ) ).isoWeekday() - 1 ];
}

/**
 * A weekday's place in its month, such as "second Friday".
 *
 * Each place has its own string, so a translator can make the ordinal
 * agree with the weekday.
 *
 * @since TBD
 *
 * @param {number} place 1 to 4, or -1 for the last.
 * @param {string} code  A weekday code.
 *
 * @return {string} The phrase, or empty for an unknown place.
 */
export function ordinalWeekday( place, code ) {
	const name = weekdayName( code );

	switch ( place ) {
		case 1:
			/* translators: %s: A weekday name. */
			return sprintf( _x( 'first %s', 'weekday in a month', 'gatherpress' ), name );
		case 2:
			/* translators: %s: A weekday name. */
			return sprintf( _x( 'second %s', 'weekday in a month', 'gatherpress' ), name );
		case 3:
			/* translators: %s: A weekday name. */
			return sprintf( _x( 'third %s', 'weekday in a month', 'gatherpress' ), name );
		case 4:
			/* translators: %s: A weekday name. */
			return sprintf( _x( 'fourth %s', 'weekday in a month', 'gatherpress' ), name );
		case -1:
			/* translators: %s: A weekday name. */
			return sprintf( _x( 'last %s', 'weekday in a month', 'gatherpress' ), name );
		default:
			// Places beyond these aren't rule values.
			return '';
	}
}

/**
 * The label of a monthly choice, from its day of the month or its weekday's place.
 *
 * @since TBD
 *
 * @param {Object} part A pattern's `month_day` and `ordinal_weekday`.
 *
 * @return {string} Such as "On day 11" or "On the second Friday".
 */
export function monthlyLabel( part ) {
	const ordinal = part?.ordinal_weekday;

	if ( Array.isArray( ordinal ) ) {
		/* translators: %s: A weekday's place in the month, such as "second Friday". */
		return sprintf( __( 'On the %s', 'gatherpress' ), ordinalWeekday( ordinal[ 0 ], ordinal[ 1 ] ) );
	}

	/* translators: %d: Day of the month. */
	return sprintf( __( 'On day %d', 'gatherpress' ), part?.month_day ?? 1 );
}

/**
 * The ways a monthly pattern can follow a date.
 *
 * By its day of the month, by its weekday's place in the month (the
 * second Friday), and by being the last such weekday. A fifth weekday can
 * only be the last, and a fourth that is also the last offers both.
 *
 * @since TBD
 *
 * @param {string} date A date, as `YYYY-MM-DD` or a datetime starting with it.
 *
 * @return {Array<{value: string, label: string, pattern: Object}>} The choices.
 */
export function monthlyChoices( date ) {
	const day = moment( date.slice( 0, 10 ) );
	const weekday = weekdayOf( date );
	const place = Math.ceil( day.date() / 7 );
	const parts = [ { value: 'day', pattern: { month_day: day.date(), ordinal_weekday: null } } ];

	if ( 4 >= place ) {
		parts.push( { value: 'weekday', pattern: { month_day: null, ordinal_weekday: [ place, weekday ] } } );
	}

	if ( day.clone().add( 7, 'days' ).month() !== day.month() ) {
		parts.push( { value: 'last', pattern: { month_day: null, ordinal_weekday: [ -1, weekday ] } } );
	}

	return parts.map( ( part ) => ( { ...part, label: monthlyLabel( part.pattern ) } ) );
}

/**
 * Which of a date's monthly choices a pattern is, if any.
 *
 * @since TBD
 *
 * @param {Array<Object>} choices The choices.
 * @param {Object}        pattern The pattern.
 *
 * @return {string|null} The choice's value, or null when the pattern picks another day.
 */
export function matchingChoice( choices, pattern ) {
	const same = ( a, b ) => JSON.stringify( a ?? null ) === JSON.stringify( b ?? null );
	const match = choices.find(
		( choice ) =>
			same( choice.pattern.month_day, pattern?.month_day ) &&
			same( choice.pattern.ordinal_weekday, pattern?.ordinal_weekday ),
	);

	return match ? match.value : null;
}

/**
 * A new pattern for a frequency, taking its days from a date.
 *
 * @since TBD
 *
 * @param {string} frequency `DAILY`, `WEEKLY`, `MONTHLY` or `YEARLY`.
 * @param {string} date      A date, as `YYYY-MM-DD` or a datetime starting with it.
 *
 * @return {Object} The pattern.
 */
export function defaultPattern( frequency, date ) {
	const pattern = {
		frequency,
		interval: 1,
		weekdays: [],
		month_day: null,
		ordinal_weekday: null,
		ends_on: null,
	};

	if ( 'WEEKLY' === frequency ) {
		pattern.weekdays = [ weekdayOf( date ) ];
	} else if ( 'MONTHLY' === frequency ) {
		pattern.month_day = moment( date.slice( 0, 10 ) ).date();
	}

	return pattern;
}

/**
 * An end date to start from when a pattern is given one: three months after a date.
 *
 * @since TBD
 *
 * @param {string} date A date, as `YYYY-MM-DD` or a datetime starting with it.
 *
 * @return {string} The date, as `YYYY-MM-DD`.
 */
export function defaultEndDate( date ) {
	return moment( date.slice( 0, 10 ) ).add( 3, 'months' ).format( 'YYYY-MM-DD' );
}

/**
 * The parts of a pattern the editor edits, from a pattern the field read.
 *
 * @since TBD
 *
 * @param {Object|null} pattern The pattern, as the field describes a rule.
 *
 * @return {Object|null} The editable pattern, or null when the event doesn't repeat.
 */
export function editablePattern( pattern ) {
	if ( ! pattern?.frequency ) {
		return null;
	}

	return {
		frequency: pattern.frequency,
		interval: pattern.interval ?? 1,
		weekdays: pattern.weekdays ?? [],
		month_day: pattern.month_day ?? null,
		ordinal_weekday: pattern.ordinal_weekday ?? null,
		ends_on: pattern.ends_on ?? null,
		...( pattern.times ? { times: pattern.times } : {} ),
		...( pattern.duration ? { duration: pattern.duration } : {} ),
	};
}

/**
 * The label of the interval field for a frequency.
 *
 * @since TBD
 *
 * @param {string} frequency The frequency.
 *
 * @return {string} Such as "Every how many weeks".
 */
export function intervalLabel( frequency ) {
	switch ( frequency ) {
		case 'DAILY':
			return __( 'Every how many days', 'gatherpress' );
		case 'WEEKLY':
			return __( 'Every how many weeks', 'gatherpress' );
		case 'MONTHLY':
			return __( 'Every how many months', 'gatherpress' );
		default:
			return __( 'Every how many years', 'gatherpress' );
	}
}

/**
 * A date in the site's date format.
 *
 * @since TBD
 *
 * @param {string} date A date, as `YYYY-MM-DD`.
 *
 * @return {string} The formatted date.
 */
export function formatDate( date ) {
	return dateI18n( getSettings().formats.date, date );
}

/**
 * How often a pattern repeats, as a sentence's start, such as "Every 2 weeks on Monday and Friday".
 *
 * Each frequency and form has a whole string with its plural forms, so a
 * translator controls the word order and agreement.
 *
 * @since TBD
 *
 * @param {Object} pattern The pattern.
 *
 * @return {string} The phrase.
 */
export function repeatPhrase( pattern ) {
	const count = pattern.interval ?? 1;

	if ( 'WEEKLY' === pattern.frequency ) {
		return weeklyPhrase( pattern, count );
	}

	if ( 'MONTHLY' === pattern.frequency ) {
		return monthlyPhrase( pattern, count );
	}

	if ( 'DAILY' === pattern.frequency ) {
		if ( 1 === count ) {
			return __( 'Every day', 'gatherpress' );
		}

		/* translators: %d: A number of days. */
		return sprintf( _n( 'Every %d day', 'Every %d days', count, 'gatherpress' ), count );
	}

	if ( 1 === count ) {
		return __( 'Every year', 'gatherpress' );
	}

	/* translators: %d: A number of years. */
	return sprintf( _n( 'Every %d year', 'Every %d years', count, 'gatherpress' ), count );
}

/**
 * How often a weekly pattern repeats, such as "Every 2 weeks on Monday and Friday".
 *
 * @since TBD
 *
 * @param {Object} pattern A weekly pattern.
 * @param {number} count   Its interval.
 *
 * @return {string} The phrase.
 */
export function weeklyPhrase( pattern, count ) {
	const days = listOf( ( pattern.weekdays ?? [] ).map( weekdayName ) );

	if ( ! days && 1 === count ) {
		return __( 'Every week', 'gatherpress' );
	}

	if ( ! days ) {
		/* translators: %d: A number of weeks. */
		return sprintf( _n( 'Every %d week', 'Every %d weeks', count, 'gatherpress' ), count );
	}

	if ( 1 === count ) {
		/* translators: %s: A list of weekday names. */
		return sprintf( __( 'Every week on %s', 'gatherpress' ), days );
	}

	return sprintf(
		/* translators: 1: A number of weeks. 2: A list of weekday names. */
		_n( 'Every %1$d week on %2$s', 'Every %1$d weeks on %2$s', count, 'gatherpress' ),
		count,
		days,
	);
}

/**
 * How often a monthly pattern repeats, such as "Every month on the second Friday".
 *
 * @since TBD
 *
 * @param {Object} pattern A monthly pattern.
 * @param {number} count   Its interval.
 *
 * @return {string} The phrase.
 */
export function monthlyPhrase( pattern, count ) {
	const ordinal = pattern.ordinal_weekday;
	const place = Array.isArray( ordinal ) ? ordinalWeekday( ordinal[ 0 ], ordinal[ 1 ] ) : '';

	if ( place && 1 === count ) {
		/* translators: %s: A weekday's place in the month, such as "second Friday". */
		return sprintf( __( 'Every month on the %s', 'gatherpress' ), place );
	}

	if ( place ) {
		return sprintf(
			/* translators: 1: A number of months. 2: A weekday's place in the month, such as "second Friday". */
			_n( 'Every %1$d month on the %2$s', 'Every %1$d months on the %2$s', count, 'gatherpress' ),
			count,
			place,
		);
	}

	if ( pattern.month_day && 1 === count ) {
		/* translators: %d: Day of the month. */
		return sprintf( __( 'Every month on day %d', 'gatherpress' ), pattern.month_day );
	}

	if ( pattern.month_day ) {
		return sprintf(
			/* translators: 1: A number of months. 2: Day of the month. */
			_n( 'Every %1$d month on day %2$d', 'Every %1$d months on day %2$d', count, 'gatherpress' ),
			count,
			pattern.month_day,
		);
	}

	if ( 1 === count ) {
		return __( 'Every month', 'gatherpress' );
	}

	/* translators: %d: A number of months. */
	return sprintf( _n( 'Every %d month', 'Every %d months', count, 'gatherpress' ), count );
}

/**
 * A sentence describing a pattern, such as "Every 2 weeks on Monday and Friday, until March 1, 2027."
 *
 * @since TBD
 *
 * @param {Object|null} pattern The pattern.
 *
 * @return {string} The sentence, or "Does not repeat."
 */
export function summarize( pattern ) {
	if ( ! pattern ) {
		return __( 'Does not repeat.', 'gatherpress' );
	}

	if ( ! pattern.ends_on ) {
		/* translators: %s: How often an event repeats, such as "Every week on Monday". */
		return sprintf( __( '%s.', 'gatherpress' ), repeatPhrase( pattern ) );
	}

	return sprintf(
		/* translators: 1: How often an event repeats, such as "Every week on Monday". 2: A date. */
		__( '%1$s, until %2$s.', 'gatherpress' ),
		repeatPhrase( pattern ),
		formatDate( pattern.ends_on ),
	);
}

/**
 * A list of names joined for a sentence.
 *
 * @since TBD
 *
 * @param {string[]} names The names.
 *
 * @return {string} Such as "Monday, Wednesday and Friday".
 */
export function listOf( names ) {
	if ( 1 >= names.length ) {
		return names[ 0 ] ?? '';
	}

	return sprintf(
		/* translators: 1: A comma-separated list. 2: The last item. */
		__( '%1$s and %2$s', 'gatherpress' ),
		// A list separator; translators can change it, such as to "、".
		names.slice( 0, -1 ).join( _x( ',', 'list separator', 'gatherpress' ) + ' ' ),
		names[ names.length - 1 ],
	);
}

/**
 * Tomorrow in a timezone, the earliest a change to a started series can apply.
 *
 * @since TBD
 *
 * @param {string} timezone The event's timezone.
 *
 * @return {string} The date, as `YYYY-MM-DD`.
 */
export function tomorrowIn( timezone ) {
	// moment.tz() can't read a manual offset such as +10:00.
	const now = isManualOffset( timezone ) ? moment().utcOffset( timezone ) : moment().tz( timezone );

	return now.add( 1, 'day' ).format( 'YYYY-MM-DD' );
}

/**
 * Whether a saved event was published and its saved start has passed.
 *
 * The saved state decides, as on the server, so the first publish of an
 * event isn't treated as a change to a running series.
 *
 * @since TBD
 *
 * @param {string} status    The saved post status.
 * @param {string} savedJson The saved `gatherpress_datetime` meta.
 *
 * @return {boolean} True for a started, published event.
 */
export function hasStarted( status, savedJson ) {
	let saved = {};

	try {
		saved = savedJson ? JSON.parse( savedJson ) : {};
	} catch {
		// Without a readable saved start, the event isn't treated as started.
		return false;
	}

	if ( ! PUBLISHED_STATUSES.includes( status ) || ! saved?.dateTimeStart ) {
		return false;
	}

	return createMomentWithTimezone( saved.dateTimeStart, saved.timezone || 'UTC' ).isSameOrBefore( moment() );
}

/**
 * The field's value to save for an edited pattern.
 *
 * Without a change date, the event's own dates give the rule's time and
 * length, so the pattern's are left out.
 *
 * @since TBD
 *
 * @param {Object|null} pattern        The edited pattern, or null to stop repeating.
 * @param {Object}      options        Options.
 * @param {string}      options.from   The date a change to a started series applies, if any.
 * @param {boolean}     options.cancel Whether to cancel a scheduled change.
 *
 * @return {Object} The value.
 */
export function toRequest( pattern, { from = null, cancel = false } = {} ) {
	let next = pattern;

	if ( pattern && ! from ) {
		next = { ...pattern };
		delete next.times;
		delete next.duration;
	}

	return {
		pattern: next,
		...( from ? { from } : {} ),
		...( cancel ? { scheduled: null } : {} ),
	};
}

/**
 * Whether the edited event is a repeating series whose first date has passed, which freezes that date.
 *
 * A pattern picked but not saved yet counts too, since the save that adds
 * it can't also move the first date.
 *
 * @since TBD
 *
 * @return {boolean} True for a started, published series.
 */
export function useIsStartedSeries() {
	return useSelect( ( select ) => {
		const editor = select( 'core/editor' );
		const repeats =
			editor.getCurrentPostAttribute?.( REPEAT_FIELD )?.pattern ||
			editor.getEditedPostAttribute?.( REPEAT_FIELD )?.pattern;

		return (
			Boolean( repeats ) &&
			hasStarted(
				editor.getCurrentPostAttribute?.( 'status' ),
				editor.getCurrentPostAttribute?.( 'meta' )?.gatherpress_datetime,
			)
		);
	}, [] );
}
