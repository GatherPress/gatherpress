import React, { useState } from 'react';
import MarkupPastEvents from '../apis/MarkupPastEvents';

const PastEvents = ( props ) => {
	const [ markup, setMarkup ] = useState( '<div class="spinner gp-spinner"></div>' );
	const { maxNumberOfEvents } = props;

	( async( setMarkup ) => {
		const response = await MarkupPastEvents.get( '/markup_past_events', { /* eslint-disable camelcase */
			params: {
				max_number: maxNumberOfEvents /* eslint-disable camelcase */
			}
		});

		setMarkup( response.data.markup );
	})( setMarkup );

	return <div onClick={( e ) => e.preventDefault()} dangerouslySetInnerHTML={{ __html: markup }} />;
};

export default PastEvents;
