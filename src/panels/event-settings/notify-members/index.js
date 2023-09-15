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
