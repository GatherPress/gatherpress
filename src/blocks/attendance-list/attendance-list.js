
import domReady from  '@wordpress/dom-ready';

import { render, useState } from '@wordpress/element';

/**
 * Internal dependencies.
 */
import AttendanceList from '../../components/AttendanceList';

const ReactApp = () => {
	// eslint-disable-next-line no-undef
	const postId = GatherPress.post_id;
	// eslint-disable-next-line no-undef
	const currentUser = GatherPress.current_user;

	return (
		<div className="react-place-code">
			<AttendanceList />
		</div>
	);
};


domReady( function() {
    const container = document.querySelector('.replace-attendance-list');
    render( <ReactApp />, container );
}); 
