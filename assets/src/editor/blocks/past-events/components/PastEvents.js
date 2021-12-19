import React, { useState } from 'react';
import ReactHtmlParser from 'react-html-parser';
import MarkupPastEvents from '../apis/MarkupPastEvents';

const PastEvents = ( props ) => {
	const [ markup, setMarkup ] = useState( '<div class="spinner gp-spinner"></div>' );
	const { maxNumberOfEvents } = props;

	( async( setMarkup ) => {
		const response = await MarkupPastEvents.get( '/markup-past-events', { /* eslint-disable camelcase */
			params: {
				max_number: maxNumberOfEvents
			}
		});

		setMarkup( response.data.markup );
	})( setMarkup );

	return (
		<div onClick={( e ) => e.preventDefault()}>
			{ReactHtmlParser( markup )}
		</div>
	);
};

export default PastEvents;
