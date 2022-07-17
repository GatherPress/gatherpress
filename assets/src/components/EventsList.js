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
	const { maxNumberOfEvents, type } = props;
	const [ events, setEvents ] = useState( [] );
	const [ loaded, setLoaded ] = useState( false );
	const renderEvents = events.map( ( event ) => {
		return <EventItem key={ event.ID } type={ type } event={ event } />;
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
		apiFetch( {
			path: `/gatherpress/v1/event/${ type }-events?max_number=${ maxNumberOfEvents }`,
		} ).then( ( e ) => {
			setLoaded( true );
			setEvents( e );
		} );
	}, [ setEvents, maxNumberOfEvents, type ] );

	return (
		<div className={ `gp-${ type }-events` }>
			{ ! loaded && <Spinner /> }
			{ loaded && 0 === events.length && renderNoEventsMessage() }
			{ loaded && renderEvents }
		</div>
	);
};

export default EventsList;
