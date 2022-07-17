import {__} from '@wordpress/i18n';

const AttendeeResponse = ({ type, status }) => {
	const responses = {
		upcoming: {
			attend: {
				icon: '',
				text: '',
			},
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
				text: __('Not Attending', 'gatherpress'),
			},
		},
		past: {
			attending: {
				icon: 'dashicons dashicons-yes-alt',
				text: __('Went', 'gatherpress'),
			},
			attend: {
				icon: 'dashicons dashicons-dismiss',
				text: __("Didn't Go", 'gatherpress'),
			},
			waiting_list: {
				icon: 'dashicons dashicons-dismiss',
				text: __("Didn't Go", 'gatherpress'),
			},
			not_attending: {
				icon: 'dashicons dashicons-dismiss',
				text: __("Didn't Go", 'gatherpress'),
			},
		},
	};
console.log(type);
console.log(status);
	return (
		<div className="gp-status__response">
			<span className={responses[type][status].icon}></span>
			<strong>{responses[type][status].text}</strong>
		</div>
	);
};

export default AttendeeResponse;
