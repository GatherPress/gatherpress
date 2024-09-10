/**
 * External dependencies.
 */
import moment from 'moment';
import clsx from 'clsx';

/**
 * WordPress dependencies.
 */
import { __, sprintf } from '@wordpress/i18n';
import {
	AlignmentToolbar,
	BlockControls,
	InspectorControls,
	useBlockProps,
} from '@wordpress/block-editor';
import { PanelBody } from '@wordpress/components';
import { useSelect } from '@wordpress/data';

/**
 * Internal dependencies.
 */
import {
	convertPHPToMomentFormat,
	getTimezone,
	getUtcOffset,
} from '../../helpers/datetime';
import DateTimeRange from '../../components/DateTimeRange';
import { getFromGlobal, isSinglePostInEditor } from '../../helpers/globals';

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
		getFromGlobal('settings.dateFormat')
	);
	const timeFormat = convertPHPToMomentFormat(
		getFromGlobal('settings.timeFormat')
	);
	const timeZoneFormat = getFromGlobal('settings.showTimezone') ? 'z' : '';
	const startFormat = dateFormat + ' ' + timeFormat;
	const { dateTimeStart, dateTimeEnd, timezone } = useSelect(
		(select) => ({
			dateTimeStart: select('gatherpress/datetime').getDateTimeStart(),
			dateTimeEnd: select('gatherpress/datetime').getDateTimeEnd(),
			timezone: select('gatherpress/datetime').getTimezone(),
		}),
		[]
	);
	const timeZone = getTimezone(timezone);

	let endFormat = dateFormat + ' ' + timeFormat + ' ' + timeZoneFormat;

	if (
		moment.tz(dateTimeStart, timeZone).format(dateFormat) ===
		moment.tz(dateTimeEnd, timeZone).format(dateFormat)
	) {
		endFormat = timeFormat + ' ' + timeZoneFormat;
	}

	return sprintf(
		/* translators: %1$s: datetime start, %2$s: datetime end, %3$s timezone. */
		__('%1$s to %2$s %3$s', 'gatherpress'),
		moment.tz(dateTimeStart, timeZone).format(startFormat),
		moment.tz(dateTimeEnd, timeZone).format(endFormat),
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
 * @param  root0
 * @param  root0.attributes
 * @param  root0.attributes.textAlign
 * @param  root0.attributes.format
 * @param  root0.attributes.isLink
 * @param  root0.attributes.displayType
 * @param  root0.setAttributes
 * @since 1.0.0
 *
 * @return {JSX.Element} The rendered Edit component for the GatherPress Event Date block.
 *
 * @see {@link DateTimeRange} - Component for editing date and time range.
 * @see {@link EditCover} - Component for displaying a cover over the block.
 * @see {@link useBlockProps} - Custom hook for block props.
 * @see {@link displayDateTime} - Function for formatting and displaying date and time.
 */
const Edit = ({
	attributes: { textAlign, format, isLink, displayType },
	setAttributes,
}) => {
	const blockProps = useBlockProps({
		className: clsx({
			[`has-text-align-${textAlign}`]: textAlign,
		}),
	});

	const { dateTimeStart, dateTimeEnd, timezone } = useSelect(
		(select) => ({
			dateTimeStart: select('gatherpress/datetime').getDateTimeStart(),
			dateTimeEnd: select('gatherpress/datetime').getDateTimeEnd(),
			timezone: select('gatherpress/datetime').getTimezone(),
		}),
		[]
	);

	return (
		<div {...blockProps}>
			<BlockControls>
				<AlignmentToolbar
					value={textAlign}
					onChange={(newAlign) =>
						setAttributes({ textAlign: newAlign })
					}
				/>
			</BlockControls>
			{displayDateTime(dateTimeStart, dateTimeEnd, timezone)}
			{isSinglePostInEditor() && (
				<InspectorControls>
					<PanelBody>
						<DateTimeRange />
					</PanelBody>
				</InspectorControls>
			)}
		</div>
	);
};

export default Edit;
