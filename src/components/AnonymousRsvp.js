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
	let defaultAnonymousRsvp = useSelect(
		(select) =>
			select('core/editor').getEditedPostAttribute('meta')
				.anonymous_rsvp
	);
	console.log(defaultAnonymousRsvp);

	if ('undefined' === typeof defaultAnonymousRsvp) {
		defaultAnonymousRsvp = getFromGlobal('settings.anonymous_rsvp');
	}

	const [anonymousRsvp, setAnonymousRsvp] = useState(defaultAnonymousRsvp);

	const updateAnonymousRsvp = (value) => {
		const meta = { anonymous_rsvp: value };

		setAnonymousRsvp(value);
		editPost({ meta });
		unlockPostSaving();
	};

	return(
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
