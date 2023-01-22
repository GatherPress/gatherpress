/**
 * WordPress dependencies.
 */
import domReady from '@wordpress/dom-ready';
import { render } from '@wordpress/element';

/**
 * Internal dependencies.
 */
import AttendanceSelector from '../../components/AttendanceSelector';
import { getFromGlobal } from '../../helpers/misc';

domReady(() => {
	const containers = document.querySelectorAll(
		`[data-gp_block_name="attendance-selector"]`
	);

	const type = true === getFromGlobal('has_event_past') ? 'past' : 'upcoming';

	for (let i = 0; i < containers.length; i++) {
		render(
			<AttendanceSelector
				eventId={getFromGlobal('post_id')}
				currentUser={getFromGlobal('current_user')}
				type={type}
			/>,
			containers[i]
		);
	}
});
