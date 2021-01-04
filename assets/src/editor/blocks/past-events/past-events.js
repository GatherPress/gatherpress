import React from 'react';
import ReactDOM from 'react-dom';
import PastEvents from './components/PastEvents';

const container = document.querySelector('#gp-past-events-container');

if (container) {
	ReactDOM.render(<PastEvents />, container);
}
