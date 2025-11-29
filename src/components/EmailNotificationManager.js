/**
 * WordPress dependencies.
 */
import { __ } from '@wordpress/i18n';
import { useSelect, useDispatch } from '@wordpress/data';
import { useEffect, useRef } from '@wordpress/element';

/**
 * Internal dependencies.
 */
import { isEventPostType, hasEventPast } from '../helpers/event';

/**
 * Email Notification Manager Component.
 *
 * Manages the "Send an event update to members via email?" notification
 * that appears after saving a published event. Uses proper WordPress
 * data hooks instead of custom subscription patterns.
 *
 * @since 1.0.0
 *
 * @return {null} This component doesn't render anything visible.
 */
const EmailNotificationManager = () => {
	const wasSaving = useRef( false );
	const wasDirty = useRef( false );
	const noticeExists = useRef( false );
	const { createNotice, removeNotice } = useDispatch( 'core/notices' );
	const { openModal } = useDispatch( 'gatherpress/email-modal' );

	const {
		isSaving,
		isDirty,
		shouldShowNotice,
	} = useSelect( ( select ) => {
		const editorSelect = select( 'core/editor' );
		const status = editorSelect.getEditedPostAttribute( 'status' );
		const saving = editorSelect.isSavingPost();
		const dirty = editorSelect.isEditedPostDirty();
		const autosaving = editorSelect.isAutosavingPost();

		return {
			isSaving: saving && ! autosaving,
			isDirty: dirty,
			shouldShowNotice: 'publish' === status && isEventPostType() && ! hasEventPast(),
		};
	}, [] );

	useEffect( () => {
		const justFinishedSaving = wasSaving.current && ! isSaving && ! isDirty;

		// Show notice only after save completion.
		if ( shouldShowNotice && justFinishedSaving ) {
			createNotice(
				'success',
				__( 'Send an event update via email', 'gatherpress' ),
				{
					id: 'gatherpress_event_communication',
					isDismissible: true,
					actions: [
						{
							onClick: openModal,
							label: __( 'Compose Message', 'gatherpress' ),
						},
					],
				}
			);

			noticeExists.current = true;
		} else if ( ( isDirty && noticeExists.current ) || ( isSaving && noticeExists.current ) || ( ! shouldShowNotice && noticeExists.current ) ) {
			// Remove notice when making changes, during saves, or when no longer needed.
			removeNotice( 'gatherpress_event_communication' );
			noticeExists.current = false;
		}

		wasSaving.current = isSaving;
		wasDirty.current = isDirty;
	}, [ isSaving, isDirty, shouldShowNotice, createNotice, openModal, removeNotice ] );

	// This component doesn't render anything.
	return null;
};

export default EmailNotificationManager;
