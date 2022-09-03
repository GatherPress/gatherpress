/**
 * WordPress components that create the necessary UI elements for the block
 *
 * @see https://developer.wordpress.org/block-editor/packages/packages-components/
 */
import {
	DateTimePicker,
	PanelBody,
	PanelRow,
	TextControl
} from '@wordpress/components';


import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { Fragment } from '@wordpress/element';

export default function Edit({ attributes, setAttributes }) {
	const blockProps = useBlockProps();

	const { theEndTime, theStartTime } = attributes;

	const onUpdateStartDate = (dateTime) => {
		var newDateTime = moment(dateTime).format('MM-DD-YYYY HH:mm');
	setAttributes({ theStartTime: newDateTime });
	};

	const onUpdateEndDate = (dateTime) => {
		var newDateTime = moment(dateTime).format('MM-DD-YYYY HH:mm');
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
					</PanelBody>
				</InspectorControls>
			</Fragment>
			<p>Click on the <span style={{fontSize:"x-large"}}>&#9881;</span> in the upper right to select time and date</p>
			<p>{theStartTime}</p>
			<p>{theEndTime}</p>
		</div>
	);

}
