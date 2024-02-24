/**
 * WordPress dependencies.
 */
import { __ } from '@wordpress/i18n';
import { CheckboxControl } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { useDispatch, useSelect } from '@wordpress/data';

/**
 * Internal dependencies.
 */
import { getFromGlobal } from '../helpers/globals';

/**
 * InitialDecline component.
 *
 * This component renders a checkbox control that allows toggling the initial declining feature for an event.
 * It handles the state and updates the post's metadata accordingly. When creating a new event, the default
 * state of the checkbox is determined by a global setting. For existing events, it uses the event's current
 * setting. The component ensures that changes are reflected in the post's metadata and also unlocks post saving.
 *
 * @return {JSX.Element} A checkbox control for enabling or disabling initial declining.
 */
const InitialDecline = () => {
	const { editPost, unlockPostSaving } = useDispatch('core/editor');
	const isNewEvent = useSelect((select) => {
		return select('core/editor').isCleanNewPost();
	}, []);

	let defaultInitialDecline = useSelect((select) => {
		return select('core/editor').getEditedPostAttribute('meta')
			.enable_initial_decline;
	}, []);

	const [initialDecline, setInitialDecline] = useState(defaultInitialDecline);

	const updateInitialDecline = (value) => {
		const meta = { enable_initial_decline: Number(value) };

		setInitialDecline(value);
		editPost({ meta });
		unlockPostSaving();
	};

	if (isNewEvent) {
		defaultInitialDecline = getFromGlobal('settings.enableInitialDecline');
		updateInitialDecline(defaultInitialDecline);
	}

	return (
		<CheckboxControl
			label={__(
				'Allow attendees to select "not attending" immediately',
				'gatherpress'
			)}
			checked={initialDecline}
			onChange={(value) => {
				updateInitialDecline(value);
			}}
		/>
	);
};

export default InitialDecline;
