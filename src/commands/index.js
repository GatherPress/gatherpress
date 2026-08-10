/**
 * WordPress dependencies
 */
import { store as commandsStore } from '@wordpress/commands';
import { dispatch } from '@wordpress/data';
import { __, sprintf } from '@wordpress/i18n';

/**
 * Build the "Go to: Events > Add New Venue" command config.
 *
 * The venue post type is nested under the Events admin menu, so WordPress
 * core never gives it an "Add New" submenu entry, and the command palette —
 * which core generates by walking the admin menu — has no venue creation
 * command to match the "Go to: Events > Add New Event" one it derives from
 * the event submenu.
 *
 * This registers that missing command directly. It is deliberately a `view`
 * command so the palette renders it identically to core's menu-derived
 * entries: the palette's own `CATEGORY_ICONS.view` supplies the arrow icon
 * (any `icon` set here would be ignored for that category) and the "View"
 * badge, and the label mirrors core's `Go to: {parent} > {child}` shape.
 *
 * @since 0.35.0
 *
 * @return {Object} A command config accepted by the commands store.
 */
export function getAddVenueCommand() {
	const target = sprintf(
		/* translators: 1: parent admin menu label (Events), 2: submenu label (Add New Venue). */
		__( '%1$s > %2$s', 'gatherpress' ),
		__( 'Events', 'gatherpress' ),
		__( 'Add New Venue', 'gatherpress' )
	);
	/* translators: %s: admin navigation target, e.g. "Events > Add New Venue". */
	const label = sprintf( __( 'Go to: %s', 'gatherpress' ), target );

	return {
		name: 'gatherpress/add-new-venue',
		label,
		searchLabel: label,
		category: 'view',
		callback: ( { close } ) => {
			close();
			document.location.href = 'post-new.php?post_type=gatherpress_venue';
		},
	};
}

dispatch( commandsStore ).registerCommand( getAddVenueCommand() );
