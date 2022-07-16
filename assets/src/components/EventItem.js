import HtmlReactParser from 'html-react-parser';
import AttendanceSelector from './AttendanceSelector';
import AttendeeList from './AttendeeList';
import AttendeeResponse from './AttendeeResponse';

const EventItem = ( props ) => {
	if ('object' !== typeof GatherPress) {
		return '';
	}

	const { type, event } = props;

	const event_class = `gp-events-list`;

	return (
		<div className={event_class}>
			<div className={`${event_class}__header`}>
				<div className={`${event_class}__info`}>
					<div className={`${event_class}__datetime has-small-font-size`}>
						<strong>
							{event.datetime_start}
						</strong>
					</div>
					<div className={`${event_class}__title has-large-font-size`}>
						<a href={event.permalink}>
							{HtmlReactParser( event.title )}
						</a>
					</div>
				</div>
				<figure className={`${event_class}__image`}>
					<a href={event.permalink}>
						{HtmlReactParser(event.featured_image)}
					</a>
				</figure>
			</div>
			<div className={`${event_class}__content`}>
				<div className={`${event_class}__excerpt`}>
					{HtmlReactParser( event.excerpt )}
				</div>
			</div>
			<div className={`${event_class}__footer`}>
				<div className="gp-attendance-list__items">
					<AttendeeList eventId={event.ID} value="attending" attendees={event.attendees} limit="3" avatarOnly={true} />
				</div>
				{'upcoming' === type && (
					<AttendanceSelector eventId={event.ID} currentUser={event.current_user} />
				)}

				{'past' === type && (
					<AttendeeResponse type={type} status={event.current_user?.status} />
				)}
			</div>
		</div>
	);
}

export default EventItem;
