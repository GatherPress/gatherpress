/**
 * WordPress dependencies
 */
import { __, sprintf } from '@wordpress/i18n';
import { Button } from '@wordpress/components';
import { dateI18n, getSettings } from '@wordpress/date';

/**
 * One row in the occurrence list: its date, its status, and the
 * cancel/restore action.
 *
 * An organizer skips a holiday without touching the rest of the series. This
 * row is the per-occurrence surface that action lives on. Canceling writes
 * the occurrence's `status` column via the `occurrence-status` REST route, and
 * never touches the rule.
 *
 * The date is formatted from the row's GMT instant in the row's own named
 * timezone, never from the timezone-free local `datetime_start`. A bare
 * local string handed to `dateI18n()` is parsed in the browser timezone and
 * then converted to the site timezone, so an 18:00 New York occurrence can
 * print as 11:00 for a traveling organizer. Converting the MySQL GMT value
 * to ISO UTC pins the instant, and the `timezone` column names the clock to
 * render it on; the column is nullable, and an empty value falls back to
 * `dateI18n()`'s own default, the site timezone.
 *
 * That same formatted date is also the button's accessible name. A series
 * renders one row per occurrence, so a button named only "Cancel" gives every
 * destructive action in the list an identical name: a screen reader user
 * pulling up the button list, or reaching one by voice, has nothing to tell
 * the September 3rd meetup from the September 17th one. The visible label
 * stays the first word of the accessible name, which is what keeps a voice
 * command of "Cancel" a prefix match rather than a mismatch (WCAG 2.5.3,
 * Label in Name).
 *
 * @since 0.36.0
 *
 * @param {Object}   props            Component props.
 * @param {Object}   props.occurrence Occurrence row, as returned by the `occurrences` REST route.
 * @param {Function} props.onToggle   Called with the occurrence when the action button is pressed.
 * @param {boolean}  props.isUpdating Whether a status change for this row is in flight.
 *
 * @return {JSX.Element} The occurrence row.
 */
const OccurrenceRow = ( { occurrence, onToggle, isUpdating } ) => {
	const isCanceled = 'canceled' === occurrence.status;
	const startUtc = `${ occurrence.datetime_start_gmt.replace( ' ', 'T' ) }Z`;
	const formattedDate = dateI18n(
		getSettings().formats.datetime,
		startUtc,
		occurrence.timezone || undefined,
	);
	const actionLabel = isCanceled
		? __( 'Restore', 'gatherpress' )
		: __( 'Cancel', 'gatherpress' );
	const actionName = isCanceled
		? sprintf(
			/* translators: %s: the occurrence date and time. */
			__( 'Restore %s', 'gatherpress' ),
			formattedDate,
		)
		: sprintf(
			/* translators: %s: the occurrence date and time. */
			__( 'Cancel %s', 'gatherpress' ),
			formattedDate,
		);

	return (
		<div className="gatherpress-occurrence-row">
			<span className="gatherpress-occurrence-row__date">
				{ formattedDate }
			</span>
			<span className="gatherpress-occurrence-row__status">
				{ isCanceled
					? __( 'Canceled', 'gatherpress' )
					: __( 'Scheduled', 'gatherpress' ) }
			</span>
			<Button
				variant="secondary"
				size="small"
				isBusy={ isUpdating }
				disabled={ isUpdating }
				aria-label={ actionName }
				onClick={ () => onToggle( occurrence ) }
			>
				{ actionLabel }
			</Button>
		</div>
	);
};

export default OccurrenceRow;
