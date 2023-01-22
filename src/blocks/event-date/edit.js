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
import { timeZone, utcOffset } from '../../helpers/datetime';

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
					</PanelBody>
				</InspectorControls>
			</Flex>
		</div>
	);
};

export default Edit;
