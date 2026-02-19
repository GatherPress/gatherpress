/**
 * WordPress dependencies
 */
import { useCommand } from '@wordpress/commands';
import { Modal, Button } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { mapMarker, calendar } from '@wordpress/icons';
import { registerPlugin } from '@wordpress/plugins';

/**
 * Internal dependencies
 */
import VenueNavigator from '../components/VenueNavigator';

const Render = () => {
	// For showing the new venue modal.
	const [ isVenueNavigatorOpen, setIsVenueNavigatorOpen ] = useState( false );

	// // Get the current post type
	// const postType = wp.data.select("core/editor").getCurrentPostType();

	// // Get the post type object
	// const postTypeObject = wp.data.select("core").getPostType(postType);

	const UserModal = ( props ) => {
		return (
			<>
				<Modal
					title={ __( 'Add new venue', 'gatherpress' ) }
					onRequestClose={ () => {
						props.onRequestClose();
					} }
					shouldCloseOnClickOutside={ true }
					shouldCloseOnEsc={ true }
				>
					<VenueNavigator />
					<Button
						variant="secondary"
						onClick={ () => {
							props.onRequestClose();
						} }
					>
						{ __( 'Cancel', 'gatherpress' ) }
					</Button>
				</Modal>
			</>
		);
	};

	useCommand( {
		name: 'gatherpress/add-new-venue',
		label: __( 'Add new venue', 'gatherpress' ),
		icon: mapMarker,
		callback: () => {
			setIsVenueNavigatorOpen( true );
		},
		context: 'block-editor',
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

	if ( isVenueNavigatorOpen ) {
		return (
			<UserModal
				onRequestClose={ () => {
					setIsVenueNavigatorOpen( false );
				} }
			/>
		);
	}

	return null; // The component doesn't need to render anything visually
};

registerPlugin( 'gatherpress-commands', {
	render: Render,
} );
