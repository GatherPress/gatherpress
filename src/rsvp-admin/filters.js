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
 * An empty value drops its parameter, which is how a filter is removed, and
 * paging resets because the current page rarely exists in the narrower result.
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
	const base = removeQueryArgs( href, 'post_id', 'response', 'paged' );
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
 * Both controls compose into one Filter button. Submitting navigates rather
 * than posting the surrounding bulk-actions form, which would leave the URL
 * unchanged and lose the filter on refresh and pagination.
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
