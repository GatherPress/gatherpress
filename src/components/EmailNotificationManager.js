/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { useSelect, useDispatch } from '@wordpress/data';
import { useEffect, useRef } from '@wordpress/element';

/**
 * Internal dependencies
 */
import { hasEventPast, isRsvpPostType } from '../helpers/event';
import { CHANGE_NOTICE_ID, getChangeNoticeText } from '../helpers/event-change';

/**
 * Email Notification Manager Component.
 *
 * After a save of a published event that has not started, offers to tell
 * the attendees when the save changed the date, the time, the time zone,
 * the venue, or the status. The server detects the change and sends it in
 * the save response as `gatherpress_change`. After any other save, no
 * notice shows; the event settings panel can still send a message.
 *
 * The notice is a standard notice, not a snackbar, so it stays until the
 * organizer closes it and keyboard and screen reader users can reach its
 * button.
 *
 * @since 0.33.0
 * @since TBD Shows only after a save that changed something attendees need to know.
 *
 * @return {null} This component doesn't render anything visible.
 */
const EmailNotificationManager = () => {
	const wasSaving = useRef( false );
	const noticeExists = useRef( false );
	const { createNotice, removeNotice } = useDispatch( 'core/notices' );
	const { openModal } = useDispatch( 'gatherpress/email-modal' );

	const { isSaving, isDirty, shouldShowNotice, change } = useSelect(
		( select ) => {
			const editorSelect = select( 'core/editor' );
			const status = editorSelect.getEditedPostAttribute( 'status' );
			const saving = editorSelect.isSavingPost();
			const autosaving = editorSelect.isAutosavingPost();

			return {
				isSaving: saving && ! autosaving,
				isDirty: editorSelect.isEditedPostDirty(),
				// Email-update notice targets RSVP attendees, so only surface it on
				// post types that actually carry `gatherpress-rsvp` support. Event-
				// date-only post types have no attendee list to email.
				shouldShowNotice:
					'publish' === status && isRsvpPostType() && ! hasEventPast(),
				// What the last save changed, from the saved record, not the edits.
				change:
					editorSelect.getCurrentPostAttribute(
						'gatherpress_change',
					) ?? null,
			};
		},
		[],
	);

	useEffect( () => {
		const justFinishedSaving = wasSaving.current && ! isSaving && ! isDirty;

		if ( justFinishedSaving && shouldShowNotice && change?.show_notice ) {
			createNotice( 'info', getChangeNoticeText( change ), {
				id: CHANGE_NOTICE_ID,
				isDismissible: true,
				actions: [
					{
						onClick: () => openModal( change ),
						label: __( 'Compose Message', 'gatherpress' ),
					},
				],
			} );

			noticeExists.current = true;
		} else if (
			noticeExists.current &&
			( isDirty || isSaving || ! shouldShowNotice )
		) {
			// Remove the notice when editing again, when the next save starts,
			// or when it no longer applies. A save that changed nothing to tell
			// leaves nothing in its place.
			removeNotice( CHANGE_NOTICE_ID );
			noticeExists.current = false;
		}

		wasSaving.current = isSaving;
	}, [
		isSaving,
		isDirty,
		shouldShowNotice,
		change,
		createNotice,
		openModal,
		removeNotice,
	] );

	// This component doesn't render anything.
	return null;
};

export default EmailNotificationManager;
