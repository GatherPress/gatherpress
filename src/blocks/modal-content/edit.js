/**
 * WordPress dependencies.
 */
import {
	useBlockProps,
	InnerBlocks,
	InspectorControls,
} from '@wordpress/block-editor';
import { PanelBody, RangeControl } from '@wordpress/components';

const Edit = ({ attributes, setAttributes }) => {
	const { style = {} } = attributes;
	const { dimensions = {}, color = {} } = style;
	const width = parseInt(dimensions.width || '320px', 10);

	const blockProps = useBlockProps({
		style: {
			...color,
			...dimensions,
		},
	});
	const TEMPLATE = [['core/paragraph', {}]];

	return (
		<>
			<InspectorControls>
				<PanelBody title="Width Settings">
					<RangeControl
						label="Width"
						value={width}
						onChange={(value) =>
							setAttributes({
								style: {
									...style,
									dimensions: {
										...dimensions,
										width: `${value}px`,
									},
								},
							})
						}
						min={320}
						max={800}
					/>
				</PanelBody>
			</InspectorControls>
			<div {...blockProps}>
				<InnerBlocks template={TEMPLATE} />
			</div>
		</>
	);
};
export default Edit;
