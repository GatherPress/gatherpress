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

const EventItem = (props) => {
	const { type, event, eventOptions } = props;
	const limitExcerpt = (excerpt) => {
		return (
			excerpt
				.split(' ')
				.splice(0, parseInt(eventOptions.descriptionLimit))
				.join(' ') + '[…]'
		);
	};
	const size =
		eventOptions.imageSize === 'default'
			? 'featured_image'
			: 'featured_image_' + eventOptions.imageSize;
	const featuredImage = HtmlReactParser(event[size]);
	const eventClass = `gp-events-list`;
	let icon = 'location';
	const isOnlineEvent = event.venue?.is_online_event;

	if (isOnlineEvent) {
		icon = 'video-alt2';
	}

	return (
		<div className={`${eventClass}`}>
			<div className={`${eventClass}__header`}>
				<div className={`${eventClass}__info`}>
					{eventOptions.showFeaturedImage && (
						<figure className={`${eventClass}__image`}>
							<a href={event.permalink}>{featuredImage}</a>
						</figure>
					)}
					<div
						className={`${eventClass}__datetime has-small-font-size`}
					>
						<strong>{event.datetime_start}</strong>
					</div>
					<div className={`${eventClass}__title has-large-font-size`}>
						<a href={event.permalink}>
							{HtmlReactParser(event.title)}
						</a>
					</div>
					{event.venue && eventOptions.showVenue && (
						<div className={`${eventClass}__venue`}>
							<span
								className={`dashicons dashicons-${icon}`}
							></span>
							{!isOnlineEvent && (
								<a href={event.venue.permalink}>
									{HtmlReactParser(event.venue.name)}
								</a>
							)}
							{isOnlineEvent && (
								<span>{HtmlReactParser(event.venue.name)}</span>
							)}
						</div>
					)}
					{eventOptions.showDescription && (
						<div className={`${eventClass}__content`}>
							<div className={`${eventClass}__excerpt`}>
								{HtmlReactParser(limitExcerpt(event.excerpt))}
							</div>
						</div>
					)}
				</div>
			</div>
			<div className={`${eventClass}__footer`}>
				{eventOptions.showRsvpResponse && (
					<div className="gp-rsvp-response__items">
						<RsvpResponseAvatarOnly
							eventId={event.ID}
							value="attending"
							responses={event.responses}
							limit="3"
						/>
					</div>
				)}
				{'upcoming' === type && eventOptions.showRsvp && (
					<Rsvp
						eventId={event.ID}
						currentUser={event.current_user}
						type={type}
					/>
				)}

				{'past' === type &&
					eventOptions.showRsvp &&
					'' !== event.current_user && (
						<RsvpStatusResponse
							type={type}
							status={event.current_user?.status}
						/>
					)}
			</div>
		</div>
	);
};

export default EventItem;
