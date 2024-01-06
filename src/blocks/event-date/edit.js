/**
 * External dependencies.
 */
import moment from 'moment';

/**
 * WordPress dependencies.
 */
import { __, sprintf } from '@wordpress/i18n';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { Flex, FlexItem, Icon, PanelBody } from '@wordpress/components';
import { useState } from '@wordpress/element';

/**
 * Internal dependencies.
 */
import { Listener } from '../../helpers/broadcasting';
import {
	defaultDateTimeEnd,
	defaultDateTimeStart,
	getTimeZone,
	getUtcOffset,
} from '../../helpers/datetime';
import EditCover from '../../components/EditCover';
import DateTimeRange from '../../components/DateTimeRange';

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

	return sprintf(
		/* translators: %1$s: datetime start, %2$s: datetime end, %3$s timezone. */
		__('%1$s to %2$s %3$s'),
		moment.tz(start, timeZone).format(startFormat),
		moment.tz(end, timeZone).format(endFormat),
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
			<EditCover>
				<Flex justify="normal" align="center" gap="4">
					<FlexItem display="flex" className="gp-event-date__icon">
						<Icon icon="clock" />
					</FlexItem>
					<FlexItem>
						{displayDateTime(dateTimeStart, dateTimeEnd, timezone)}
					</FlexItem>
					<InspectorControls>
						<PanelBody>
							<DateTimeRange />
						</PanelBody>
					</InspectorControls>
				</Flex>
			</EditCover>
		</div>
	);
};

export default Edit;
