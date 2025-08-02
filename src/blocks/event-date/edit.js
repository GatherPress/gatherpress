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

/**
 * Similar to get_display_datetime method in class-event.php.
 *
 * @param {string} dateTimeStart
 * @param {string} dateTimeEnd
 * @param {string} timezone
 * @return {string} Displayed date.
 */
const displayDateTime = (dateTimeStart, dateTimeEnd, timezone) => {
	const dateFormat = convertPHPToMomentFormat(
		getFromGlobal('settings.dateFormat')
	);
	const timeFormat = convertPHPToMomentFormat(
		getFromGlobal('settings.timeFormat')
	);
	const timezoneFormat = getFromGlobal('settings.showTimezone') ? 'z' : '';
	const startFormat = dateFormat + ' ' + timeFormat;

	timezone = getTimezone(timezone);

	let endFormat = dateFormat + ' ' + timeFormat + ' ' + timezoneFormat;

	if (dateTimeStart && dateTimeEnd) {
		// Don't show the same day twice.
		if (
			moment.tz(dateTimeStart, timezone).format(dateFormat) ===
			moment.tz(dateTimeEnd, timezone).format(dateFormat)
		) {
			endFormat = timeFormat + ' ' + timezoneFormat;
		}

		return sprintf(
			/* translators: %1$s: datetime start, %2$s: datetime end, %3$s timezone. */
			__('%1$s to %2$s %3$s', 'gatherpress'),
			moment.tz(dateTimeStart, timezone).format(startFormat),
			moment.tz(dateTimeEnd, timezone).format(endFormat),
			getUtcOffset(timezone)
		);
	} else if (dateTimeStart) {
		return moment.tz(dateTimeStart, timezone).format(endFormat);
	} else if (dateTimeEnd) {
		return moment.tz(dateTimeEnd, timezone).format(endFormat);
	}

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
	const { textAlign, displayType } = attributes;
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
				timezone
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
						</VStack>
					</PanelBody>
				</InspectorControls>
			)}
		</div>
	);
};

export default Edit;
