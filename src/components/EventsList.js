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
import { getFromGlobal } from '../helpers/globals';
import apiFetch from '@wordpress/api-fetch';

/**
 * EventsList component for GatherPress.
 *
 * This component displays a list of events based on the provided parameters.
 * It retrieves the events from the server using the WordPress REST API or
 * GatherPress custom API, depending on whether the user is logged in or not.
 *
 * @since 1.0.0
 *
 * @param {Object} props                   - Component properties.
 * @param {Object} props.eventOptions      - Options for displaying each event in the list.
 * @param {number} props.maxNumberOfEvents - The maximum number of events to display.
 * @param {string} props.type              - The type of events to retrieve ('upcoming' or 'past').
 * @param {Array}  props.topics            - An array of topic objects to filter events by.
 * @param {Array}  props.venues            - An array of venue objects to filter events by.
 *
 * @return {JSX.Element} The rendered React component.
 */
const EventsList = ( props ) => {
	const {
		eventOptions,
		maxNumberOfEvents,
		datetimeFormat,
		type,
		topics,
		venues,
	} = props;
	const [ events, setEvents ] = useState( [] );
	const [ loaded, setLoaded ] = useState( false );
	const renderEvents = events.map( ( event ) => {
		return (
			<EventItem
				key={ event.ID }
				eventOptions={ eventOptions }
				type={ type }
				event={ event }
			/>
		);
	} );

	const renderNoEventsMessage = () => {
		const message =
			'upcoming' === type
				? __( 'There are no upcoming events.', 'gatherpress' )
				: __( 'There are no past events.', 'gatherpress' );

		return (
			<div className={ `gatherpress-${ type }-events__no_events_message` }>
				{ message }
			</div>
		);
	};

	useEffect( () => {
		let topicsString = '';
		let venuesString = '';

		if ( 'object' === typeof topics ) {
			topicsString = topics
				.map( ( topic ) => {
					return topic.slug;
				} )
				?.join( ',' );
		}

		if ( 'object' === typeof venues ) {
			venuesString = venues
				.map( ( venue ) => {
					return venue.slug;
				} )
				?.join( ',' );
		}

		/**
		 * Check if user is logged in, so we have current_user for the event present, which
		 * allows them to interact with the block.
		 */
		if ( getFromGlobal( 'misc.isUserLoggedIn' ) ) {
			apiFetch( {
				path:
					getFromGlobal( 'urls.eventApiPath' ) +
					`/events-list?event_list_type=${ type }&max_number=${ maxNumberOfEvents }&datetime_format=${ datetimeFormat }&topics=${ topicsString }&venues=${ venuesString }`,
			} ).then( ( data ) => {
				setLoaded( true );
				setEvents( data );
			} );
		} else {
			/**
			 * Not using apiFetch helper here as it will use X-Wp-Nonce and cache it when page caching is on causing a 403.
			 *
			 * @see https://github.com/GatherPress/gatherpress/issues/300
			 */
			fetch(
				getFromGlobal( 'urls.eventApiUrl' ) +
					`/events-list?event_list_type=${ type }&max_number=${ maxNumberOfEvents }&datetime_format=${ datetimeFormat }&topics=${ topicsString }&venues=${ venuesString }`,
			)
				.then( ( response ) => {
					return response.json();
				} )
				.then( ( data ) => {
					setLoaded( true );
					setEvents( data );
				} );
		}
	}, [ setEvents, maxNumberOfEvents, datetimeFormat, type, topics, venues ] );

	return (
		<div className={ `gatherpress-${ type }-events-list` }>
			{ ! loaded && <Spinner /> }
			{ loaded && 0 === events.length && renderNoEventsMessage() }
			{ loaded && renderEvents }
		</div>
	);
};

export default EventsList;
