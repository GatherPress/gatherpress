/**
 * External dependencies.
 */
import React from 'react';
import ReactDOM from 'react-dom';
/**
 * Internal dependencies.
 */
import AttendanceSelector from '../../components/AttendanceSelector';

const containers = document.querySelectorAll( `[data-gp_block_name="attendance-selector"]` );

for (let i =0; i < containers.length; i++) {
	ReactDOM.render( <AttendanceSelector eventId={GatherPress.post_id} currentUser={GatherPress.current_user} />, containers[i] );
}
