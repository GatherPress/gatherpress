import React from 'react';
import ReactDOM from 'react-dom';
import EventsList from '../components/EventsList';

const container = document.querySelector( '#gp-upcoming-events-container' );

if ( container ) {
	ReactDOM.render( <EventsList type="upcoming" maxNumberOfEvents={container.dataset.max_posts} />, container );
}
