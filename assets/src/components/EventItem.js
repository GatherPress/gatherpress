import HtmlReactParser from 'html-react-parser';
import AttendanceSelector from './AttendanceSelector';
import AttendeeList from './AttendeeList';
import AttendeeResponse from './AttendeeResponse';

const EventItem = ( props ) => {
	if ('object' !== typeof GatherPress) {
		return '';
	}

	const { type, event } = props;

	const eventClass = `gp-events-list`;

	return (
		<div className={eventClass}>
			<div className={`${eventClass}__header`}>
				<div className={`${eventClass}__info`}>
					<figure className={`${eventClass}__image`}>
						<a href={event.permalink}>
							{HtmlReactParser(event.featured_image)}
						</a>
					</figure>
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
					<div className={`${eventClass}__content`}>
						<div className={`${eventClass}__excerpt`}>
							{HtmlReactParser(event.excerpt)}
						</div>
					</div>
				</div>
			</div>
			<div className={`${eventClass}__footer`}>
				<div className="gp-attendance-list__items">
					<AttendeeList eventId={event.ID} value="attending" attendees={event.attendees} limit="3" avatarOnly={true} />
				</div>
				{'upcoming' === type && (
					<AttendanceSelector
						eventId={event.ID}
						currentUser={event.current_user}
						type={type}
					/>
				)}

				{'past' === type && (
					<AttendeeResponse
						type={type}
						status={event.current_user?.status}
					/>
				)}
			</div>
		</div>
	);
}

export default EventItem;
