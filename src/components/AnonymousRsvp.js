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
		defaultAnonymousRsvp = getFromGlobal('settings.enable_anonymous_rsvp');
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
			label={__('Enable anonymous RSVPs', 'gatherpress')}
			checked={anonymousRsvp}
			onChange={(value) => {
				updateAnonymousRsvp(value);
			}}
		/>
	);
};

export default AnonymousRsvp;
