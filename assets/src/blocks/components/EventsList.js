import { useState, useEffect } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import EventItem from './EventItem';

const EventsList = ( props ) => {
	const { maxNumberOfEvents, type } = props;
	const [events, setEvents] = useState([]);
	const renderEvents = events.map((event) => {
		return <EventItem key={event.ID} type={type} event={event} />
	});

	useEffect(() => {
		apiFetch({
			path: `/gatherpress/v1/event/future-events?max_number=${maxNumberOfEvents}`
		}).then((events) => {
			setEvents(events);
		});
	}, [setEvents]);

	return (
		<div id={`gp-${type}-events`}>
			{0 === events.length &&
				<div>Loading...</div>
			}
			{renderEvents}
		</div>
	);
};

export default EventsList;
