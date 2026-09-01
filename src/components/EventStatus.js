/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { SelectControl } from '@wordpress/components';
import { useCallback } from '@wordpress/element';
import { useDispatch, useSelect } from '@wordpress/data';

const STATUS_DESCRIPTIONS = {
	scheduled: __(
		'Event is planned and confirmed to take place.',
		'gatherpress'
	),
	cancelled: __(
		'Event will not take place. Calendar feeds will mark it as cancelled.',
		'gatherpress'
	),
	postponed: __(
		'Event is delayed to a future unconfirmed date.',
		'gatherpress'
	),
	rescheduled: __(
		'Event date and time have been changed.',
		'gatherpress'
	),
	'moved-online': __(
		'Event venue has changed to an online meeting.',
		'gatherpress'
	),
};

/**
 * EventStatus component.
 *
 * Operational status of an event ('scheduled', 'cancelled', 'postponed',
 * 'rescheduled', 'moved-online').
 *
 * Stored in the `_gatherpress_event_status` taxonomy so events can be filtered
 * by status, and read and written here as a plain slug through the
 * `gatherpress_status` REST field, so this stays a SelectControl rather than a
 * term-id round trip.
 *
 * @since 0.36.0
 *
 * @return {JSX.Element} A select control for the event status.
 */
const EventStatus = () => {
	const { editPost, unlockPostSaving } = useDispatch( 'core/editor' );

	const status = useSelect(
		( select ) =>
			select( 'core/editor' ).getEditedPostAttribute(
				'gatherpress_status'
			) || 'scheduled',
		[]
	);

	const updateStatus = useCallback(
		( value ) => {
			editPost( { gatherpress_status: value } );
			unlockPostSaving();
		},
		[ editPost, unlockPostSaving ]
	);

	return (
		<SelectControl
			__next40pxDefaultSize
			label={ __( 'Event status', 'gatherpress' ) }
			value={ status }
			onChange={ updateStatus }
			help={ STATUS_DESCRIPTIONS[ status ] || STATUS_DESCRIPTIONS.scheduled }
			__nextHasNoMarginBottom
		>
			<option value="scheduled">{ __( 'Scheduled', 'gatherpress' ) }</option>
			<option value="cancelled">{ __( 'Cancelled', 'gatherpress' ) }</option>
			<option value="postponed">{ __( 'Postponed', 'gatherpress' ) }</option>
			<option value="rescheduled">{ __( 'Rescheduled', 'gatherpress' ) }</option>
			<option value="moved-online">{ __( 'Moved online', 'gatherpress' ) }</option>
		</SelectControl>
	);
};

export default EventStatus;
