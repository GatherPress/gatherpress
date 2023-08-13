/**
 * WordPress dependencies.
 */
import { useState } from '@wordpress/element';
import { TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useDispatch, useSelect } from '@wordpress/data';

/**
 * Internal dependencies.
 */
import { Broadcaster } from '../helpers/broadcasting';

const OnlineEventLink = () => {
	const editPost = useDispatch('core/editor').editPost;
	const { unlockPostSaving } = useDispatch('core/editor');
	const onlineEventLinkMetaData = useSelect(
		(select) =>
			select('core/editor').getEditedPostAttribute('meta')
				._online_event_link
	);
	const [onlineEventLink, setOnlineEventLink] = useState(
		onlineEventLinkMetaData
	);
	const updateEventLink = (value) => {
		const meta = { _online_event_link: value };

		editPost({ meta });
		setOnlineEventLink(value);
		Broadcaster({
			setOnlineEventLink: value,
		});
		unlockPostSaving();
	};

	return (
		<TextControl
			label={__('Online event link', 'gatherpress')}
			value={onlineEventLink}
			placeholder={__('Add link to online event', 'gatherpress')}
			onChange={(value) => {
				updateEventLink(value);
			}}
		/>
	);
};

export default OnlineEventLink;
