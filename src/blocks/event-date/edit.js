/**
 * External dependencies.
 */
import moment from 'moment';

/**
 * WordPress dependencies.
 */
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import {
	CheckboxControl,
	Flex,
	FlexItem,
	Icon,
	PanelBody,
	PanelRow,
} from '@wordpress/components';

import { useState } from '@wordpress/element';
import { useSelect } from '@wordpress/data';
import { useEntityProp } from '@wordpress/core-data';

/**
 * Internal dependencies.
 */
import { Listener } from '../../helpers/broadcasting';

const DateSett44ingCheckbox = (props) => {
	const {
		attributes: { show_date_as_event },
		setAttributes,
	} = props;

	const [isChecked, setChecked] = useState(show_date_as_event);
	// const postType = useSelect(
	// 	(select) => select('core/editor').getCurrentPostType(),
	// 	[]
	// );

	// const [meta, setMeta] = useEntityProp('postType', postType, 'meta');

	// let metaFieldValue = meta.show_date_as_event_date;
	// if ( typeof metaFieldValue === 'undefined' ) {
	// 	metaFieldValue = true;
	// }

	const updateMetaValue = (newValue) => {
		setChecked(newValue);
		setAttributes({ show_date_as_event: newValue });
		// setMeta( { ...meta, show_date_as_event_date: newValue } );
	};

	return (
		<CheckboxControl
			label="Use Event Date"
			help="Do you want to show the post date or event date?"
			checked={isChecked}
			onChange={updateMetaValue}
		/>
	);
};

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
	const timeZone = GatherPress.event_datetime.timezone;
	const startFormat = dateFormat + ' ' + timeFormat;
	let endFormat = dateFormat + ' ' + timeFormat + ' ' + timeZoneFormat;

	if (moment(start).format(dateFormat) === moment(end).format(dateFormat)) {
		endFormat = timeFormat + ' ' + timeZoneFormat;
	}

	return moment(start).format(startFormat) + ' to ' + moment.tz(end, timeZone).format(endFormat);
};

const Edit = (props) => {
	const {
		attributes: { show_date_as_event },
		setAttributes,
	} = props;
	const blockProps = useBlockProps();
	// eslint-disable-next-line no-undef
	const [dateTimeStart, setDateTimeStart] = useState(GatherPress.event_datetime.datetime_start);
	// eslint-disable-next-line no-undef
	const [dateTimeEnd, setDateTimeEnd] = useState(GatherPress.event_datetime.datetime_end);

	Listener({ setDateTimeEnd, setDateTimeStart });

	const DateSettingCheckbox = () => {
		const [isChecked, setChecked] = useState(show_date_as_event);
		const postType = useSelect(
			(select) => select('core/editor').getCurrentPostType(),
			[]
		);

		const [meta, setMeta] = useEntityProp('postType', postType, 'meta');

		let metaFieldValue = meta.show_date_as_event_date;
		if ( typeof metaFieldValue === 'undefined' ) {
			metaFieldValue = true;
		}

		const updateMetaValue = (newValue) => {
			setChecked(newValue);
			setAttributes({ show_date_as_event: newValue });
			setMeta( { ...meta, show_date_as_event_date: newValue } );
		};

		return (
			<>
				<PanelRow>
					<CheckboxControl
						label="Use Event Date"
						help="Do you want to show the post date or event date?"
						checked={isChecked}
						onChange={updateMetaValue}
					/>
				</PanelRow>
			</>
		);
	};

	return (
		<>
			<InspectorControls>
				<PanelBody title="Date Settings" icon="calendar" initialOpen={true}>
					<DateSettingCheckbox />
				</PanelBody>
			</InspectorControls>
			<div { ...blockProps }>
				<Flex justify="normal" align="flex-start" gap="4">
					<FlexItem display="flex" className="gp-event-date__icon">
						<Icon icon="clock" />
					</FlexItem>
					<FlexItem>
						{ displayDateTime( dateTimeStart, dateTimeEnd ) }
					</FlexItem>
				</Flex>
			</div>
		</>
	);
};

export default Edit;
