import React from 'react';
import ReactDOM from 'react-dom';
import AttendanceSelector from './components/AttendanceSelector';

const container = document.querySelector( '#gp-attendance-selector-container' );

if ( container ) {
	ReactDOM.render( <AttendanceSelector />, container );
}
