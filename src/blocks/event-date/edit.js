/**
 * External dependencies.
 */
import moment from 'moment';

/**
 * WordPress dependencies.
 */
import { useBlockProps } from '@wordpress/block-editor';
import { Flex, FlexItem, Icon } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { timeZone, utcOffset } from '../../helpers/datetime';

/**
 * Internal dependencies.
 */
import { Listener } from '../../helpers/broadcasting';

/**
 * Similar to get_display_datetime method in class-event.php.
 *
 * @param {string} start
 * @param {string} end
 * @return {string} Displayed date.
 */
const displayDateTime = (start, end) => {
	const dateFormat = 'dddd, MMMM D, YYYY';
	const timeFormat = 'h:mm A';
	const timeZoneFormat = 'z';
	// eslint-disable-next-line no-undef
	const startFormat = dateFormat + ' ' + timeFormat;
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
		utcOffset
	);
};

const Edit = () => {
	const blockProps = useBlockProps();
	const [dateTimeStart, setDateTimeStart] = useState(
		// eslint-disable-next-line no-undef
		GatherPress.event_datetime.datetime_start
	);
	const [dateTimeEnd, setDateTimeEnd] = useState(
		// eslint-disable-next-line no-undef
		GatherPress.event_datetime.datetime_end
	);

	Listener({ setDateTimeEnd, setDateTimeStart });

	return (
		<div {...blockProps}>
			<Flex justify="normal" align="flex-start" gap="4">
				<FlexItem display="flex" className="gp-event-date__icon">
					<Icon icon="clock" />
				</FlexItem>
				<FlexItem>
					{displayDateTime(dateTimeStart, dateTimeEnd)}
				</FlexItem>
			</Flex>
		</div>
	);
};

export default Edit;
