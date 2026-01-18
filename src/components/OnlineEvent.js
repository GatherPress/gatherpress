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

/**
 * OnlineEvent component for GatherPress.
 *
 * This component is used to display information about online events, including an icon
 * and an optional link for attendees.
 *
 * @since 1.0.0
 *
 * @param {Object} props                             - Component properties.
 * @param {string} [props.onlineEventLinkDefault=''] - The default online event link.
 *
 * @return {JSX.Element} The rendered React component.
 */
const OnlineEvent = ( { onlineEventLinkDefault = '' } ) => {
	const text = __( 'Online event', 'gatherpress' );
	const [ onlineEventLink, setOnlineEventLink ] = useState(
		onlineEventLinkDefault,
	);

	Listener( { setOnlineEventLink }, getFromGlobal( 'eventDetails.postId' ) );

	return (
		<Flex justify="normal" gap="3">
			<FlexItem display="flex">
				<Icon icon="video-alt2" />
			</FlexItem>
			<FlexItem>
				{ ! onlineEventLink && (
					<>
						<span
							tabIndex="0"
							className="gatherpress-tooltip"
							data-tooltip-id="gatherpress-online-event-tooltip"
							data-tooltip-content={ __(
								'Link available for attendees only.',
								'gatherpress',
							) }
						>
							{ text }
						</span>
						<Tooltip id="gatherpress-online-event-tooltip" />
					</>
				) }
				{ onlineEventLink && (
					<a href={ onlineEventLink } rel="noreferrer" target="_blank">
						{ text }
					</a>
				) }
			</FlexItem>
		</Flex>
	);
};

export default OnlineEvent;
