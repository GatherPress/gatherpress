/**
 * Internal dependencies
 */
import { getViewerTimeLabel } from './viewer-time';

/**
 * Fill in the viewer's local time on every event date that asked for it.
 *
 * The server cannot know the reader's timezone, so `render.php` emits an empty
 * placeholder carrying the event's GMT datetimes and its own timezone, and this
 * fills it once in the browser. The placeholder stays empty and hidden when the
 * reader is already in the event's timezone, which is the common case for a
 * local group and would otherwise be a line of noise on every event.
 *
 * @since 0.35.0
 *
 * @return {void}
 */
function renderViewerTimes() {
	const elements = document.querySelectorAll(
		'.gatherpress-event-date__viewer-time[data-gatherpress-start-gmt]'
	);

	elements.forEach( ( element ) => {
		const label = getViewerTimeLabel( {
			startGmt: element.dataset.gatherpressStartGmt || '',
			endGmt: element.dataset.gatherpressEndGmt || '',
			eventTimezone: element.dataset.gatherpressTimezone || '',
		} );

		if ( ! label ) {
			return;
		}

		element.textContent = label;
		element.hidden = false;
	} );
}

if ( 'loading' === document.readyState ) {
	document.addEventListener( 'DOMContentLoaded', renderViewerTimes );
} else {
	renderViewerTimes();
}
