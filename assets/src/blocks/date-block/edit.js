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

/**
 * React hook that is used to mark the block wrapper element.
 * It provides all the necessary props like the class name.
 *
 * @see https://developer.wordpress.org/block-editor/packages/packages-block-editor/#useBlockProps
 */
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { Fragment, createElement } from '@wordpress/element';

export default function Edit({ attributes, setAttributes }) {
	const blockProps = useBlockProps();

	const { datetime } = attributes;

	const onUpdateDate = (dateTime) => {
		var newDateTime = moment(dateTime).format('YYYY-MM-DD HH:mm');
		setAttributes({ datetime: newDateTime });
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
								currentDate={datetime}
								onChange={(val) => onUpdateDate(val)}
								is12Hour={true}
							/>
						</PanelRow>
					</PanelBody>
				</InspectorControls>
			</Fragment>
			<p>{datetime}</p>
			<TextControl
				placeholder="Enter Text Here"
				value={attributes.message}
				onChange={(val) => setAttributes({ message: val })}
			/>
		</div>
	);

}
