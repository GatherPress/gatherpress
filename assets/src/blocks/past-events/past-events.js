/**
 * External dependencies.
 */
import React from 'react';
import ReactDOM from 'react-dom';
/**
 * Internal dependencies.
 */
import EventsList from '../components/EventsList';

const containers = document.querySelectorAll( `[data-gp_block_name="past-events"]` );

for (let i =0; i < containers.length; i++) {
	const attrs = JSON.parse( containers[i].dataset.gp_block_attrs );

	ReactDOM.render( <EventsList type="past" maxNumberOfEvents={attrs.maxNumberofEvents ?? 5} />, containers[i] );
}
