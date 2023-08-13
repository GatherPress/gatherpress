/**
 * WordPress dependencies.
 */
import { subscribe } from '@wordpress/data';
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies.
 */
import { saveDateTime } from '../../../helpers/datetime';
import DateTimeStart from '../../../components/DateTimeStart';
import DateTimeEnd from '../../../components/DateTimeEnd';
import TimeZone from '../../../components/TimeZone';

subscribe(saveDateTime);

const DateTimePanel = () => {
	const [dateTimeStart, setDateTimeStart] = useState();
	const [dateTimeEnd, setDateTimeEnd] = useState();
	const [timezone, setTimezone] = useState();

	return (
		<section>
			<h3>{__('Date & time', 'gatherpress')}</h3>
			<DateTimeStart
				dateTimeStart={dateTimeStart}
				setDateTimeStart={setDateTimeStart}
			/>
			<DateTimeEnd
				dateTimeEnd={dateTimeEnd}
				setDateTimeEnd={setDateTimeEnd}
			/>
			<TimeZone timezone={timezone} setTimezone={setTimezone} />
		</section>
	);
};

export default DateTimePanel;
