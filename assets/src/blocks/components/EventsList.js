/**
 * WordPress dependencies.
 */
import { useState, useEffect } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { Spinner } from '@wordpress/components';

/**
 * Block dependencies.
 */
import EventItem from './EventItem';

const EventsList = (props) => {
	const { maxNumberOfEvents, type } = props;
	const [events, setEvents] = useState([]);
	const renderEvents = events.map((event) => {
		return <EventItem key={event.ID} type={type} event={event} />;
	});

	useEffect(() => {
		apiFetch({
			path: `/gatherpress/v1/event/${type}-events?max_number=${maxNumberOfEvents}`,
		}).then((e) => {
			setEvents(e);
		});
	}, [setEvents, maxNumberOfEvents, type]);

	return (
		<div id={`gp-${type}-events`}>
			{0 === events.length && <Spinner />}
			{renderEvents}
		</div>
	);
};

export default EventsList;
