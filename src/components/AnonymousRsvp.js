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
 * AnonymousRsvp component.
 *
 * This component renders a checkbox control that allows toggling the anonymous RSVP feature for an event.
 * It handles the state and updates the post's metadata accordingly. When creating a new event, the default
 * state of the checkbox is determined by a global setting. For existing events, it uses the event's current
 * setting. The component ensures that changes are reflected in the post's metadata and also unlocks post saving.
 *
 * @return {JSX.Element} A checkbox control for enabling or disabling anonymous RSVPs.
 */
const AnonymousRsvp = () => {
	const { editPost, unlockPostSaving } = useDispatch('core/editor');
	const isNewEvent = useSelect((select) => {
		return select('core/editor').isCleanNewPost();
	}, []);

	let defaultAnonymousRsvp = useSelect((select) => {
		return select('core/editor').getEditedPostAttribute('meta')
			.enable_anonymous_rsvp;
	}, []);

	if (isNewEvent) {
		defaultAnonymousRsvp = getFromGlobal('settings.enableAnonymousRsvp');
	}

	const [anonymousRsvp, setAnonymousRsvp] = useState(defaultAnonymousRsvp);

	const updateAnonymousRsvp = (value) => {
		const meta = { enable_anonymous_rsvp: Number(value) };

		setAnonymousRsvp(value);
		editPost({ meta });
		unlockPostSaving();
	};

	return (
		<CheckboxControl
			label={__('Enable Anonymous RSVP', 'gatherpress')}
			checked={anonymousRsvp}
			onChange={(value) => {
				updateAnonymousRsvp(value);
			}}
		/>
	);
};

export default AnonymousRsvp;
