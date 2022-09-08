/**
 * WordPress dependencies.
 */
import { useState, useEffect } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { Spinner } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies.
 */
import EventItem from './EventItem';

const EventsList = ( props ) => {
	const { descriptionLimit, imageSize, maxNumberOfEvents, type, topics, showAttendeeList, showFeaturedImage, showDescription, showRsvpButton } = props;
	const [ events, setEvents ] = useState( [] );
	const [ loaded, setLoaded ] = useState( false );
	const renderEvents = events.map( ( event ) => {
		return <EventItem key={ event.ID } descriptionLimit={ descriptionLimit } imageSize={ imageSize } type={ type } event={ event } showAttendeeList={ showAttendeeList } showFeaturedImage={ showFeaturedImage } showDescription={ showDescription } showRsvpButton={ showRsvpButton } />;
	} );
	const renderNoEventsMessage = () => {
		const message =
			'upcoming' === type
				? __( 'There are no upcoming events.', 'gatherpress' )
				: __( 'There are no past events.', 'gatherpress' );

		return (
			<div className={ `gp-${ type }-events__no_events_message` }>
				{ message }
			</div>
		);
	};

	useEffect( () => {
		let topicsString = '';

		if ( 'object' === typeof topics ) {
			topicsString = topics.map( ( topic ) => {
				return topic.slug;
			} )?.join( ',' );
		}

		apiFetch( {
			path: `/gatherpress/v1/event/events-list?event_list_type=${ type }&max_number=${ maxNumberOfEvents }&topics=${ topicsString }`,
		} ).then( ( e ) => {
			setLoaded( true );
			setEvents( e );
		} );
	}, [ setEvents, maxNumberOfEvents, type, topics ] );

	return (
		<div className={ `gp-${ type }-events` }>
			{ ! loaded && <Spinner /> }
			{ loaded && 0 === events.length && renderNoEventsMessage() }
			{ loaded && renderEvents }
		</div>
	);
};

export default EventsList;
