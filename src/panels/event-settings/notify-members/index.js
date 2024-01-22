/**
 * WordPress dependencies.
 */
import { __ } from '@wordpress/i18n';
import { Button } from '@wordpress/components';
import { select } from '@wordpress/data';

/**
 * Internal dependencies.
 */
import { Broadcaster } from '../../../helpers/broadcasting';
import { hasEventPast } from '../../../helpers/event';

/**
 * A panel component for notifying members about an event update.
 *
 * This component checks if the current post is published and the event has not yet occurred.
 * If the conditions are met, it displays a section with a button to compose a message for members.
 *
 * @since 1.0.0
 *
 * @return {JSX.Element | null} The JSX element for the NotifyMembersPanel or null if conditions are not met.
 */
const NotifyMembersPanel = () => {
	return (
		'publish' === select('core/editor').getEditedPostAttribute('status') &&
		!hasEventPast() && (
			<section>
				<h3 style={{ marginBottom: '0.5rem' }}>
					{__('Send an event update', 'gatherpress')}
				</h3>
				<Button
					variant="secondary"
					onClick={() => Broadcaster({ setOpen: true })}
				>
					{__('Compose Message', 'gatherpress')}
				</Button>
			</section>
		)
	);
};

export default NotifyMembersPanel;
