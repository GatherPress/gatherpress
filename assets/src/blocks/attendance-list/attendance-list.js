/**
 * External dependencies.
 */
import React from 'react';
import ReactDOM from 'react-dom';
/**
 * Internal dependencies.
 */
import AttendanceList from '../../components/AttendanceList';

const containers = document.querySelectorAll( `[data-gp_block_name="attendance-list"]` );

for (let i =0; i < containers.length; i++) {
	ReactDOM.render( <AttendanceList />, containers[i] );
}
