/**
 * WordPress dependencies
 */
import { store, getContext } from '@wordpress/interactivity';

/**
 * Internal dependencies
 */
import { getViewerTimeLabel } from './viewer-time';

store( 'gatherpress', {
	state: {
		/**
		 * The event's time as the reader's own browser states it.
		 *
		 * The server cannot know the reader's timezone, so `render.php` emits an
		 * empty placeholder carrying the event's GMT datetimes and its own
		 * timezone, and this derives the label in the browser. Deriving it from
		 * the block's context rather than filling the element once on load is
		 * what lets the label follow a client-side page change, which the
		 * block's `interactivity` support promises. `render.php` binds the
		 * placeholder's `hidden` attribute to the negation of this, so the
		 * reader already in the event's timezone keeps seeing nothing.
		 *
		 * This is a script module, so `@wordpress/i18n` must not be imported
		 * here: the two sentence formats arrive server-translated in the same
		 * context payload.
		 *
		 * @since 0.36.0
		 *
		 * @return {string} The label, or an empty string when there is nothing to add.
		 */
		get viewerTimeLabel() {
			const context = getContext();

			return getViewerTimeLabel( {
				startGmt: context?.startGmt || '',
				endGmt: context?.endGmt || '',
				eventTimezone: context?.eventTimezone || '',
				rangeFormat: context?.rangeFormat || undefined,
				singleFormat: context?.singleFormat || undefined,
			} );
		},
	},
} );
