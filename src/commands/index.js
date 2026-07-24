/**
 * WordPress dependencies
 */
import { useCommand } from '@wordpress/commands';
import { Button, Modal } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { calendar, mapMarker } from '@wordpress/icons';
import { registerPlugin } from '@wordpress/plugins';

/**
 * Internal dependencies
 */
import VenueNavigator from '../components/VenueNavigator';

/**
 * Modal wrapper hosting the venue navigator.
 *
 * Declared at module scope rather than inside the plugin component so it keeps
 * a stable identity across renders. A component defined inline would be a new
 * type on every render, remounting the navigator and discarding whatever the
 * user had already typed into it.
 *
 * @since 0.35.0
 *
 * @param {Object}   props                Component properties.
 * @param {Function} props.onRequestClose Invoked when the modal should close.
 *
 * @return {JSX.Element} The rendered modal.
 */
const AddVenueModal = ( { onRequestClose } ) => (
	<Modal
		title={ __( 'Add new venue', 'gatherpress' ) }
		onRequestClose={ onRequestClose }
		shouldCloseOnClickOutside
		shouldCloseOnEsc
	>
		<VenueNavigator />
		<Button variant="secondary" onClick={ onRequestClose }>
			{ __( 'Cancel', 'gatherpress' ) }
		</Button>
	</Modal>
);

/**
 * Register GatherPress entries in the editor's command palette.
 *
 * "Add new venue" opens the venue navigator in a modal so a venue can be
 * created without leaving the post being edited. It is scoped to the block
 * editor because the navigator resolves the venue post type from the post
 * currently open. "Add new event" has nothing to offer inline, so it closes
 * the palette and navigates to the new-event screen.
 *
 * @since 0.35.0
 *
 * @return {JSX.Element|null} The venue modal while open, otherwise nothing.
 */
const GatherPressCommands = () => {
	const [ isVenueModalOpen, setIsVenueModalOpen ] = useState( false );

	useCommand( {
		name: 'gatherpress/add-new-venue',
		label: __( 'Add new venue', 'gatherpress' ),
		icon: mapMarker,
		context: 'block-editor',
		callback: ( { close } ) => {
			close();
			setIsVenueModalOpen( true );
		},
	} );

	useCommand( {
		name: 'gatherpress/add-new-event',
		label: __( 'Add new event', 'gatherpress' ),
		icon: calendar,
		callback: ( { close } ) => {
			close();
			window.location.assign(
				'post-new.php?post_type=gatherpress_event'
			);
		},
	} );

	if ( ! isVenueModalOpen ) {
		return null;
	}

	return (
		<AddVenueModal onRequestClose={ () => setIsVenueModalOpen( false ) } />
	);
};

registerPlugin( 'gatherpress-commands', {
	render: GatherPressCommands,
} );
