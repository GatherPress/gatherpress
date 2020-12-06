import React, { useState } from 'react';
import markup_future_events from "../apis/markup_future_events";

const UpcomingEvents = () => {
	const [markup, setMarkup] = useState( 'Loading...' );

	(async (setMarkup) => {
		const response = await markup_future_events.get('/markup_future_events', {
			status: status,
		});

		setMarkup(response.data.markup);
	})(setMarkup);

	return <div id="foobar" onClick={(e) => e.preventDefault()} dangerouslySetInnerHTML={{ __html: markup }} />;
}

export default UpcomingEvents;
