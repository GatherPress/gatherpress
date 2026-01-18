/**
 * WordPress dependencies.
 */
import { useState } from '@wordpress/element';
import { _x } from '@wordpress/i18n';

/**
 * Internal dependencies.
 */
import RsvpResponseHeader from './RsvpResponseHeader';
import RsvpResponseContent from './RsvpResponseContent';
import { Listener } from '../helpers/broadcasting';
import { getFromGlobal } from '../helpers/globals';

/**
 * Component for displaying and managing RSVP responses.
 *
 * This component renders a user interface for managing RSVP responses to an event.
 * It includes options for attending, being on the waiting list, or not attending,
 * and updates the status based on user interactions. The component also listens for
 * changes in RSVP status and updates the state accordingly.
 *
 * @param {Object} root0               The destructured props object.
 * @param {string} root0.defaultStatus The current default status for the RSVP response, defaults to 'attending'.
 * @since 1.0.0
 *
 * @deprecated Component will be removed soon.
 *
 * @return {JSX.Element} The rendered RSVP response component.
 */
const RsvpResponse = ( { defaultStatus = 'attending' } ) => {
	const defaultLimit = 8;
	const hasEventPast = getFromGlobal( 'eventDetails.hasEventPast' );
	const items = [
		{
			title:
				false === hasEventPast
					? _x(
						'Attending',
						'RSVP status option for upcoming events',
						'gatherpress',
					)
					: _x(
						'Went',
						'RSVP status option for past events',
						'gatherpress',
					),
			value: 'attending',
		},
		{
			title:
				false === hasEventPast
					? _x(
						'Waiting List',
						'RSVP status option for upcoming events',
						'gatherpress',
					)
					: _x(
						'Wait Listed',
						'RSVP status option for past events',
						'gatherpress',
					),
			value: 'waiting_list',
		},
		{
			title:
				false === hasEventPast
					? _x(
						'Not Attending',
						'RSVP status option for upcoming events',
						'gatherpress',
					)
					: _x(
						"Didn't Go",
						'RSVP status option for past events',
						'gatherpress',
					),
			value: 'not_attending',
		},
	];

	const [ rsvpStatus, setRsvpStatus ] = useState( defaultStatus );
	const [ rsvpLimit, setRsvpLimit ] = useState( defaultLimit );

	const onTitleClick = ( e, value ) => {
		e.preventDefault();
		setRsvpStatus( value );
	};

	Listener( { setRsvpStatus }, getFromGlobal( 'eventDetails.postId' ) );

	// Make sure rsvpStatus is a valid status, if not, set to default.
	if ( ! items.some( ( item ) => item.value === rsvpStatus ) ) {
		setRsvpStatus( defaultStatus );
	}

	return (
		<div className="gatherpress-rsvp-response">
			<RsvpResponseHeader
				items={ items }
				activeValue={ rsvpStatus }
				onTitleClick={ onTitleClick }
				rsvpLimit={ rsvpLimit }
				setRsvpLimit={ setRsvpLimit }
				defaultLimit={ defaultLimit }
			/>
			<RsvpResponseContent
				items={ items }
				activeValue={ rsvpStatus }
				limit={ rsvpLimit }
			/>
		</div>
	);
};

export default RsvpResponse;
