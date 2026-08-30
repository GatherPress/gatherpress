/**
 * WordPress dependencies
 */
import { Button } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { addQueryArgs, removeQueryArgs } from '@wordpress/url';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import EventSelect from '../components/EventSelect';

/**
 * The RSVP screen's event filter.
 *
 * The list table already filters on a `post_id` request parameter, so this is
 * a UI over a query that works today. Submitting navigates rather than posting
 * the surrounding form: the screen wraps its table in a POST form for bulk
 * actions, and a POST would apply the filter without putting it in the URL,
 * losing it on refresh and on every pagination link. Navigating keeps the
 * filter bookmarkable and matches how arriving from the Events screen already
 * works.
 *
 * Arriving with `post_id` already set prefills the control, so the screen shows
 * which event it is filtered to rather than an empty box over a filtered table.
 *
 * @since 0.36.0
 *
 * @param {Object}   props               Component props.
 * @param {string[]} props.postTypes     Event post types to search.
 * @param {number}   props.initialPostId Event ID from the current request, if any.
 * @param {string}   props.label         Control label.
 *
 * @return {JSX.Element} The filter control.
 */
export default function EventFilter( { postTypes, initialPostId, label } ) {
	const [ postId, setPostId ] = useState( initialPostId );

	/**
	 * Reload the screen filtered to the chosen event.
	 *
	 * @return {void}
	 */
	const applyFilter = () => {
		// `event` is the list table's older name for the same filter; drop it
		// so the two cannot disagree about which event is selected. Paging
		// resets because the current page number rarely exists in the
		// filtered result set.
		const base = removeQueryArgs(
			window.location.href,
			'event',
			'post_id',
			'paged'
		);

		window.location.href = postId
			? addQueryArgs( base, { post_id: postId } )
			: base;
	};

	return (
		<div className="gatherpress-rsvp-event-filter">
			<EventSelect
				label={ label }
				hideLabelFromVision
				value={ postId }
				postTypes={ postTypes }
				onChange={ setPostId }
			/>

			<Button variant="secondary" onClick={ applyFilter }>
				{ __( 'Filter', 'gatherpress' ) }
			</Button>
		</div>
	);
}
