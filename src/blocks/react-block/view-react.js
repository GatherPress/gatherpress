import { render } from '@wordpress/element';

import domReady from  '@wordpress/dom-ready';

// ReactDOM.render( <ReactApp />, container );

const ReactApp = () => <div>This is REACT!!</div>

// wp.domReady(
domReady( function() {
    const container = document.querySelector('#react-app');
    render( <ReactApp />, container );
}); 
