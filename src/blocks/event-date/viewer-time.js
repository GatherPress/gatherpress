/**
 * WordPress dependencies
 */
import { __, sprintf } from '@wordpress/i18n';

/**
 * Resolve the timezone the browser is running in.
 *
 * @since 0.35.0
 *
 * @return {string} An IANA timezone name, or an empty string when the browser will not say.
 */
export function getViewerTimezone() {
	try {
		return Intl.DateTimeFormat().resolvedOptions().timeZone || '';
	} catch {
		// Intl is unavailable or refused the call: no viewer time to offer.
		return '';
	}
}

/**
 * Format a GMT datetime in a given timezone.
 *
 * Returns an empty string rather than throwing when the timezone is not one
 * `Intl` accepts. GatherPress allows manual UTC offsets ("+05:30") as an event
 * timezone, and `Intl` only takes IANA names.
 *
 * @since 0.35.0
 *
 * @param {string} gmt      Datetime in `Y-m-d H:i:s` GMT, as stored in event meta.
 * @param {string} timezone IANA timezone name to render in.
 * @param {Object} options  `Intl.DateTimeFormat` options.
 * @param {string} locale   BCP 47 locale tag.
 *
 * @return {string} The formatted datetime, or an empty string when it could not be formatted.
 */
export function formatInTimezone( gmt, timezone, options, locale ) {
	if ( ! gmt || ! timezone ) {
		return '';
	}

	// The stored shape is `Y-m-d H:i:s` in GMT. `Date` parses the `T`/`Z` form
	// reliably across browsers, where the space-separated form is implementation
	// defined.
	const date = new Date( `${ gmt.replace( ' ', 'T' ) }Z` );

	if ( isNaN( date.getTime() ) ) {
		return '';
	}

	try {
		return new Intl.DateTimeFormat( locale, {
			...options,
			timeZone: timezone,
		} ).format( date );
	} catch {
		// Not an IANA name (a manual offset, or a name this browser lacks).
		return '';
	}
}

/**
 * Build the "in your timezone" label for an event.
 *
 * Returns an empty string whenever there is nothing worth saying: no start
 * time, an unreadable timezone, or a viewer already in the event's timezone.
 * The label carries the date as well as the time when the event falls on a
 * different calendar day for the viewer, which is the case that actually
 * misleads people: an evening event in New York is the next morning in Tokyo.
 *
 * @since 0.35.0
 *
 * @param {Object} args                Label inputs.
 * @param {string} args.startGmt       Event start in `Y-m-d H:i:s` GMT.
 * @param {string} args.endGmt         Optional event end in `Y-m-d H:i:s` GMT.
 * @param {string} args.eventTimezone  The event's own timezone.
 * @param {string} args.viewerTimezone Optional override for the browser timezone, for tests.
 * @param {string} args.locale         Optional BCP 47 locale tag.
 *
 * @return {string} The label, or an empty string when there is nothing to add.
 */
export function getViewerTimeLabel( {
	startGmt = '',
	endGmt = '',
	eventTimezone = '',
	viewerTimezone = undefined,
	locale = undefined,
} = {} ) {
	const viewer = viewerTimezone ?? getViewerTimezone();

	if ( ! startGmt || ! viewer || viewer === eventTimezone ) {
		return '';
	}

	const timeOptions = { hour: 'numeric', minute: '2-digit' };
	const dayOptions = { year: 'numeric', month: 'numeric', day: 'numeric' };

	const viewerStart = formatInTimezone( startGmt, viewer, timeOptions, locale );

	if ( ! viewerStart ) {
		return '';
	}

	// Same instant, both calendars: when they disagree the label needs the date.
	const viewerDay = formatInTimezone( startGmt, viewer, dayOptions, locale );
	const eventDay = formatInTimezone( startGmt, eventTimezone, dayOptions, locale );
	const spansDays = !! eventDay && viewerDay !== eventDay;

	const start = spansDays
		? formatInTimezone(
			startGmt,
			viewer,
			{ ...dayOptions, ...timeOptions },
			locale
		)
		: viewerStart;

	const viewerEnd = endGmt
		? formatInTimezone( endGmt, viewer, timeOptions, locale )
		: '';

	if ( viewerEnd ) {
		return sprintf(
			/* translators: 1: event start in the viewer's timezone, 2: event end in the viewer's timezone. */
			__( '%1$s to %2$s your time', 'gatherpress' ),
			start,
			viewerEnd
		);
	}

	return sprintf(
		/* translators: %s: event start in the viewer's timezone. */
		__( '%s your time', 'gatherpress' ),
		start
	);
}
