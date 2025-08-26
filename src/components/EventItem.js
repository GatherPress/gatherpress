/**
 * External dependencies.
 */
import HtmlReactParser from 'html-react-parser';

/**
 * Internal dependencies.
 */
import Rsvp from './Rsvp';
import RsvpResponseAvatarOnly from './RsvpResponseAvatarOnly';
import RsvpStatusResponse from './RsvpStatusResponse';

/**
 * EventItem component for GatherPress.
 *
 * This component represents an individual event item in the events list.
 * It displays various details of the event, including the featured image,
 * date and time, title, venue, and description. It also handles RSVP and
 * RSVP response components based on the event type.
 *
 * @since 1.0.0
 *
 * @param {Object} props              - Component properties.
 * @param {string} props.type         - The type of the event (upcoming or past).
 * @param {Object} props.event        - The event data.
 * @param {Object} props.eventOptions - Options for displaying the event.
 *
 * @return {JSX.Element} The rendered React component.
 */
const EventItem = ( props ) => {
	const { type, event, eventOptions } = props;
	const limitExcerpt = ( excerpt ) => {
		return (
			excerpt
				.split( ' ' )
				.splice( 0, parseInt( eventOptions.descriptionLimit ) )
				.join( ' ' ) + '[…]'
		);
	};
	const size =
		'default' === eventOptions.imageSize
			? 'featured_image'
			: 'featured_image_' + eventOptions.imageSize;
	const featuredImage = HtmlReactParser( event[ size ] );
	const eventClass = `gatherpress-events-list`;
	let icon = 'location';
	const isOnlineEvent = event.venue?.is_online_event;

	if ( isOnlineEvent ) {
		icon = 'video-alt2';
	}

	return (
		<div className={ `${ eventClass }` }>
			<div className={ `${ eventClass }__header` }>
				<div className={ `${ eventClass }__info` }>
					{ eventOptions.showFeaturedImage && (
						<figure className={ `${ eventClass }__image` }>
							<a href={ event.permalink }>{ featuredImage }</a>
						</figure>
					) }
					<div className={ `${ eventClass }__datetime` }>
						<strong>{ event.datetime_start }</strong>
					</div>
					<div className={ `${ eventClass }__title` }>
						<a href={ event.permalink }>
							{ HtmlReactParser( event.title ) }
						</a>
					</div>
					{ event.venue && eventOptions.showVenue && (
						<div className={ `${ eventClass }__venue` }>
							<span
								className={ `dashicons dashicons-${ icon }` }
							></span>
							{ ! isOnlineEvent && (
								<a href={ event.venue.permalink }>
									{ HtmlReactParser( event.venue.name ) }
								</a>
							) }
							{ isOnlineEvent && (
								<span>{ HtmlReactParser( event.venue.name ) }</span>
							) }
						</div>
					) }
					{ eventOptions.showDescription && (
						<div className={ `${ eventClass }__content` }>
							<div className={ `${ eventClass }__excerpt` }>
								{ HtmlReactParser( limitExcerpt( event.excerpt ) ) }
							</div>
						</div>
					) }
				</div>
			</div>
			<div className={ `${ eventClass }__footer` }>
				{ eventOptions.showRsvpResponse && (
					<div className="gatherpress-rsvp-response__items">
						<RsvpResponseAvatarOnly
							postId={ event.ID }
							value="attending"
							responses={ event.responses }
							limit="3"
						/>
					</div>
				) }
				{ 'upcoming' === type && eventOptions.showRsvp && (
					<Rsvp
						postId={ event.ID }
						currentUser={ event.current_user }
						type={ type }
						enableAnonymousRsvp={
							event.gatherpress_enable_anonymous_rsvp
						}
					/>
				) }

				{ 'past' === type &&
					eventOptions.showRsvp &&
					'' !== event.current_user && (
					<RsvpStatusResponse
						type={ type }
						status={ event.current_user?.status }
					/>
				) }
			</div>
		</div>
	);
};

export default EventItem;
