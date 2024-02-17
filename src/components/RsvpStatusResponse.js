/**
 * WordPress dependencies.
 */
import { __, _x } from '@wordpress/i18n';

/**
 * RsvpStatusResponse component for GatherPress.
 *
 * This component displays the RSVP status response based on the event type (upcoming or past)
 * and the provided status. It includes an icon and text representing the corresponding RSVP status.
 * The component is typically used within the `Rsvp` component to show the user's RSVP status.
 *
 * @since 1.0.0
 *
 * @param {Object} props                      - Component props.
 * @param {string} [props.type='upcoming']    - The type of the event, either 'upcoming' or 'past'.
 * @param {string} [props.status='no_status'] - The RSVP status, such as 'attending', 'waiting_list', 'not_attending', 'no_status'.
 *
 * @return {JSX.Element} The rendered React component.
 */
const RsvpStatusResponse = ({ type = 'upcoming', status = 'no_status' }) => {
	const responses = {
		upcoming: {
			attending: {
				icon: 'dashicons dashicons-yes-alt',
				text: __('Attending', 'gatherpress'),
			},
			waiting_list: {
				icon: 'dashicons dashicons-editor-help',
				text: __('Waiting List', 'gatherpress'),
			},
			not_attending: {
				icon: 'dashicons dashicons-dismiss',
				text: _x('Not Attending', 'responded not attending', 'gatherpress'),
			},
			no_status: {
				icon: '',
				text: '',
			},
		},
		past: {
			attending: {
				icon: 'dashicons dashicons-yes-alt',
				text: __('Went', 'gatherpress'),
			},
			waiting_list: {
				icon: 'dashicons dashicons-dismiss',
				text: __("Didn't Go", 'gatherpress'),
			},
			not_attending: {
				icon: 'dashicons dashicons-dismiss',
				text: __("Didn't Go", 'gatherpress'),
			},
			no_status: {
				icon: 'dashicons dashicons-dismiss',
				text: __("Didn't Go", 'gatherpress'),
			},
		},
	};

	return (
		<div className="gp-status__response">
			<span className={responses[type][status].icon}></span>
			<strong>{responses[type][status].text}</strong>
		</div>
	);
};

export default RsvpStatusResponse;
