/**
 * Internal dependencies.
 */
import RsvpResponseCard from './RsvpResponseCard';
import { getFromGlobal } from '../helpers/globals';
import { useState } from '@wordpress/element';
import { Listener } from '../helpers/broadcasting';

/**
 * RsvpResponseContent component for GatherPress.
 *
 * This component displays the content of RSVP responses based on the selected RSVP status.
 * It receives an array of items representing different RSVP statuses and renders the content
 * of the active status using the RsvpResponseCard component. The component dynamically updates
 * based on changes to the RSVP responses.
 *
 * @since 1.0.0
 *
 * @param {Object}         props               - Component props.
 * @param {Array}          props.items         - An array of objects representing different RSVP statuses.
 * @param {string}         props.activeValue   - The currently active RSVP status value.
 * @param {number|boolean} [props.limit=false] - The maximum number of responses to display or false for no limit.
 *
 * @return {JSX.Element} The rendered React component.
 */
const RsvpResponseContent = ( { items, activeValue, limit = false } ) => {
	const postId = getFromGlobal( 'eventDetails.postId' );
	const [ rsvpResponse, setRsvpResponse ] = useState(
		getFromGlobal( 'eventDetails.responses' ),
	);

	Listener( { setRsvpResponse }, postId );

	const renderedItems = items.map( ( item, index ) => {
		const { value } = item;
		const active = value === activeValue;

		if ( active ) {
			return (
				<div
					key={ index }
					className="gatherpress-rsvp-response__items"
					id={ `gatherpress-rsvp-${ value }` }
					role="tabpanel"
					aria-labelledby={ `gatherpress-rsvp-${ value }-tab` }
				>
					<RsvpResponseCard
						value={ value }
						limit={ limit }
						responses={ rsvpResponse }
					/>
				</div>
			);
		}

		return '';
	} );

	return (
		<div className="gatherpress-rsvp-response__content">
			{ renderedItems }
		</div>
	);
};

export default RsvpResponseContent;
