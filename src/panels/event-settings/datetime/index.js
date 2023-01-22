/**
 * External dependencies.
 */
import moment from 'moment';

/**
 * WordPress dependencies.
 */
import { subscribe } from '@wordpress/data';
import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies.
 */
import {
	dateTimeMomentFormat,
	getDateTimeStart,
	getDateTimeEnd,
	saveDateTime,
	timeZone,
} from '../../../helpers/datetime';
import { hasEventPastNotice } from '../../../helpers/event';
import DateTimeStartPanel from '../../../components/DateTimeStartPanel';
import DateTimeEndPanel from '../../../components/DateTimeEndPanel';

hasEventPastNotice();
subscribe(saveDateTime);

const DateTimePanel = () => {
	const [dateTimeStart, setDateTimeStart] = useState();
	const [dateTimeEnd, setDateTimeEnd] = useState();

	useEffect(() => {
		setDateTimeStart(
			moment.tz(getDateTimeStart(), timeZone).format(dateTimeMomentFormat)
		);
		setDateTimeEnd(
			moment.tz(getDateTimeEnd(), timeZone).format(dateTimeMomentFormat)
		);
	});

	return (
		<section>
			<h3>{__('Date & time', 'gatherpress')}</h3>
			<DateTimeStartPanel dateTimeStart={dateTimeStart} setDateTimeStart={setDateTimeStart} />
			<DateTimeEndPanel dateTimeEnd={dateTimeEnd} setDateTimeEnd={setDateTimeEnd} />
		</section>
	);
};

export default DateTimePanel;
