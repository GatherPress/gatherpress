import { useBlockProps, InnerBlocks } from '@wordpress/block-editor';
import TEMPLATE from './template';
const Edit = () => {
	const blockProps = useBlockProps();

	return (
		<div {...blockProps}>
			<InnerBlocks template={TEMPLATE} />
		</div>
	);
};

export default Edit;
