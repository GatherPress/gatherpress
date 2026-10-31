/**
 * WordPress dependencies
 */
import { Button } from '@wordpress/components';
import { useInstanceId } from '@wordpress/compose';
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
 * A row action leaves its action, RSVP and nonce in the URL; they are dropped
 * so filtering does not run that action again.
 *
 * @since 0.36.0
 *
 * @param {string}      href      The current URL.
 * @param {number|null} postId    Selected event ID, if any.
 * @param {string[]}    responses Selected response statuses.
 * @param {string}      checkIn   Selected check-in state, or an empty string for all.
 *
 * @return {string} The URL to navigate to.
 */
export function buildFilterUrl( href, postId, responses, checkIn = '' ) {
	const base = removeQueryArgs(
		href,
		'post_id',
		'response',
		'check_in',
		'paged',
		'action',
		'action2',
		'gatherpress_rsvp_id',
		'_wpnonce',
	);
	const args = {};

	if ( postId ) {
		args.post_id = postId;
	}

	if ( responses.length ) {
		args.response = responses.join( ',' );
	}

	if ( checkIn ) {
		args.check_in = checkIn;
	}

	return Object.keys( args ).length ? addQueryArgs( base, args ) : base;
}

/**
 * The RSVP screen's filters.
 *
 * The controls compose into one Filter button. Submitting navigates rather
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
 * @param {Object[]} props.checkInStates    Selectable check-in states, each `{ value, label }`.
 * @param {string}   props.initialCheckIn   Check-in state from the current request.
 *
 * @return {JSX.Element} The filter controls.
 */
export default function Filters( {
	postTypes,
	initialPostId,
	eventLabel,
	statuses,
	initialResponses,
	checkInStates = [],
	initialCheckIn = '',
} ) {
	const [ postId, setPostId ] = useState( initialPostId );
	const [ responses, setResponses ] = useState( initialResponses );
	const [ checkIn, setCheckIn ] = useState( initialCheckIn );
	const checkInId = useInstanceId( Filters, 'gatherpress-rsvp-check-in' );

	/**
	 * Reload the screen with both filters applied.
	 *
	 * @return {void}
	 */
	const applyFilters = () => {
		window.location.href = buildFilterUrl(
			window.location.href,
			postId,
			responses,
			checkIn,
		);
	};

	return (
		<>
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

			<div className="gatherpress-rsvp-filters__check-in">
				<label className="screen-reader-text" htmlFor={ checkInId }>
					{ __( 'Filter by check-in', 'gatherpress' ) }
				</label>
				<select
					id={ checkInId }
					value={ checkIn }
					onChange={ ( event ) => setCheckIn( event.target.value ) }
				>
					<option value="">
						{ __( 'All check-ins', 'gatherpress' ) }
					</option>
					{ checkInStates.map( ( state ) => (
						<option key={ state.value } value={ state.value }>
							{ state.label }
						</option>
					) ) }
				</select>
			</div>

			<Button variant="secondary" onClick={ applyFilters }>
				{ __( 'Filter', 'gatherpress' ) }
			</Button>
		</>
	);
}
