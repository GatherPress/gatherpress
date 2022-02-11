import React, { useState } from 'react';
import {__} from '@wordpress/i18n';
import AttendanceSelectorItem from './AttendanceSelectorItem';
import attendance from '../apis/attendance';


const AttendanceSelector = () => {
	if ( 'object' !== typeof GatherPress ) {
		return '';
	}

	let defaultStatus = GatherPress.current_user_status.status;

	const [ attendanceStatus, setAttendanceStatus ] = useState( defaultStatus );
	const [ selectorHidden, setSelectorHidden ] = useState( 'hidden' );
	const [ selectorExpanded, setSelectorExpanded ] = useState( 'false' );

	const items = [
		{
			text: GatherPress.settings.language.attendance.attending_text,
			status: 'attending'
		},
		{
			text: GatherPress.settings.language.attendance.not_attending_text,
			status: 'not_attending'
		}
	];

	const onAnchorClick = async( e, status ) => {
		e.preventDefault();

		const response = await attendance.post( '/attendance', {
			status: status
		});

		if ( response.data.success ) {
			setAttendanceStatus( response.data.status );

			const dispatchAttendanceStatus = new CustomEvent( 'setAttendanceStatus', {
				detail: response.data.status
			});

			dispatchEvent( dispatchAttendanceStatus );

			const dispatchAttendanceList = new CustomEvent( 'setAttendanceList', {
				detail: response.data.attendees
			});

			dispatchEvent( dispatchAttendanceList );

			let count = {
				all: 0,
				attending: 0,
				not_attending: 0, // eslint-disable-line camelcase
				waiting_list: 0 // eslint-disable-line camelcase
			};

			for ( const [ key, value ] of Object.entries( response.data.attendees ) ) {
				count[key] = value.count;
			}

			const dispatchAttendanceCount = new CustomEvent( 'setAttendanceCount', {
				detail: count
			});

			dispatchEvent( dispatchAttendanceCount );
		}
	};

	const getStatusText = ( status ) => {
		switch ( status ) {
			case 'attending':
				return GatherPress.settings.language.attendance.attending;
			case 'not_attending':
				return GatherPress.settings.language.attendance.not_attending;
			case 'waiting_list':
				return GatherPress.settings.language.attendance.waiting_list;
		}

		return GatherPress.settings.language.attendance.attend;
	};

	const renderedItems = items.map( ( item, index ) => {
		const { text, status } = item;
		return (
			<AttendanceSelectorItem
				key={index}
				text={text}
				status={status}
				onAnchorClick={onAnchorClick}
			/>
		);
	});

	const onSpanKeyDown = ( e ) => {
		if ( 13 === e.keyCode ) {
			setSelectorHidden( ( 'hidden' === selectorHidden ) ? 'show' : 'hidden' );
			setSelectorExpanded( ( 'false' === selectorExpanded ) ? 'true' : 'false' );
		}
	};

	if ( '' === GatherPress.current_user_status ) {
		return (
			<div className="gp-attendance-selector">
				<div className="wp-block-button">
					<a
						className="wp-block-button__link"
						href="#"
						onClick={ e => onAnchorClick( e, 'attending' )}
					>
						{ getStatusText( attendanceStatus ) }
					</a>
				</div>
			</div>
		);
	}

	return (
		<div className="gp-attendance-selector">
			<div>Current Response: </div>
			<span
				className="gp-attendance-selector__container"
				aria-expanded={selectorExpanded}
				tabIndex="0"
				onKeyDown={onSpanKeyDown}
			>
				<span className="gp-attendance-selector__text">{ getStatusText( attendanceStatus ) }</span>
				<svg className="gp-attendance-selector__svg" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
					<path d = "M9.293 12.95l.707.707L15.657 8l-1.414-1.414L10 10.828 5.757 6.586 4.343 8z" />
				</svg>
			</span>
			<ul
				className={`gp-attendance-selector__options ${selectorHidden}`}
			>
				{renderedItems}
			</ul>
		</div>
	);
};

export default AttendanceSelector;
