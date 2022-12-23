import { render } from '@wordpress/element';

import domReady from  '@wordpress/dom-ready';
import { Button } from '@wordpress/components';

const ReactApp = () => {
    return(
        <div>
            <p>
                This is REACT!!
                <Button variant="primary">Click me!</Button>
            </p>
        </div>
    )
}

// wp.domReady(
domReady( function() {
    const container = document.querySelector('.gatherpress-attendance-selector-replace-me-here');
    render( <ReactApp />, container );
}); 
