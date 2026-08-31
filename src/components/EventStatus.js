/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { SelectControl } from '@wordpress/components';
import { useCallback } from '@wordpress/element';
import { useDispatch, useSelect } from '@wordpress/data';

/**
 * EventStatus component.
 *
 * Operational status of an event ('scheduled', 'cancelled', 'postponed',
 * 'rescheduled', 'moved-online'), stored as the `gatherpress_status` post meta.
 *
 * @since 0.36.0
 *
 * @return {JSX.Element} A select control for the event status.
 */
const EventStatus = () => {
	const { editPost, unlockPostSaving } = useDispatch( 'core/editor' );

	const status = useSelect(
		( select ) =>
			select( 'core/editor' ).getEditedPostAttribute( 'meta' )
				?.gatherpress_status || 'scheduled',
		[]
	);

	const updateStatus = useCallback(
		( value ) => {
			editPost( { meta: { gatherpress_status: value } } );
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
			help={ __(
				'Set the operational status of the event.',
				'gatherpress'
			) }
			__nexthasnomarginbottom
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
