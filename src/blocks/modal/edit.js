/**
 * WordPress dependencies.
 */
import {
	useBlockProps,
	InnerBlocks,
	InspectorControls,
	store as blockEditorStore,
} from '@wordpress/block-editor';
import { useSelect } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import { PanelBody, RangeControl } from '@wordpress/components';

const Edit = ({ attributes, setAttributes, clientId, isSelected }) => {
	const hasSelectedInnerBlock = useSelect(
		(select) =>
			select(blockEditorStore).hasSelectedInnerBlock(clientId, true),
		[clientId]
	);
	const blockProps = useBlockProps({
		style: {
			display: isSelected || hasSelectedInnerBlock ? 'block' : 'none',
			maxWidth: 'none',
		},
	});
	const { zIndex } = attributes;

	const TEMPLATE = [['gatherpress/modal-content', {}]];

	return (
		<>
			<InspectorControls>
				<PanelBody title={__('Modal Settings', 'gatherpress')}>
					<RangeControl
						label={__('Z-Index', 'gatherpress')}
						value={zIndex}
						onChange={(newValue) =>
							setAttributes({ zIndex: newValue })
						}
						min={0}
						max={9999}
						step={1}
						help={__(
							'Set the layering position of the modal.',
							'gatherpress'
						)}
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
