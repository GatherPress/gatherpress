/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { store as commandsStore } from '@wordpress/commands';
import { dispatch } from '@wordpress/data';
import { mapMarker, calendar } from '@wordpress/icons';

dispatch( commandsStore ).registerCommand( {
	name: 'gatherpress/add-new-venue',
	label: __( 'Add new venue', 'gatherpress' ),
	icon: mapMarker,
	callback: () => {
		document.location.href = 'post-new.php?post_type=gatherpress_venue';
	},
} );

dispatch( commandsStore ).registerCommand( {
	name: 'gatherpress/add-new-event',
	label: __( 'Add new event', 'gatherpress' ),
	icon: calendar,
	callback: () => {
		document.location.href = 'post-new.php?post_type=gatherpress_event';
	},
} );
