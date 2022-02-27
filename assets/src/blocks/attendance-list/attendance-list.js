import React from 'react';
import ReactDOM from 'react-dom';
import AttendanceList from '../components/AttendanceList';

const container = document.querySelector( '#gp-attendance-list-container' );

if ( container ) {
	ReactDOM.render( <AttendanceList />, container );
}
