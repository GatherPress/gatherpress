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
import { Fragment, createElement } from '@wordpress/element';

export default function Edit({ attributes, setAttributes }) {
	const blockProps = useBlockProps();

	const { theTime, message } = attributes;

	const onUpdateDate = (dateTime) => {
		var newDateTime = moment(dateTime).format('YYYY-MM-DD HH:mm');
		setAttributes({ theTime: newDateTime });
	};

	return (
		<div {...blockProps}>
			<Fragment>
				<InspectorControls>
					<PanelBody
						title="Some title for the date-tile panel"
						icon=""
						initialOpen={false}
					>
						<PanelRow>
							<DateTimePicker
								currentDate={theTime}
								onChange={(newTime) => onUpdateDate(newTime)}
								is12Hour={true}
							/>
						</PanelRow>
					</PanelBody>
				</InspectorControls>
			</Fragment>
			<p>{theTime}</p>
			<TextControl
				placeholder="Enter Text Here"
				value={attributes.message}
				onChange={(newMessage) => setAttributes({ message: newMessage })}
			/>
		</div>
	);

}
