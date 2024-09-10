/**
 * External dependencies.
 */
import moment from 'moment';

/**
 * WordPress dependencies.
 */
import { useDispatch, useSelect } from '@wordpress/data';
import { useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies.
 */
import { dateTimeDatabaseFormat } from '../helpers/datetime';
import DateTimeStart from '../components/DateTimeStart';
import DateTimeEnd from '../components/DateTimeEnd';
import TimeZone from '../components/TimeZone';


/**
 * DateTimeRange component for GatherPress.
 *
 * This component manages the date and time range selection. It includes
 * DateTimeStart, DateTimeEnd, and TimeZone components. The selected values
 * for start date and time, end date and time, and timezone are managed in the
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
				.gatherpress_datetime
	);

	try {
		dateTimeMetaData = dateTimeMetaData ? JSON.parse(dateTimeMetaData) : {};
	} catch (e) {
		dateTimeMetaData = {};
	}

	const { dateTimeStart, dateTimeEnd, timezone } = useSelect(
		(select) => ({
			dateTimeStart: select('gatherpress/datetime').getDateTimeStart(),
			dateTimeEnd: select('gatherpress/datetime').getDateTimeEnd(),
			timezone: select('gatherpress/datetime').getTimezone(),
		}),
		[]
	);

	useEffect(() => {
		const payload = JSON.stringify({
			...dateTimeMetaData,
			...{
				dateTimeStart: moment.tz(dateTimeStart, timezone).format(dateTimeDatabaseFormat),
				dateTimeEnd: moment.tz(dateTimeEnd, timezone).format(dateTimeDatabaseFormat),
				timezone,
			},
		});
		const meta = { gatherpress_datetime: payload };

		editPost({ meta });
	}, [dateTimeStart, dateTimeEnd, timezone]);

	return (
		<>
			<h3>{__('Date & time', 'gatherpress')}</h3>
			<DateTimeStart />
			<DateTimeEnd />
			<TimeZone />
		</>
	);
};

export default DateTimeRange;
