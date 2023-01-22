/**
 * External dependencies.
 */
import moment from 'moment';

/**
 * WordPress dependencies.
 */
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { Flex, FlexItem, Icon, PanelBody } from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies.
 */
import { Listener } from '../../helpers/broadcasting';
import DateTimeStartPanel from '../../components/DateTimeStartPanel';
import DateTimeEndPanel from '../../components/DateTimeEndPanel';
import { timeZone, utcOffset } from '../../helpers/datetime';
import { getFromGlobal } from '../../helpers/misc';

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

const Edit = ({ attributes, setAttributes }) => {
	const blockProps = useBlockProps();

	const { eventStart, eventEnd } = attributes;

	const [dateTimeStart, setDateTimeStart] = useState(
		getFromGlobal('event_datetime.datetime_start')
	);
	const [dateTimeEnd, setDateTimeEnd] = useState(
		getFromGlobal('event_datetime.datetime_end')
	);

	Listener({ setDateTimeEnd, setDateTimeStart });

	useEffect(() => {
		setAttributes({
			eventStart: dateTimeStart,
			eventEnd: dateTimeEnd,
		});
	});
	return (
		<div {...blockProps}>
			<Flex justify="normal" align="flex-start" gap="4">
				<FlexItem display="flex" className="gp-event-date__icon">
					<Icon icon="clock" />
				</FlexItem>
				<FlexItem>
					{displayDateTime(eventStart, eventEnd)}
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
			{ JSON.stringify( attributes) }
		</div>
	);
};

export default Edit;
