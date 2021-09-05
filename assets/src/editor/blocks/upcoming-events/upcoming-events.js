import React from 'react';
import ReactDOM from 'react-dom';
import UpcomingEvents from './components/UpcomingEvents';

const container = document.querySelector( '#gp-upcoming-events-container' );

if ( container ) {
	ReactDOM.render( <UpcomingEvents />, container );
}
