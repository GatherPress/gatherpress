/**
 * WordPress dependencies
 */
import { __, _n, _x, sprintf } from '@wordpress/i18n';
import { dateI18n } from '@wordpress/date';
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
 * The ways a monthly pattern can follow a start date.
 *
 * By its day of the month, by its weekday's place in the month (the
 * second Friday), and by being the last such weekday. A fifth weekday can
 * only be the last, and a fourth that is also the last offers both.
 *
 * @since TBD
 *
 * @param {string} date The start date, as `YYYY-MM-DD` or a datetime starting with it.
 *
 * @return {Array<{value: string, label: string, pattern: Object}>} The choices.
 */
export function monthlyChoices( date ) {
	const day = moment( date.slice( 0, 10 ) );
	const weekday = weekdayOf( date );
	const place = Math.ceil( day.date() / 7 );
	const isLast = day.clone().add( 7, 'days' ).month() !== day.month();
	const ordinals = {
		1: __( 'first', 'gatherpress' ),
		2: __( 'second', 'gatherpress' ),
		3: __( 'third', 'gatherpress' ),
		4: __( 'fourth', 'gatherpress' ),
	};
	const choices = [
		{
			value: 'day',
			/* translators: %d: Day of the month. */
			label: sprintf( __( 'On day %d', 'gatherpress' ), day.date() ),
			pattern: { month_day: day.date(), ordinal_weekday: null },
		},
	];

	if ( 4 >= place ) {
		choices.push( {
			value: 'weekday',
			label: sprintf(
				/* translators: 1: An ordinal such as "second". 2: A weekday name. */
				__( 'On the %1$s %2$s', 'gatherpress' ),
				ordinals[ place ],
				weekdayName( weekday ),
			),
			pattern: { month_day: null, ordinal_weekday: [ place, weekday ] },
		} );
	}

	if ( isLast ) {
		choices.push( {
			value: 'last',
			label: sprintf(
				/* translators: %s: A weekday name. */
				__( 'On the last %s', 'gatherpress' ),
				weekdayName( weekday ),
			),
			pattern: { month_day: null, ordinal_weekday: [ -1, weekday ] },
		} );
	}

	return choices;
}

/**
 * Which monthly choice a pattern uses.
 *
 * @since TBD
 *
 * @param {Object} pattern A pattern.
 *
 * @return {string} `day`, `weekday` or `last`.
 */
export function monthlyChoiceOf( pattern ) {
	const ordinal = pattern?.ordinal_weekday;

	if ( ! Array.isArray( ordinal ) ) {
		return 'day';
	}

	return -1 === ordinal[ 0 ] ? 'last' : 'weekday';
}

/**
 * A new pattern for a frequency, taking its days from the start date.
 *
 * @since TBD
 *
 * @param {string} frequency `DAILY`, `WEEKLY`, `MONTHLY` or `YEARLY`.
 * @param {string} date      The start date, as `YYYY-MM-DD` or a datetime starting with it.
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
 * The unit an interval counts in, for its label.
 *
 * @since TBD
 *
 * @param {string} frequency The frequency.
 * @param {number} interval  The interval.
 *
 * @return {string} The unit, such as "weeks".
 */
export function intervalUnit( frequency, interval ) {
	switch ( frequency ) {
		case 'DAILY':
			return _n( 'day', 'days', interval, 'gatherpress' );
		case 'WEEKLY':
			return _n( 'week', 'weeks', interval, 'gatherpress' );
		case 'MONTHLY':
			return _n( 'month', 'months', interval, 'gatherpress' );
		default:
			return _n( 'year', 'years', interval, 'gatherpress' );
	}
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

	const interval = pattern.interval ?? 1;
	let every = sprintf(
		/* translators: %s: A unit, such as "week". */
		__( 'Every %s', 'gatherpress' ),
		intervalUnit( pattern.frequency, 1 ),
	);

	if ( 1 !== interval ) {
		every = sprintf(
			/* translators: 1: A number. 2: A unit, such as "weeks". */
			__( 'Every %1$d %2$s', 'gatherpress' ),
			interval,
			intervalUnit( pattern.frequency, interval ),
		);
	}

	const parts = [ every ];

	if ( 'WEEKLY' === pattern.frequency && pattern.weekdays?.length ) {
		parts.push(
			sprintf(
				/* translators: %s: A list of weekday names. */
				__( 'on %s', 'gatherpress' ),
				listOf( pattern.weekdays.map( weekdayName ) ),
			),
		);
	} else if ( 'MONTHLY' === pattern.frequency ) {
		parts.push( monthlyPhrase( pattern ) );
	}

	const sentence = parts.filter( Boolean ).join( ' ' );

	if ( ! pattern.ends_on ) {
		/* translators: %s: A repeating pattern. */
		return sprintf( __( '%s.', 'gatherpress' ), sentence );
	}

	return sprintf(
		/* translators: 1: A repeating pattern. 2: A date. */
		__( '%1$s, until %2$s.', 'gatherpress' ),
		sentence,
		dateI18n( 'F j, Y', pattern.ends_on ),
	);
}

/**
 * How a monthly pattern picks its day, from the pattern itself.
 *
 * @since TBD
 *
 * @param {Object} pattern A monthly pattern.
 *
 * @return {string} Such as "on the second Friday", or empty when the pattern names no day.
 */
export function monthlyPhrase( pattern ) {
	const ordinal = pattern.ordinal_weekday;
	const places = {
		1: __( 'first', 'gatherpress' ),
		2: __( 'second', 'gatherpress' ),
		3: __( 'third', 'gatherpress' ),
		4: __( 'fourth', 'gatherpress' ),
		'-1': __( 'last', 'gatherpress' ),
	};

	if ( Array.isArray( ordinal ) && places[ ordinal[ 0 ] ] ) {
		return sprintf(
			/* translators: 1: An ordinal such as "second" or "last". 2: A weekday name. */
			__( 'on the %1$s %2$s', 'gatherpress' ),
			places[ ordinal[ 0 ] ],
			weekdayName( ordinal[ 1 ] ),
		);
	}

	return pattern.month_day
		? /* translators: %d: Day of the month. */ sprintf( __( 'on day %d', 'gatherpress' ), pattern.month_day )
		: '';
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
	const now = isManualOffset( timezone )
		? moment().utcOffset( timezone )
		: moment().tz( timezone );

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

	return createMomentWithTimezone(
		saved.dateTimeStart,
		saved.timezone || 'UTC',
	).isSameOrBefore( moment() );
}

/**
 * The field's value to save for an edited pattern.
 *
 * @since TBD
 *
 * @param {Object|null} pattern The edited pattern, or null to stop repeating.
 * @param {string|null} from    The date a change to a started series applies, if any.
 *
 * @return {Object} The value.
 */
export function toRequest( pattern, from = null ) {
	return {
		pattern,
		...( from ? { from } : {} ),
	};
}

/**
 * Whether the edited event is a repeating series whose first date has passed, which freezes that date.
 *
 * @since TBD
 *
 * @return {boolean} True for a started, published series.
 */
export function useIsStartedSeries() {
	return useSelect( ( select ) => {
		const editor = select( 'core/editor' );

		return (
			Boolean( editor.getCurrentPostAttribute?.( REPEAT_FIELD )?.pattern ) &&
			hasStarted(
				editor.getCurrentPostAttribute?.( 'status' ),
				editor.getCurrentPostAttribute?.( 'meta' )?.gatherpress_datetime,
			)
		);
	}, [] );
}
