import React, { useState } from 'react';
import markup_future_events from "../apis/markup_future_events";

const UpcomingEvents = (props) => {
	const [markup, setMarkup] = useState( 'Loading...' );
	const { maxNumberOfEvents } = props;

	(async (setMarkup) => {
		const response = await markup_future_events.get('/markup_future_events', {
			params: {
				max_number: maxNumberOfEvents,
			}
		});

		setMarkup(response.data.markup);
	})(setMarkup);

	return <div onClick={(e) => e.preventDefault()} dangerouslySetInnerHTML={{ __html: markup }} />;
}

export default UpcomingEvents;
