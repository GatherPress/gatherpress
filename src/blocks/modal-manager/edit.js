/**
 * WordPress dependencies.
 */
import { useBlockProps, InnerBlocks } from '@wordpress/block-editor';

const Edit = () => {
	const blockProps = useBlockProps();

	const TEMPLATE = [
		['gatherpress/modal', {}, [['gatherpress/modal-content', {}]]],
	];

	return (
		<div {...blockProps}>
			<InnerBlocks template={TEMPLATE} />
		</div>
	);
};

export default Edit;
