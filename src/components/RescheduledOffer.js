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
import { createMomentWithTimezone } from '../helpers/datetime';
import { EVENT_STATUS_ID } from './EventStatus';

/**
 * Read the start and the time zone from a `gatherpress_datetime` meta value.
 *
 * @since TBD
 *
 * @param {string|undefined} value The JSON meta value.
 *
 * @return {Object} The `dateTimeStart` and `timezone`, empty when the value cannot be read.
 */
export const parseStart = ( value ) => {
	try {
		const { dateTimeStart = '', timezone = '' } = JSON.parse( value || '{}' );

		return { dateTimeStart, timezone };
	} catch {
		return { dateTimeStart: '', timezone: '' };
	}
};

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
		const saved = parseStart(
			editor.getCurrentPostAttribute( 'meta' )?.gatherpress_datetime,
		);
		const edited = parseStart(
			editor.getEditedPostAttribute( 'meta' )?.gatherpress_datetime,
		);
		const status =
			editor.getEditedPostAttribute( 'gatherpress_status' ) || 'scheduled';

		// Only a published event that has not started, whose start moved, and
		// that nobody has given another status yet.
		if (
			'publish' !== editor.getCurrentPostAttribute( 'status' ) ||
			'scheduled' !== status ||
			! saved.dateTimeStart ||
			( saved.dateTimeStart === edited.dateTimeStart &&
				saved.timezone === edited.timezone )
		) {
			return false;
		}

		const start = createMomentWithTimezone(
			saved.dateTimeStart,
			saved.timezone,
		);

		return start.valueOf() > moment().valueOf();
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
