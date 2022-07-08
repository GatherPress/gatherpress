import React from 'react';
import ReactDOM from 'react-dom';

/**
 * Internal dependencies.
 */
import EventsList from '../components/EventsList';

const container = document.querySelector( '#gp-past-events-container' );

if ( container ) {
	ReactDOM.render( <EventsList type="past" maxNumberOfEvents={container.dataset.max_posts} />, container );
}
