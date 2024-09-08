/**
 * WordPress dependencies.
 */
import { subscribe } from '@wordpress/data';
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies.
 */
import { saveDateTime } from '../helpers/datetime';
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
	subscribe(saveDateTime);

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
