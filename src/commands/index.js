/**
 * WordPress dependencies
 */
import {
	useCommand,
} from '@wordpress/commands';
import { mapMarker, calendar } from '@wordpress/icons';
import { __ } from '@wordpress/i18n';
import { registerPlugin } from '@wordpress/plugins';

const Render = () => {
	useCommand( {
		name: 'gatherpress/add-new-venue',
		label: __( 'Add new venue', 'gatherpress' ),
		icon: mapMarker,
		callback: ( { close } ) => {
			close();
			document.location.href = 'post-new.php?post_type=gatherpress_venue';
		},
	} );

	useCommand( {
		name: 'gatherpress/add-new-event',
		label: __( 'Add new event', 'gatherpress' ),
		icon: calendar,
		callback: ( { close } ) => {
			close();
			document.location.href = 'post-new.php?post_type=gatherpress_event';
		},
	} );

	return null; // The component doesn't need to render anything visually
};

registerPlugin( 'gatherpress-commands', {
	render: Render,
} );
