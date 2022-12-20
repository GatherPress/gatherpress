import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import AttendanceListNavigation from './AttendanceListNavigation';
import AttendanceListContent from './AttendanceListContent';
import { Listener } from '../helpers/broadcasting';

const AttendanceList = () => {
	let defaultStatus = 'attending';
	const items = [
		{
			title:
				// eslint-disable-next-line no-undef
				'1' !== GatherPress.has_event_past
					? __( 'Attending', 'gatherpress' )
					: __( 'Went', 'gatherpress' ),
			value: 'attending',
		},
		{
			title:
				// eslint-disable-next-line no-undef
				'1' !== GatherPress.has_event_past
					? __( 'Waiting List', 'gatherpress' )
					: __( 'Wait Listed', 'gatherpress' ),
			value: 'waiting_list',
		},
		{
			title:
				// eslint-disable-next-line no-undef
				'1' !== GatherPress.has_event_past
					? __( 'Not Attending', 'gatherpress' )
					: __( "Didn't Go", 'gatherpress' ),
			value: 'not_attending',
		},
	];

	if ( 'object' === typeof GatherPress ) {
		// @todo redo this logic and have it come from API and not GatherPress object.
		defaultStatus =
			// eslint-disable-next-line no-undef
			'undefined' !== typeof GatherPress.current_user.status &&
			// eslint-disable-next-line no-undef
			'attend' !== GatherPress.current_user.status
				// eslint-disable-next-line no-undef
				? GatherPress.current_user.status
				: defaultStatus;
	}
	const defaultLimit = 10;

	const [ attendanceStatus, setAttendanceStatus ] = useState( defaultStatus );
	const [ attendeeLimit, setAttendeeLimit ] = useState( defaultLimit );

	// eslint-disable-next-line no-undef
	Listener( { setAttendanceStatus }, GatherPress.post_id );

	const onTitleClick = ( e, value ) => {
		e.preventDefault();

		setAttendanceStatus( value );
	};

	const updateLimit = ( e ) => {
		e.preventDefault();
		if ( false !== attendeeLimit ) {
			setAttendeeLimit( false );
		} else {
			setAttendeeLimit( defaultLimit );
		}
	};

	let loadListText;
	if ( false === attendeeLimit ) {
		loadListText = __( 'See less', 'gatherpress' );
	} else {
		loadListText = __( 'See more', 'gatherpress' );
	}

	return (
		<>
			<div className="gatherpress-attendance-list">
				<AttendanceListNavigation
					items={ items }
					activeValue={ attendanceStatus }
					onTitleClick={ onTitleClick }
				/>
				<AttendanceListContent
					items={ items }
					activeValue={ attendanceStatus }
					limit={ attendeeLimit }
				/>
			</div>
			<div className="has-text-align-right">
				{ /* eslint-disable-next-line jsx-a11y/anchor-is-valid */ }
				<a href="#" onClick={ ( e ) => updateLimit( e ) }>
					{ loadListText }
				</a>
			</div>
		</>
	);
};

export default AttendanceList;
