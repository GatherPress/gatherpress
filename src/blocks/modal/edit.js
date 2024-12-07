/**
 * WordPress dependencies.
 */
import {
	useBlockProps,
	InnerBlocks,
	store as blockEditorStore,
} from '@wordpress/block-editor';
import { useSelect } from '@wordpress/data';

const Edit = ({ clientId, isSelected }) => {
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

	const TEMPLATE = [['gatherpress/modal-content', {}]];

	return (
		<div {...blockProps}>
			<InnerBlocks template={TEMPLATE} />
		</div>
	);
};

export default Edit;
