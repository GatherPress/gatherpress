/**
 * WordPress dependencies.
 */
import { __ } from '@wordpress/i18n';
import { Button } from '@wordpress/components';
import { useSelect, useDispatch } from '@wordpress/data';
import { useState, useEffect } from '@wordpress/element';

/**
 * Internal dependencies.
 */
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
	const [ showNotifyPanel, setShowNotifyPanel ] = useState( false );
	const { openModal } = useDispatch( 'gatherpress/email-modal' );
	const isEmailSaving = useSelect( ( select ) => select( 'gatherpress/email-modal' ).isSaving(), [] );

	const { currentStatus, isSaving, isDirty } = useSelect( ( select ) => {
		const editorSelect = select( 'core/editor' );

		return {
			currentStatus: editorSelect.getEditedPostAttribute( 'status' ),
			isSaving: editorSelect.isSavingPost(),
			isDirty: editorSelect.isEditedPostDirty(),
		};
	}, [] );

	useEffect( () => {
		const isPostPublished = 'publish' === currentStatus && ! hasEventPast();

		setShowNotifyPanel( isPostPublished );
	}, [ currentStatus ] );

	return (
		showNotifyPanel && (
			<section>
				<h3 style={ { marginBottom: '0.5rem' } }>
					{ __( 'Send an event update via email', 'gatherpress' ) }
				</h3>
				<Button
					variant="secondary"
					onClick={ openModal }
					disabled={ isEmailSaving || isSaving || isDirty }
				>
					{ __( 'Compose Message', 'gatherpress' ) }
				</Button>
			</section>
		)
	);
};

export default NotifyMembersPanel;
