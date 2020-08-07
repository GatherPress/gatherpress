import React, { createElement } from 'react';
import ReactDOM from 'react-dom';
import { AttendanceButton } from './attendance/button';
import { Attendance } from './attendance/attendance';

const domAttendanceButton = document.querySelector( '#attendance_button_container' );
const domAttendance       = document.querySelector( '#attendance_container' );

ReactDOM.render( createElement( AttendanceButton ), domAttendanceButton );
ReactDOM.render( createElement( Attendance ), domAttendance );
