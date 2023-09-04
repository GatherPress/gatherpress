/**
 * WordPress dependencies.
 */
import { useState, useEffect } from '@wordpress/element';
import { Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies.
 */
import EventItem from './EventItem';
import apiFetch from '@wordpress/api-fetch';

const EventsList = (props) => {
	const { eventOptions, maxNumberOfEvents, type, topics, venues } = props;
	const [events, setEvents] = useState([]);
	const [loaded, setLoaded] = useState(false);
	const renderEvents = events.map((event) => {
		return (
			<EventItem
				key={event.ID}
				eventOptions={eventOptions}
				type={type}
				event={event}
			/>
		);
	});

	const renderNoEventsMessage = () => {
		const message =
			'upcoming' === type
				? __('There are no upcoming events.', 'gatherpress')
				: __('There are no past events.', 'gatherpress');

		return (
			<div className={`gp-${type}-events__no_events_message`}>
				{message}
			</div>
		);
	};

	useEffect(() => {
		let topicsString = '';
		let venuesString = '';

		if ('object' === typeof topics) {
			topicsString = topics
				.map((topic) => {
					return topic.slug;
				})
				?.join(',');
		}

		if ('object' === typeof venues) {
			venuesString = venues
				.map((venue) => {
					return venue.slug;
				})
				?.join(',');
		}

		apiFetch({
			path: `/gatherpress/v1/event/events-list?event_list_type=${type}&max_number=${maxNumberOfEvents}&topics=${topicsString}&venues=${venuesString}`,
		}).then((data) => {
			setLoaded(true);
			setEvents(data);
		});
	}, [setEvents, maxNumberOfEvents, type, topics, venues]);

	return (
		<div className={`gp-${type}-events-list`}>
			{!loaded && <Spinner />}
			{loaded && 0 === events.length && renderNoEventsMessage()}
			{loaded && renderEvents}
		</div>
	);
};

export default EventsList;
