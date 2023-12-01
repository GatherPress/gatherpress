/**
 * External dependencies.
 */
import { Tooltip } from 'react-tooltip';
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
import { getFromGlobal } from '../helpers/globals';

const OnlineEvent = ({ onlineEventLinkDefault = '' }) => {
	const text = __('Online event', 'gatherpress');
	const [onlineEventLink, setOnlineEventLink] = useState(
		onlineEventLinkDefault
	);

	Listener({ setOnlineEventLink }, getFromGlobal('post_id'));

	return (
		<Flex justify="normal" gap="3">
			<FlexItem display="flex">
				<Icon icon="video-alt2" />
			</FlexItem>
			<FlexItem>
				{!onlineEventLink && (
					<>
						<span
							tabindex="0"
							className="gp-tooltip"
							data-tooltip-id="gp-online-event"
							data-tooltip-content={__(
								'Link active for attendees during event.',
								'gatherpress'
							)}
						>
							{text}
						</span>
						<Tooltip id="gp-online-event" />
					</>
				)}
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
