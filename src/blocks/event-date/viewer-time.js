/**
 * Resolve the timezone the browser is running in.
 *
 * @since 0.36.0
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
 * timezone, and every engine this plugin supports accepts those alongside IANA
 * names. That acceptance is not universal, though: an engine predating the
 * ECMA-402 change that required offset time zones rejects "+05:30", and the
 * empty string returned here is what keeps that a degradation rather than a
 * break. There, `isSameZoneAt()` below can no longer match an offset-configured
 * site against the reader's own zone, so such a reader sees a local-time line
 * repeating a time they were already reading.
 *
 * @since 0.36.0
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
 * Whether two timezones put the same instant on the same wall clock.
 *
 * A site can store its timezone as a manual UTC offset rather than a city, and
 * `wp_timezone_string()` returns exactly that for an offset-configured site. A
 * name comparison would then tell every reader their time differs from an event
 * time they are already reading, so this compares what the two zones resolve to
 * for the event's own instant instead.
 *
 * Only that one instant is compared. A daylight-saving transition inside the
 * event window in exactly one of the two zones would make them agree here and
 * diverge before the event ends, and the label stays suppressed. The instant
 * the label is anchored to is the one readers plan around, so that is the one
 * worth being right about.
 *
 * @since 0.36.0
 *
 * @param {string} gmt            Event instant in `Y-m-d H:i:s` GMT.
 * @param {string} viewerTimezone The reader's timezone.
 * @param {string} eventTimezone  The event's own timezone.
 *
 * @return {boolean} True when both timezones show that instant identically.
 */
function isSameZoneAt( gmt, viewerTimezone, eventTimezone ) {
	if ( viewerTimezone === eventTimezone ) {
		return true;
	}

	// A fixed locale keeps the comparison about the two zones rather than about
	// how the reader's locale happens to punctuate a date.
	const wallClockOptions = {
		year: 'numeric',
		month: 'numeric',
		day: 'numeric',
		hour: 'numeric',
		minute: '2-digit',
	};
	const viewerWallClock = formatInTimezone(
		gmt,
		viewerTimezone,
		wallClockOptions,
		'en-US'
	);

	return (
		!! viewerWallClock &&
		viewerWallClock ===
			formatInTimezone( gmt, eventTimezone, wallClockOptions, 'en-US' )
	);
}

/**
 * Fill a translated format string the way `sprintf` would.
 *
 * The frontend consumer is a script module, which cannot import
 * `@wordpress/i18n`, so its `sprintf` is out of reach here. Replacing a literal
 * `%s` is not a substitute: it fills only the first copy of a placeholder,
 * leaves `%%` doubled, and cannot see `%1$s` as the same placeholder as `%s`.
 * That last one is the one that actually reaches readers, because translators
 * normalize a lone `%s` to `%1$s` as a matter of course, and the sentence would
 * then carry the raw token instead of a time. Mirrors the positional-tolerant
 * substitution `helpers/interactivity.js` does for the attendee count.
 *
 * A placeholder no value answers is left as written, so a translation that
 * invents an extra one shows the token rather than the word "undefined".
 *
 * @since 0.36.0
 *
 * @param {string}   format Translated format string.
 * @param {string[]} values Values to substitute, in `%1$s` order.
 *
 * @return {string} The format with its placeholders filled.
 */
function fillFormat( format, values ) {
	let next = 0;

	return format.replace(
		/%(?:(\d+)\$)?([%s])/g,
		( placeholder, position, conversion ) => {
			if ( 's' !== conversion ) {
				return '%';
			}

			const value = position
				? values[ Number( position ) - 1 ]
				: values[ next++ ];

			return undefined === value ? placeholder : String( value );
		}
	);
}

/**
 * Build the "in your timezone" label for an event.
 *
 * Returns an empty string whenever there is nothing worth saying: no datetime
 * at all, an unreadable timezone, or a viewer already in the event's timezone.
 * The label carries the date as well as the time when the event falls on a
 * different calendar day for the viewer, which is the case that actually
 * misleads people: an evening event in New York is the next morning in Tokyo.
 *
 * A caller passing an end but no start gets the end converted on its own,
 * dated by the same rule. That is a block set to display only the end, and it
 * mirrors `Event::get_display_datetime()`, which shows the end alone there
 * rather than showing nothing.
 *
 * The two sentence formats are passed in rather than translated here: the
 * frontend consumer is a script module, which cannot import `@wordpress/i18n`,
 * so it receives them server-translated in the block's `data-wp-context`. The
 * English defaults are the fallback for a caller that has nothing better.
 *
 * @since 0.36.0
 *
 * @param {Object} args                Label inputs.
 * @param {string} args.startGmt       Event start in `Y-m-d H:i:s` GMT.
 * @param {string} args.endGmt         Optional event end in `Y-m-d H:i:s` GMT.
 * @param {string} args.eventTimezone  The event's own timezone.
 * @param {string} args.viewerTimezone Optional override for the browser timezone, for tests.
 * @param {string} args.locale         Optional BCP 47 locale tag.
 * @param {string} args.rangeFormat    Translated format taking `%1$s` start and `%2$s` end.
 * @param {string} args.singleFormat   Translated format taking `%s` for the one time shown.
 *
 * @return {string} The label, or an empty string when there is nothing to add.
 */
export function getViewerTimeLabel( {
	startGmt = '',
	endGmt = '',
	eventTimezone = '',
	viewerTimezone = undefined,
	locale = undefined,
	rangeFormat = '%1$s to %2$s your time',
	singleFormat = '%s your time',
} = {} ) {
	const viewer = viewerTimezone ?? getViewerTimezone();

	// An end-only block has no start to convert, so its end becomes the one
	// time the label speaks about, and there is no range left to close.
	const labelStartGmt = startGmt || endGmt;
	const labelEndGmt = startGmt ? endGmt : '';

	if (
		! labelStartGmt ||
		! viewer ||
		isSameZoneAt( labelStartGmt, viewer, eventTimezone )
	) {
		return '';
	}

	const timeOptions = { hour: 'numeric', minute: '2-digit' };
	const dayOptions = { year: 'numeric', month: 'numeric', day: 'numeric' };

	const viewerStart = formatInTimezone(
		labelStartGmt,
		viewer,
		timeOptions,
		locale
	);

	if ( ! viewerStart ) {
		return '';
	}

	// Same instant, both calendars: when they disagree the label needs the date.
	const viewerDay = formatInTimezone( labelStartGmt, viewer, dayOptions, locale );
	const eventDay = formatInTimezone(
		labelStartGmt,
		eventTimezone,
		dayOptions,
		locale
	);
	const spansDays = !! eventDay && viewerDay !== eventDay;

	const start = spansDays
		? formatInTimezone(
			labelStartGmt,
			viewer,
			{ ...dayOptions, ...timeOptions },
			locale
		)
		: viewerStart;

	// Mirrors `Event::get_display_datetime()`, which picks `get_time_end()` over
	// `get_datetime_end()` only while the two ends share a date. Measured in the
	// viewer's calendar here, because that is the one the label speaks in.
	const viewerEndDay = labelEndGmt
		? formatInTimezone( labelEndGmt, viewer, dayOptions, locale )
		: '';
	const endSpansDays = !! viewerEndDay && viewerEndDay !== viewerDay;

	const viewerEnd = labelEndGmt
		? formatInTimezone(
			labelEndGmt,
			viewer,
			endSpansDays ? { ...dayOptions, ...timeOptions } : timeOptions,
			locale
		)
		: '';

	if ( viewerEnd ) {
		return fillFormat( rangeFormat, [ start, viewerEnd ] );
	}

	return fillFormat( singleFormat, [ start ] );
}
