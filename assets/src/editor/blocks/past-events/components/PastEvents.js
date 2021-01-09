import React, { useState } from 'react';
import markup_past_events from "../apis/markup_past_events";

const PastEvents = (props) => {
	const [markup, setMarkup] = useState( '<div class="spinner gp-spinner"></div>' );
	const { maxNumberOfEvents } = props;

	(async (setMarkup) => {
		const response = await markup_past_events.get('/markup_past_events', {
			params: {
				max_number: maxNumberOfEvents,
			}
		});

		setMarkup(response.data.markup);
	})(setMarkup);

	return <div onClick={(e) => e.preventDefault()} dangerouslySetInnerHTML={{ __html: markup }} />;
}

export default PastEvents;
