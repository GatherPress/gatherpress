import HtmlReactParser from 'html-react-parser';
import AttendanceSelector from './AttendanceSelector';
import AttendeeList from './AttendeeList';
import AttendeeResponse from './AttendeeResponse';

const EventItem = ( props ) => {
	if ( 'object' !== typeof GatherPress ) {
		return '';
	}

	const { type, event, descriptionLimit, showAttendeeList, showFeaturedImage, showDescription, showRsvpButton } = props;

	const limitExcerpt = ( excerpt ) => {
		return excerpt.split(" ").splice(0,parseInt(descriptionLimit)).join(" ") + '[…]';
	}

	const eventClass = `gp-events-list`;

	return (
		<div className={ eventClass }>
			<div className={ `${ eventClass }__header` }>
				<div className={ `${ eventClass }__info` }>
				{ showFeaturedImage && (
					<figure className={ `${ eventClass }__image` }>
						<a href={ event.permalink }>
							{/* Here we will need to put an image block with controls so it can be cropped */}
							{ HtmlReactParser( event.featured_image ) }
						</a>
					</figure>
				) }
					<div
						className={ `${ eventClass }__datetime has-small-font-size` }
					>
						<strong>{ event.datetime_start }</strong>
					</div>
					<div className={ `${ eventClass }__title has-large-font-size` }>
						<a href={ event.permalink }>
							{  HtmlReactParser( event.title ) }
						</a>
					</div>
					{ showDescription && (
						<div className={ `${ eventClass }__content` }>
							<div className={ `${ eventClass }__excerpt` }>
								{  HtmlReactParser( limitExcerpt( event.excerpt ) ) }
							</div>
						</div>
					) }
				</div>
			</div>
			<div className={ `${ eventClass }__footer` }>
			{ showAttendeeList && (
				<div className="gp-attendance-list__items">
					<AttendeeList
						eventId={ event.ID }
						value="attending"
						attendees={ event.attendees }
						limit="3"
						avatarOnly={ true }
					/>
				</div>
			)}
				{ ( 'upcoming' === type && showRsvpButton ) && (
					<AttendanceSelector
						eventId={ event.ID }
						currentUser={ event.current_user }
						type={ type }
					/>
				) }

				{ ( 'past' === type && showRsvpButton ) && (
					<AttendeeResponse
						type={ type }
						status={ event.current_user?.status }
					/>
				) }
			</div>
		</div>
	);
};

export default EventItem;
