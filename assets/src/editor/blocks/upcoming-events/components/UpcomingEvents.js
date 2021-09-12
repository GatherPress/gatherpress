import React, { useState } from 'react';
import ReactHtmlParser from 'react-html-parser';
import MarkupFutureEvents from '../apis/MarkupFutureEvents';

const UpcomingEvents = ( props ) => {
	const [ markup, setMarkup ] = useState( '<div class="spinner gp-spinner"></div>' );
	const { maxNumberOfEvents } = props;

	( async( setMarkup ) => {
		const response = await MarkupFutureEvents.get( '/markup_future_events', { /* eslint-disable camelcase */
			params: {
				max_number: maxNumberOfEvents /* eslint-disable camelcase */
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

export default UpcomingEvents;
