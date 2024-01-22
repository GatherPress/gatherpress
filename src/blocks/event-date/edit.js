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
	convertPHPToMomentFormat,
	defaultDateTimeEnd,
	defaultDateTimeStart,
	getTimeZone,
	getUtcOffset,
} from '../../helpers/datetime';
import EditCover from '../../components/EditCover';
import DateTimeRange from '../../components/DateTimeRange';
import { getFromGlobal } from '../../helpers/globals';

/**
 * Similar to get_display_datetime method in class-event.php.
 *
 * @param {string} start
 * @param {string} end
 * @param {string} tz
 * @return {string} Displayed date.
 */
const displayDateTime = (start, end, tz) => {
	const dateFormat = convertPHPToMomentFormat(
		getFromGlobal('settings.date_format')
	);
	const timeFormat = convertPHPToMomentFormat(
		getFromGlobal('settings.time_format')
	);
	const timeZoneFormat = getFromGlobal('settings.show_timezone') ? 'z' : '';
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

/**
 * Edit component for the GatherPress Event Date block.
 *
 * This component represents the editable view of the GatherPress Event Date block
 * in the WordPress block editor. It manages the state of date, time, and timezone
 * for the block and renders the user interface accordingly. The component includes
 * an icon, displays the formatted date and time, and provides controls to edit the
 * date and time range via the DateTimeRange component in the InspectorControls.
 *
 * @since 1.0.0
 *
 * @return {JSX.Element} The rendered Edit component for the GatherPress Event Date block.
 *
 * @see {@link DateTimeRange} - Component for editing date and time range.
 * @see {@link EditCover} - Component for displaying a cover over the block.
 * @see {@link useBlockProps} - Custom hook for block props.
 * @see {@link displayDateTime} - Function for formatting and displaying date and time.
 * @see {@link Listener} - Function for adding event listeners.
 */
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
