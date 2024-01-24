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
	if (!getFromGlobal('settings.allow_anonymous_rsvp')) {
		return <></>;
	}

	const { editPost, unlockPostSaving } = useDispatch('core/editor');
	const defaultAnonymousRsvp = useSelect((select) => {
		return select('core/editor').getEditedPostAttribute('meta')
			.allow_anonymous_rsvp;
	}, []);

	const [anonymousRsvp, setAnonymousRsvp] = useState(defaultAnonymousRsvp);

	const updateAnonymousRsvp = (value) => {
		const meta = { allow_anonymous_rsvp: Number(value) };

		setAnonymousRsvp(value);
		editPost({ meta });
		unlockPostSaving();
	};

	return (
		<CheckboxControl
			label={__('Allow anonymous RSVPs', 'gatherpress')}
			checked={anonymousRsvp}
			onChange={(value) => {
				updateAnonymousRsvp(value);
			}}
		/>
	);
};

export default AnonymousRsvp;
