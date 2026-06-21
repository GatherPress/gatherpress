/**
 * WordPress dependencies
 */
import { useDispatch, useSelect } from '@wordpress/data';
import { useEffect } from '@wordpress/element';

/**
 * Internal dependencies
 */
import {
	dateTimeDatabaseFormat,
	createMomentWithTimezone,
	useMatchedDuration,
} from '../helpers/datetime';
import DateTimeStart from '../components/DateTimeStart';
import DateTimeEnd from '../components/DateTimeEnd';
import Timezone from './Timezone';
import Duration from '../components/Duration';

/**
 * DateTimeRange component for GatherPress.
 *
 * This component manages the selection of a date and time range for events.
 * It includes DateTimeStart, DateTimeEnd, and Timezone components to allow users
 * to set the event's start date, end date, and timezone. The component pulls
 * these values from the state using WordPress data stores and subscribes to changes
 * via the `saveDateTime` function. On changes, the component updates the post meta
 * with the selected date and time values, formatted for the database.
 *
 * The component also handles the duration of the event, checking if the end time
 * matches a predefined duration option and updating the duration accordingly.
 *
 * @since 0.27.0
 *
 * @return {JSX.Element} The rendered DateTimeRange React component.
 */
const DateTimeRange = () => {
	const editPost = useDispatch( 'core/editor' ).editPost;
	let dateTimeMetaData = useSelect(
		( select ) =>
			select( 'core/editor' ).getEditedPostAttribute( 'meta' )
				?.gatherpress_datetime,
	);

	try {
		dateTimeMetaData = dateTimeMetaData ? JSON.parse( dateTimeMetaData ) : {};
	} catch ( e ) {
		// eslint-disable-next-line no-console
		console.error( 'Failed to parse gatherpress_datetime meta:', e );
		dateTimeMetaData = {};
	}

	const { dateTimeStart, dateTimeEnd, timezone } = useSelect(
		( select ) => ( {
			dateTimeStart: select( 'gatherpress/datetime' ).getDateTimeStart(),
			dateTimeEnd: select( 'gatherpress/datetime' ).getDateTimeEnd(),
			timezone: select( 'gatherpress/datetime' ).getTimezone(),
		} ),
		[],
	);
	// Matched preset (or `false`) for the start/end pair. Memoized on the
	// inputs so the moment.tz comparisons run once per real change rather
	// than once per render — see `useMatchedDuration` for the #1607 context.
	const matchedDuration = useMatchedDuration();

	useEffect( () => {
		const payload = JSON.stringify( {
			...dateTimeMetaData,
			dateTimeStart: createMomentWithTimezone( dateTimeStart, timezone )
				.format( dateTimeDatabaseFormat ),
			dateTimeEnd: createMomentWithTimezone( dateTimeEnd, timezone )
				.format( dateTimeDatabaseFormat ),
			timezone,
		} );
		const meta = { gatherpress_datetime: payload };

		editPost( { meta } );
	}, [
		dateTimeStart,
		dateTimeEnd,
		timezone,
		dateTimeMetaData,
		editPost,
	] );

	return (
		<>
			<section>
				<DateTimeStart />
			</section>
			<section>
				{ matchedDuration ? <Duration /> : <DateTimeEnd /> }
			</section>
			<section>
				<Timezone />
			</section>
		</>
	);
};

export default DateTimeRange;
