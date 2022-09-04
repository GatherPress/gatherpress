import { InspectorControls, useBlockProps } from '@wordpress/block-editor';

import { Fragment } from '@wordpress/element';

import {
	DateTimePicker,
	PanelBody,
	PanelRow,
	__experimentalGrid as Grid,
	__experimentalText as Text,
} from '@wordpress/components';


function Presentation_Grid() {
	return (
		<Grid columns={2}>
			<Text>{GatherPress.event_datetime.datetime_start}</Text>
			<Text>{GatherPress.event_datetime.datetime_end}</Text>
		</Grid>
	);
}
export default function Edit({ attributes, setAttributes }) {
	const blockProps = useBlockProps();

	const { theEndTime, theStartTime } = attributes;

	const onUpdateStartDate = (dateTime) => {
		var newDateTime = moment(dateTime).format('MM-DD-YYYY HH:mm');
	setAttributes({ theStartTime: newDateTime });
	};

	const onUpdateEndDate = (dateTime) => {
		var newDateTime = moment(dateTime).format('MM-DD-YYYY HH:mm');
		// minDate: moment().toISOString(),
		setAttributes({ theEndTime: newDateTime });
	};

	return (
		<div {...blockProps}>
			<Fragment>
				<InspectorControls>
					<PanelBody
						title="StartTime"
						initialOpen={true}
					>
						<PanelRow>
							<DateTimePicker
								currentDate={theStartTime}
								onChange={(newStartTime) => onUpdateStartDate(newStartTime)}
								is12Hour={true}
							/>
						</PanelRow>
					</PanelBody>
					<PanelBody
						title="EndTime"
						initialOpen={true}
					>
						<PanelRow>
							<DateTimePicker
								currentDate={theEndTime}
								onChange={(newEndTime) => onUpdateEndDate(newEndTime)}
								is12Hour={true}
							/>
						</PanelRow>
						<PanelRow>
							<p>GatherPress.event_datetime.datetime_end</p>
						</PanelRow>
					</PanelBody>
				</InspectorControls>
			</Fragment>
			<p>GatherPress.event_datetime.datetime_end</p>
			<p>{GatherPress.event_datetime.datetime_end}</p>
			<p>Click on the <span style={{ fontSize: "x-large" }}>&#9881;</span> in the upper right to select time and date</p>
			<Presentation_Grid />
		</div>
	);

}
