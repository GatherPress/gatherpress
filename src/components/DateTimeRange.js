/**
 * External dependencies.
 */
import moment from 'moment';

/**
 * WordPress dependencies.
 */
import { useDispatch, useSelect } from '@wordpress/data';
import { useEffect } from '@wordpress/element';

/**
 * Internal dependencies.
 */
import {
	dateTimeDatabaseFormat,
	dateTimeOffset,
	durationOptions,
} from '../helpers/datetime';
import DateTimeStart from '../components/DateTimeStart';
import DateTimeEnd from '../components/DateTimeEnd';
import Timezone from './Timezone';
import Duration from '../components/Duration';

/**
 * DateTimeRange component for GatherPress.
 *
 * This component manages the date and time range selection. It includes
 * DateTimeStart, DateTimeEnd, and Timezone components. The selected values
 * for the start date and time, end date and time, and timezone are managed in the
 * component's state. The component subscribes to the saveDateTime function,
 * which is triggered to save the selected date and time values.
 *
 * @since 1.0.0
 *
 * @return {JSX.Element} The rendered React component.
 */
const DateTimeRange = () => {
	const editPost = useDispatch('core/editor').editPost;
	let dateTimeMetaData = useSelect(
		(select) =>
			select('core/editor').getEditedPostAttribute('meta')
				?.gatherpress_datetime
	);

	try {
		dateTimeMetaData = dateTimeMetaData ? JSON.parse(dateTimeMetaData) : {};
	} catch (e) {
		dateTimeMetaData = {};
	}

	const { dateTimeStart, dateTimeEnd, duration, timezone } = useSelect(
		(select) => ({
			dateTimeStart: select('gatherpress/datetime').getDateTimeStart(),
			dateTimeEnd: select('gatherpress/datetime').getDateTimeEnd(),
			duration: select('gatherpress/datetime').getDuration(),
			timezone: select('gatherpress/datetime').getTimezone(),
		}),
		[]
	);
	const { setDuration } = useDispatch('gatherpress/datetime');

	useEffect(() => {
		const payload = JSON.stringify({
			...dateTimeMetaData,
			...{
				dateTimeStart: moment
					.tz(dateTimeStart, timezone)
					.format(dateTimeDatabaseFormat),
				dateTimeEnd: moment
					.tz(dateTimeEnd, timezone)
					.format(dateTimeDatabaseFormat),
				timezone,
			},
		});
		const meta = { gatherpress_datetime: payload };

		setDuration(
			durationOptions.some(
				(option) => dateTimeOffset(option.value) === dateTimeEnd
			)
				? duration
				: false
		);
		editPost({ meta });
	}, [
		dateTimeStart,
		dateTimeEnd,
		timezone,
		dateTimeMetaData,
		editPost,
		setDuration,
		duration,
	]);

	return (
		<>
			<section>
				<DateTimeStart />
			</section>
			<section>{duration ? <Duration /> : <DateTimeEnd />}</section>
			<section>
				<Timezone />
			</section>
		</>
	);
};

export default DateTimeRange;
