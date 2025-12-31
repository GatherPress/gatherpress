/**
 * WordPress dependencies.
 */
import { __, _n, sprintf } from '@wordpress/i18n';

/**
 * Internal dependencies.
 */
import { getFromGlobal } from '../helpers/globals';

/**
 * RsvpResponseCard component for GatherPress.
 *
 * This component displays detailed information about attendees who have responded to an event's RSVP.
 * It receives information about the RSVP responses, including the attendee's profile link, name, photo, role,
 * and the number of guests. The component renders each attendee's information in a structured layout.
 * It also provides a message when no attendees are found for the specified RSVP status.
 * The component dynamically updates based on changes to the RSVP responses.
 *
 * @since 1.0.0
 *
 * @param {Object} props                - Component props.
 * @param {string} props.value          - The RSVP status value ('attending', 'not_attending', etc.).
 * @param {number} props.limit          - The maximum number of responses to display.
 * @param {Array}  [props.responses=[]] - An array of RSVP responses for the specified status.
 *
 * @return {JSX.Element} The rendered React component.
 */
const RsvpResponseCard = ( { value, limit, responses = [] } ) => {
	let renderedItems = '';

	if (
		'object' === typeof responses &&
		'undefined' !== typeof responses[ value ]
	) {
		responses = [ ...responses[ value ].records ];

		if ( limit ) {
			responses = responses.splice( 0, limit );
		}

		renderedItems = responses.map( ( response, index ) => {
			const { profile, name, photo, role } = response;
			const { guests } = response;

			return (
				<div key={ index } className="gatherpress-rsvp-response__item">
					<figure className="gatherpress-rsvp-response__member-avatar">
						{ '' === profile ? (
							<img alt={ name } title={ name } src={ photo } />
						) : (
							<a href={ profile }>
								<img alt={ name } title={ name } src={ photo } />
							</a>
						) }
					</figure>
					<div className="gatherpress-rsvp-response__member-info">
						<div className="gatherpress-rsvp-response__member-name">
							{ '' === profile ? (
								<span>{ name }</span>
							) : (
								<a href={ profile } title={ name }>
									{ name }
								</a>
							) }
						</div>
						<div className="gatherpress-rsvp-response__member-role">
							{ role }
						</div>
						{ 0 < guests && (
							<small className="gatherpress-rsvp-response__guests">
								{ sprintf(
									/* translators: %d: Number of guests. */
									_n(
										'+%d guest',
										'+%d guests',
										guests,
										'gatherpress',
									),
									guests,
								) }
							</small>
						) }
					</div>
				</div>
			);
		} );
	}
	return (
		<>
			{ 'attending' === value && 0 === renderedItems.length && (
				<div className="gatherpress-rsvp-response__no-responses">
					{ true === getFromGlobal( 'eventDetails.hasEventPast' )
						? __( 'No one went to this event.', 'gatherpress' )
						: __(
							'No one is attending this event yet.',
							'gatherpress',
						) }
				</div>
			) }
			{ renderedItems }
		</>
	);
};

export default RsvpResponseCard;
