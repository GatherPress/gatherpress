
import domReady from  '@wordpress/dom-ready';

import { render, useState } from '@wordpress/element';

import { Button, Modal } from "@wordpress/components";

/**
 * Internal dependencies.
 */
import AttendanceSelector from '../../components/AttendanceSelector';

const ReactApp = () => {
	// eslint-disable-next-line no-undef
	const postId = GatherPress.post_id;
	// eslint-disable-next-line no-undef
	const currentUser = GatherPress.current_user;

	return (
		<div className="react-place-code">
			<AttendanceSelector
				eventId={ postId }
				currentUser={ currentUser }
				type={ 'upcoming' }
			/>
		</div>
	);
};


domReady( function() {
    const container = document.querySelector('.gatherpress-attendance-selector-here');
    render( <ReactApp />, container );
}); 
