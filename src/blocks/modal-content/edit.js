/**
 * WordPress dependencies.
 */
import { useBlockProps, InnerBlocks } from '@wordpress/block-editor';

const Edit = () => {
	const blockProps = useBlockProps();
	const TEMPLATE = [
		[
			'core/paragraph',
			{},
		],
	];

	return (
	<div {...blockProps}>
		<InnerBlocks template={TEMPLATE} />
	</div>
	);
};

export default Edit;
