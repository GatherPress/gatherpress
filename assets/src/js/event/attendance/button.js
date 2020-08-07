import React, { Component } from 'react';
import { __ } from '@wordpress/i18n';

import { updateAttendanceList, updateActiveNavigation } from './attendance';

export class AttendanceButton extends Component {

	constructor( props ) {
		super( props );

		this.state = {
			inputValue: this.attendanceStatus( GatherPress.current_user_status )
		};
	}

	attendanceStatus( status ) {

		switch ( status ) {
			case 'attending':
				return __( 'Attending', 'gatherpress' );
			case 'not_attending':
				return __( 'Not Attending', 'gatherpress' );
			case 'waitlist':
				return __( 'On Waitlist', 'gatherpress' );
		}

		return __( 'Attend', 'gatherpress' );

	}

	changeSelection( evt ) {

		evt.preventDefault();

		let status = evt.target.getAttribute( 'data-value' );

		this.updateStatus( status );

	}

	updateStatus( status ) {

		const requestOptions = {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': GatherPress.nonce,
			},
			body: JSON.stringify({
				status: status,
				post_id: GatherPress.post_id,
				_wpnonce: GatherPress.nonce
			})
		};
		fetch(
			GatherPress.event_rest_api + 'attendance', requestOptions
		).then( results => {
			return results.json();
		}).then( data => {

			if ( data.success ) {
				this.setState({
					inputValue: this.attendanceStatus( data.status )
				});

				updateAttendanceList( data.attendees );
				updateActiveNavigation( data.status );
			}

		});
	}

	render() {
		const hasEventPast = ( '1' === GatherPress.has_event_past ) ? 'opacity-50 cursor-not-allowed' : '';

		return(
			<div
				className  = 'group inline-block relative float-right'
			>
				<button
					type          = 'button'
					className     = { 'bg-blue-500 hover:bg-blue-700 text-white text-2xl py-2 px-4 rounded inline-flex items-center ' + hasEventPast }
					data-toggle   = 'dropdown'
					aria-haspopup = 'true'
					aria-expanded = 'false'
				>
					<span
						className = 'mr-1'
					>
						{ this.state.inputValue }
					</span>
					<svg
						className = 'fill-current h-4 w-4'
						xmlns     = 'http://www.w3.org/2000/svg'
						viewBox   = '0 0 20 20'
					>
						<path
						d="M9.293 12.95l.707.707L15.657 8l-1.414-1.414L10 10.828 5.757 6.586 4.343 8z"
						/>
					</svg>
				</button>
				<ul
					className       = 'absolute right-0 z-10 hidden text-gray-700 pt-1 group-hover:block'
				>
					<li>
						<a
							className  = 'rounded-t bg-gray-200 hover:bg-gray-400 py-2 px-4 block whitespace-no-wrap'
							href       = '#'
							data-value = 'attending'
							onClick    = { ( evt ) => this.changeSelection( evt ) }
						>
							{ __( 'Yes, I would like to attend this event.', 'gatherpress' ) }
						</a>

					</li>
					<li>
						<a
						className  = 'rounded-b bg-gray-200 hover:bg-gray-400 py-2 px-4 block whitespace-no-wrap'
						href       = '#'
						data-value = 'not_attending'
						onClick    = { ( evt ) => this.changeSelection( evt ) }
						>
							{ __( 'No, I cannot attend this event.', 'gatherpress' ) }
						</a>

					</li>
				</ul>
			</div>
		);
	}
}
