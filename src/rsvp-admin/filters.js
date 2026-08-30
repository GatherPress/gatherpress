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
import ResponseFilter from './response-filter';

/**
 * Builds the URL one filter submission navigates to.
 *
 * An empty value drops its parameter rather than sending it blank, so clearing
 * a control and applying is how a filter is removed. Paging resets because the
 * current page rarely exists in the narrowed result. `event` is the list
 * table's older name for the event filter and is dropped so the two cannot
 * disagree about which event is selected.
 *
 * @since 0.36.0
 *
 * @param {string}      href      The current URL.
 * @param {number|null} postId    Selected event ID, if any.
 * @param {string[]}    responses Selected response statuses.
 *
 * @return {string} The URL to navigate to.
 */
export function buildFilterUrl( href, postId, responses ) {
	const base = removeQueryArgs(
		href,
		'event',
		'post_id',
		'response',
		'paged'
	);
	const args = {};

	if ( postId ) {
		args.post_id = postId;
	}

	if ( responses.length ) {
		args.response = responses.join( ',' );
	}

	return Object.keys( args ).length ? addQueryArgs( base, args ) : base;
}

/**
 * The RSVP screen's filters.
 *
 * Both controls sit behind one Filter button rather than one each: they narrow
 * the same list, and two buttons would leave the reader deciding which applies
 * what. Everything is composed into a single navigation.
 *
 * Submitting navigates rather than posting the surrounding form. The table is
 * wrapped in a POST form for bulk actions, and filtering through it would
 * apply the filter via `$_REQUEST` while leaving the URL unchanged, losing it
 * on refresh and on every pagination link.
 *
 * @since 0.36.0
 *
 * @param {Object}   props                  Component props.
 * @param {string[]} props.postTypes        Event post types to search.
 * @param {number}   props.initialPostId    Event ID from the current request, if any.
 * @param {string}   props.eventLabel       Label for the event picker.
 * @param {Object[]} props.statuses         Selectable response statuses.
 * @param {string[]} props.initialResponses Response statuses from the current request.
 *
 * @return {JSX.Element} The filter controls.
 */
export default function Filters( {
	postTypes,
	initialPostId,
	eventLabel,
	statuses,
	initialResponses,
} ) {
	const [ postId, setPostId ] = useState( initialPostId );
	const [ responses, setResponses ] = useState( initialResponses );

	/**
	 * Reload the screen with both filters applied.
	 *
	 * @return {void}
	 */
	const applyFilters = () => {
		window.location.href = buildFilterUrl(
			window.location.href,
			postId,
			responses
		);
	};

	return (
		<div className="gatherpress-rsvp-filters">
			<EventSelect
				label={ eventLabel }
				hideLabelFromVision
				value={ postId }
				postTypes={ postTypes }
				onChange={ setPostId }
			/>

			<ResponseFilter
				statuses={ statuses }
				selected={ responses }
				onChange={ setResponses }
			/>

			<Button variant="secondary" onClick={ applyFilters }>
				{ __( 'Filter', 'gatherpress' ) }
			</Button>
		</div>
	);
}
