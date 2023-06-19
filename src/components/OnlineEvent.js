/**
 * WordPress dependencies.
 */
import { __ } from '@wordpress/i18n';
import { Flex, FlexItem, Icon } from '@wordpress/components';
import { useState } from '@wordpress/element';

/**
 * Internal dependencies.
 */
import { Listener } from '../helpers/broadcasting';

const OnlineEvent = ({ eventId, onlineEventLinkDefault = '' }) => {
	const text = __('Online event', 'gatherpress');
	const [onlineEventLink, setOnlineEventLink] = useState(
		onlineEventLinkDefault
	);

	Listener({ setOnlineEventLink }, eventId);

	return (
		<Flex justify="normal">
			<FlexItem display="flex">
				<Icon icon="video-alt2" />
			</FlexItem>
			<FlexItem>
				{!onlineEventLink && <span>{text}</span>}
				{onlineEventLink && (
					<a href={onlineEventLink} rel="noreferrer" target="_blank">
						{text}
					</a>
				)}
			</FlexItem>
		</Flex>
	);
};

export default OnlineEvent;
