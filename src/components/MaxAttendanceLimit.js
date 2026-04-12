/**
 * WordPress dependencies.
 */
import { __ } from '@wordpress/i18n';
import {
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalNumberControl as NumberControl,
} from '@wordpress/components';
import { useState, useEffect, useCallback } from '@wordpress/element';
import { useDispatch, useSelect } from '@wordpress/data';

/**
 * Internal dependencies.
 */
import { getFromGlobal } from '../helpers/globals';

/**
 * MaxAttendance component.
 *
 * This component renders a number control that allows setting the maximum attendance limit for an event.
 * It handles the state and updates the post's metadata accordingly. When creating a new event, the default
 * state of the control is determined by a global setting. For existing events, it uses the event's current
 * setting. The component ensures that changes are reflected in the post's metadata and also unlocks post saving.
 *
 * @return {JSX.Element} A number control for setting the maximum attendance limit.
 */
const MaxAttendanceLimit = () => {
	const { editPost, unlockPostSaving } = useDispatch( 'core/editor' );
	const isNewEvent = useSelect( ( select ) => {
		return select( 'core/editor' ).isCleanNewPost();
	}, [] );

	let defaultMaxAttendanceLimit = useSelect( ( select ) => {
		return select( 'core/editor' ).getEditedPostAttribute( 'meta' )
			.gatherpress_max_attendance_limit;
	}, [] );

	if ( isNewEvent ) {
		defaultMaxAttendanceLimit = getFromGlobal(
			'settings.maxAttendanceLimit',
		);
	}

	if ( false === defaultMaxAttendanceLimit ) {
		defaultMaxAttendanceLimit = 0;
	}

	const [ maxAttendanceLimit, setMaxAttendanceLimit ] = useState(
		defaultMaxAttendanceLimit,
	);

	const updateMaxAttendanceLimit = useCallback(
		( value ) => {
			const meta = { gatherpress_max_attendance_limit: Number( value ) };

			setMaxAttendanceLimit( value );
			editPost( { meta } );
			unlockPostSaving();
		},
		[ editPost, unlockPostSaving ],
	);

	useEffect( () => {
		if ( isNewEvent && 0 !== defaultMaxAttendanceLimit ) {
			updateMaxAttendanceLimit( defaultMaxAttendanceLimit );
		}
	}, [ isNewEvent, defaultMaxAttendanceLimit, updateMaxAttendanceLimit ] );

	return (
		<>
			<NumberControl
				label={ __( 'Maximum Attendance Limit', 'gatherpress' ) }
				value={ maxAttendanceLimit }
				min={ 0 }
				onChange={ ( value ) => {
					updateMaxAttendanceLimit( value );
				} }
			/>
			<p className="description">
				{ __(
					'Total number of people allowed at the event. A value of 0 indicates no limit.',
					'gatherpress',
				) }
			</p>
		</>
	);
};

export default MaxAttendanceLimit;
