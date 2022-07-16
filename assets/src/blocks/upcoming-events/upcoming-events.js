import React from 'react';
import ReactDOM from 'react-dom';
import EventsList from '../components/EventsList';

const containers = document.querySelectorAll( `[data-gp_id="gp-upcoming-events-container"]` );

for (let i =0; i < containers.length; i++) {
	ReactDOM.render( <EventsList type="upcoming" maxNumberOfEvents={containers[i].dataset.max_posts} />, containers[i] );
}
