/**
 * WordPress dependencies.
 */
import { useBlockProps, InnerBlocks } from '@wordpress/block-editor';

const Save = ( { attributes } ) => {
	const blockProps = useBlockProps.save();
	const { label } = attributes;

	return (
		<div { ...blockProps }>
			{ /* eslint-disable-next-line jsx-a11y/anchor-is-valid */ }
			<a href="#" className="wp-block-gatherpress-dropdown__trigger">
				{ label }
			</a>
			<div className="wp-block-gatherpress-dropdown__menu">
				<InnerBlocks.Content />
			</div>
		</div>
	);
};

export default Save;
