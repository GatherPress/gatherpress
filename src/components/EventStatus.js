/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { SelectControl } from '@wordpress/components';
import { useCallback } from '@wordpress/element';
import { useDispatch, useSelect } from '@wordpress/data';

/**
 * Internal dependencies
 */
import { getStatusDescription, getStatusOptions } from '../helpers/event-status';

/**
 * EventStatus component.
 *
 * Operational status of an event, offering whatever statuses PHP publishes.
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
			options={ getStatusOptions() }
			help={ getStatusDescription( status ) }
			__nextHasNoMarginBottom
		/>
	);
};

export default EventStatus;
