/**
 * External dependencies.
 */
import moment from 'moment';

/**
 * WordPress dependencies.
 */
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { Flex, FlexItem, Icon, PanelBody } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies.
 */
import { Listener } from '../../helpers/broadcasting';
import DateTimeStartPanel from '../../components/DateTimeStartPanel';
import DateTimeEndPanel from '../../components/DateTimeEndPanel';
import {
	defaultDateTimeEnd,
	defaultDateTimeStart,
	getTimeZone,
	getUtcOffset,
} from '../../helpers/datetime';
import TimeZonePanel from '../../components/TimeZonePanel';

/**
 * Similar to get_display_datetime method in class-event.php.
 *
 * @param {string} start
 * @param {string} end
 * @param {string} tz
 * @return {string} Displayed date.
 */
const displayDateTime = (start, end, tz) => {
	const dateFormat = 'dddd, MMMM D, YYYY';
	const timeFormat = 'h:mm A';
	const timeZoneFormat = 'z';
	const startFormat = dateFormat + ' ' + timeFormat;
	const timeZone = getTimeZone(tz);
	let endFormat = dateFormat + ' ' + timeFormat + ' ' + timeZoneFormat;

	if (
		moment.tz(start, timeZone).format(dateFormat) ===
		moment.tz(end, timeZone).format(dateFormat)
	) {
		endFormat = timeFormat + ' ' + timeZoneFormat;
	}

	return (
		moment.tz(start, timeZone).format(startFormat) +
		' to ' +
		moment.tz(end, timeZone).format(endFormat) +
		getUtcOffset(timeZone)
	);
};

const Edit = () => {
	const blockProps = useBlockProps();
	const [dateTimeStart, setDateTimeStart] = useState(defaultDateTimeStart);
	const [dateTimeEnd, setDateTimeEnd] = useState(defaultDateTimeEnd);
	const [timezone, setTimezone] = useState(getTimeZone());

	Listener({ setDateTimeEnd, setDateTimeStart, setTimezone });

	return (
		<div {...blockProps}>
			<Flex justify="normal" align="flex-start" gap="4">
				<FlexItem display="flex" className="gp-event-date__icon">
					<Icon icon="clock" />
				</FlexItem>
				<FlexItem>
					{displayDateTime(dateTimeStart, dateTimeEnd, timezone)}
				</FlexItem>
				<InspectorControls>
					<PanelBody>
						<h3>{__('Date & time', 'gatherpress')}</h3>
						<DateTimeStartPanel
							dateTimeStart={dateTimeStart}
							setDateTimeStart={setDateTimeStart}
						/>
						<DateTimeEndPanel
							dateTimeEnd={dateTimeEnd}
							setDateTimeEnd={setDateTimeEnd}
						/>
						<TimeZonePanel
							timezone={timezone}
							setTimezone={setTimezone}
						/>
					</PanelBody>
				</InspectorControls>
			</Flex>
		</div>
	);
};

export default Edit;
