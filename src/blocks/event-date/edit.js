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
import {
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalVStack as VStack,
	PanelBody,
	RadioControl,
	Spinner,
	TextControl,
} from '@wordpress/components';
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
import { getFromGlobal } from '../../helpers/globals';
import { isEventPostType } from '../../helpers/event';

const globalDateFormat = getFromGlobal('settings.dateFormat');
const globalTimeFormat = getFromGlobal('settings.timeFormat');
// const defaultFormat = `${globalDateFormat} ${globalTimeFormat}`;

/**
 * Similar to get_display_datetime method in class-event.php.
 *
 * @param {string} dateTimeStart
 * @param {string} dateTimeEnd
 * @param {string} timezone
 * @param {string} format
 * @return {string} Displayed date.
 */
const displayDateTime = (dateTimeStart, dateTimeEnd, timezone, format) => {
	// let [dateFormat, timeFormat] = '';

	// // if (format) {
	// // 	[dateFormat, timeFormat] = convertPHPToMomentFormat(format);
	// // } else {
	// // 	dateFormat = 
	// // }

	// dateFormat = convertPHPToMomentFormat(format ? format : globalDateFormat);
	// timeFormat = convertPHPToMomentFormat(format ? format : globalTimeFormat);

	// if (dateTimeStart && dateTimeEnd) {
	// 	return sprintf(
	// 		/* translators: %1$s: datetime start, %2$s: datetime end, %3$s timezone. */
	// 		__('%1$s to %2$s %3$s', 'gatherpress'),
	// 		moment.tz(dateTimeStart, timezone).format(startFormat),
	// 		moment.tz(dateTimeEnd, timezone).format(endFormat),
	// 		getUtcOffset(timezone)
	// 	)
	// }

	////////

	// MAYBE DO TWO SEPARATE DATE BLOCKS BY DEFAULT SO IT'S EASIER TO HANDLE
	// THE %date% "TO" %date" stuff with conditional timezone?

	if (dateTimeStart && dateTimeEnd) {
		format = convertPHPToMomentFormat(format);

		return format
			? sprintf(
					/* translators: %1$s: datetime start, %2$s: datetime end.. */
					__('%1$s to %2$s', 'gatherpress'),
					moment.tz(dateTimeStart, timezone).format(format),
					moment.tz(dateTimeEnd, timezone).format(format)
				)
			: sprintf(
					/* translators: %1$s: datetime start, %2$s: datetime end, %3$s timezone. */
					__('%1$s to %2$s %3$s', 'gatherpress'),
					moment.tz(dateTimeStart, timezone).format(),
					moment.tz(dateTimeEnd, timezone).format(endFormat),
					getUtcOffset(timezone)
				);
	}

	///////

	// // We want to get the custom format before these!!
	// const dateFormat = convertPHPToMomentFormat(globalDateFormat);
	// const timeFormat = convertPHPToMomentFormat(globalTimeFormat);
	// const timezoneFormat = getFromGlobal('settings.showTimezone') ? 'z' : '';
	// const startFormat = dateFormat + ' ' + timeFormat;

	// timezone = getTimezone(timezone);

	// let endFormat = dateFormat + ' ' + timeFormat + ' ' + timezoneFormat;

	// if (dateTimeStart && dateTimeEnd) {
	// 	// Don't show the same day twice.
	// 	if (
	// 		moment.tz(dateTimeStart, timezone).format(dateFormat) ===
	// 		moment.tz(dateTimeEnd, timezone).format(dateFormat)
	// 	) {
	// 		endFormat = timeFormat + ' ' + timezoneFormat;
	// 	}

	// 	return sprintf(
	// 		/* translators: %1$s: datetime start, %2$s: datetime end, %3$s timezone. */
	// 		__('%1$s to %2$s %3$s', 'gatherpress'),
	// 		moment.tz(dateTimeStart, timezone).format(startFormat),
	// 		moment.tz(dateTimeEnd, timezone).format(endFormat),
	// 		getUtcOffset(timezone)
	// 	);
	// } else if (dateTimeStart) {
	// 	return moment.tz(dateTimeStart, timezone).format(endFormat);
	// } else if (dateTimeEnd) {
	// 	return moment.tz(dateTimeEnd, timezone).format(endFormat);
	// }

	return '';
};

/**
 * Edit component for the GatherPress Event Date block.
 *
 * This component represents the editable view of the GatherPress Event Date block
 * in the WordPress block editor. It manages the state of the start and end date,
 * time, and timezone for the block, and renders the user interface accordingly.
 * The component includes a BlockControls toolbar, displays the formatted date and
 * time, and provides controls for editing the date and time range via the
 * DateTimeRange component within InspectorControls.
 *
 * @since 1.0.0
 *
 * @param {Object}   root0               The props passed to the Edit component.
 * @param {Object}   root0.attributes    The block attributes.
 * @param {Object}   root0.context       Block context data containing postId and event info.
 * @param {Function} root0.setAttributes Function to set block attributes.
 *
 * @return {JSX.Element} The rendered Edit component for the GatherPress Event Date block.
 *
 * @see {@link DateTimeRange} - Component for editing date and time range.
 * @see {@link AlignmentToolbar} - Toolbar for text alignment control.
 * @see {@link useBlockProps} - Custom hook for block props.
 * @see {@link displayDateTime} - Function for formatting and displaying date and time.
 */
const Edit = ({ attributes, setAttributes, context }) => {
	const { textAlign, displayType, displayFormat } = attributes;
	const blockProps = useBlockProps({
		className: clsx({
			[`has-text-align-${textAlign}`]: textAlign,
		}),
	});
	const postId = attributes?.postId ?? context?.postId ?? null;

	const { dateTimeStart, dateTimeEnd, timezone } = useSelect(
		(select) => {
			if (isEventPostType()) {
				return {
					dateTimeStart: select(
						'gatherpress/datetime'
					).getDateTimeStart(),
					dateTimeEnd: select(
						'gatherpress/datetime'
					).getDateTimeEnd(),
					timezone: select('gatherpress/datetime').getTimezone(),
				};
			}

			const meta = select('core').getEntityRecord(
				'postType',
				'gatherpress_event',
				postId
			)?.meta;

			return {
				dateTimeStart: meta?.gatherpress_datetime_start,
				dateTimeEnd: meta?.gatherpress_datetime_end,
				timezone: meta?.gatherpress_timezone,
			};
		},
		[postId]
	);

	if (!dateTimeStart || !dateTimeEnd || !timezone) {
		return (
			<div {...blockProps}>
				<Spinner />
			</div>
		);
	}

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
			{displayDateTime(
				['start', 'both'].includes(displayType) ? dateTimeStart : null,
				['end', 'both'].includes(displayType) ? dateTimeEnd : null,
				timezone,
				displayFormat
			)}
			{isEventPostType() && (
				<InspectorControls>
					<PanelBody>
						<VStack spacing={4}>
							<DateTimeRange />
							<RadioControl
								label={__('Display', 'gatherpress')}
								selected={displayType}
								options={[
									{
										label: __(
											'Start and end date',
											'getherpress'
										),
										value: 'both',
									},
									{
										label: __(
											'Start date only',
											'gatherpress'
										),
										value: 'start',
									},
									{
										label: __(
											'End date only',
											'gatherpress'
										),
										value: 'end',
									},
								]}
								onChange={(value) =>
									setAttributes({ displayType: value })
								}
							/>
							<TextControl
								label={__('Format', 'gatherpress')}
								value={displayFormat}
								placeholder={`${globalDateFormat} ${globalTimeFormat}`}
								help={
									<a
										href="https://wordpress.org/documentation/article/customize-date-and-time-format/"
										target="_blank"
										rel="noreferrer"
									>
										{__(
											'Date/time formatting documentation',
											'gatherpress'
										)}
									</a>
								}
								onChange={(value) =>
									setAttributes({ displayFormat: value })
								}
							/>
						</VStack>
					</PanelBody>
				</InspectorControls>
			)}
		</div>
	);
};

export default Edit;
