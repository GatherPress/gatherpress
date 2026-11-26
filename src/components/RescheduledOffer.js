/**
 * External dependencies
 */
import moment from 'moment';

/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { Notice } from '@wordpress/components';
import { useDispatch, useSelect } from '@wordpress/data';

/**
 * Internal dependencies
 */
import {
	createMomentWithTimezone,
	dateTimeDatabaseFormat,
} from '../helpers/datetime';
import { EVENT_STATUS_ID } from './EventStatus';

/**
 * Offer to mark a published event as Rescheduled when its start moves.
 *
 * A date change does not set the status by itself, because a small fix to
 * the date would mark the event Rescheduled for good. The offer shows before
 * the save, so the date and the status change in the same save and the
 * message to attendees names both.
 *
 * @since TBD
 *
 * @return {JSX.Element|null} The offer, or null when it does not apply.
 */
const RescheduledOffer = () => {
	const { editPost } = useDispatch( 'core/editor' );
	const show = useSelect( ( select ) => {
		const editor = select( 'core/editor' );

		// The saved start comes from the read-only meta the server derives
		// for every event, also one created without the editor. The edited
		// start is in the date store, which the date controls write to.
		const meta = editor.getCurrentPostAttribute( 'meta' ) || {};
		const savedStart = meta.gatherpress_datetime_start || '';
		const savedTimezone = meta.gatherpress_timezone || '';
		const status =
			editor.getEditedPostAttribute( 'gatherpress_status' ) || 'scheduled';

		// Only a published event that has not started, whose start moved, and
		// that nobody has given another status yet.
		if (
			'publish' !== editor.getCurrentPostAttribute( 'status' ) ||
			'scheduled' !== status ||
			! savedStart
		) {
			return false;
		}

		const datetime = select( 'gatherpress/datetime' );
		const moved =
			savedTimezone !== datetime.getTimezone() ||
			moment( savedStart ).format( dateTimeDatabaseFormat ) !==
				moment( datetime.getDateTimeStart() ).format(
					dateTimeDatabaseFormat,
				);

		return (
			moved &&
			createMomentWithTimezone( savedStart, savedTimezone ).valueOf() >
				moment().valueOf()
		);
	}, [] );

	if ( ! show ) {
		return null;
	}

	const markRescheduled = () => {
		editPost( { gatherpress_status: 'rescheduled' } );

		// The offer goes away once the status changes, so keep focus on the
		// select that now says Rescheduled.
		document.getElementById( EVENT_STATUS_ID )?.focus();
	};

	return (
		<Notice
			status="info"
			isDismissible={ false }
			actions={ [
				{
					label: __( 'Mark as Rescheduled', 'gatherpress' ),
					onClick: markRescheduled,
					variant: 'secondary',
				},
			] }
		>
			{ __(
				'The start changed. Do you want to set the event status to Rescheduled?',
				'gatherpress',
			) }
		</Notice>
	);
};

export default RescheduledOffer;
