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
		 * The event's time as the viewer's own browser states it.
		 *
		 * The server cannot know the viewer's timezone, so `render.php` emits a
		 * context payload carrying the event's GMT datetimes and its own
		 * timezone, and this derives the label in the browser. Deriving it from
		 * the block's context rather than filling the element once on load is
		 * what lets the label follow a client-side page change, which the
		 * block's `interactivity` support promises.
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

		/**
		 * Whether the viewer local time is active for this block.
		 *
		 * @since 0.36.0
		 *
		 * @return {boolean} True when a viewer time label is available.
		 */
		get hasViewerTime() {
			const { state } = store( 'gatherpress' );

			return Boolean( state.viewerTimeLabel );
		},

		/**
		 * Tabindex for the datetime element when a tooltip is present.
		 *
		 * Makes the element keyboard-focusable only when there is a tooltip
		 * to display, matching WCAG 2.1.1 keyboard navigation requirements.
		 *
		 * @since 0.36.0
		 *
		 * @return {string|undefined} '0' when tooltip is active, undefined otherwise.
		 */
		get viewerTimeTabIndex() {
			const { state } = store( 'gatherpress' );

			return state.hasViewerTime ? '0' : undefined;
		},

		/**
		 * Visually hidden text announced to screen reader users.
		 *
		 * Accompanies the tooltip so non-sighted users hear the converted
		 * time immediately after the event's datetime.
		 *
		 * @since 0.36.0
		 *
		 * @return {string} The parenthesized label, or empty string.
		 */
		get viewerTimeSrLabel() {
			const { state } = store( 'gatherpress' );

			return state.viewerTimeLabel ? ` (${ state.viewerTimeLabel })` : '';
		},
	},
} );
