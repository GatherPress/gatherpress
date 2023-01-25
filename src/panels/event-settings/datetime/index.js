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
import DateTimeStartPanel from '../../../components/DateTimeStartPanel';
import DateTimeEndPanel from '../../../components/DateTimeEndPanel';
import TimeZonePanel from '../../../components/TimeZonePanel';

subscribe(saveDateTime);

const DateTimePanel = () => {
	const [dateTimeStart, setDateTimeStart] = useState();
	const [dateTimeEnd, setDateTimeEnd] = useState();
	const [timezone, setTimezone] = useState();

	return (
		<section>
			<h3>{__('Date & time', 'gatherpress')}</h3>
			<DateTimeStartPanel
				dateTimeStart={dateTimeStart}
				setDateTimeStart={setDateTimeStart}
			/>
			<DateTimeEndPanel
				dateTimeEnd={dateTimeEnd}
				setDateTimeEnd={setDateTimeEnd}
			/>
			<TimeZonePanel timezone={timezone} setTimezone={setTimezone} />
		</section>
	);
};

export default DateTimePanel;
