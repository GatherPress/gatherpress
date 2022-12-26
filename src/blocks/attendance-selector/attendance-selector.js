/**
 * WordPress dependencies.
 */
import { render } from '@wordpress/element';

/**
 * Internal dependencies.
 */
import AttendanceSelector from '../../components/AttendanceSelector';

document.addEventListener('DOMContentLoaded', () => {
	const containers = document.querySelectorAll(
		`[data-gp_block_name="attendance-selector"]`
	);

	// eslint-disable-next-line no-undef
	const type = true === GatherPress.has_event_past ? 'past' : 'upcoming';

	for (let i = 0; i < containers.length; i++) {
		render(
			<AttendanceSelector
				// eslint-disable-next-line no-undef
				eventId={GatherPress.post_id}
				// eslint-disable-next-line no-undef
				currentUser={GatherPress.current_user}
				type={type}
			/>,
			containers[i]
		);
	}
});
