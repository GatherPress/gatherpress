/**
 * WordPress dependencies.
 */
import {
	BlockContextProvider,
	InnerBlocks,
	useBlockProps,
} from '@wordpress/block-editor';

/**
 * Internal dependencies.
 */
import TEMPLATE from './template';

/**
 * Edit component for the GatherPress Online Event v2 block.
 *
 * Container block that holds an icon and online event link.
 * If a postId attribute is set (override), it provides that as context to children.
 *
 * @since 1.0.0
 *
 * @param {Object} props            Block props.
 * @param {Object} props.attributes Block attributes.
 *
 * @return {JSX.Element} The rendered React component.
 */
const Edit = ( { attributes } ) => {
	const blockProps = useBlockProps();
	const { postId } = attributes;

	const innerBlocksContent = (
		<InnerBlocks template={ TEMPLATE } templateLock={ false } />
	);

	return (
		<div { ...blockProps }>
			{ postId ? (
				<BlockContextProvider value={ { postId } }>
					{ innerBlocksContent }
				</BlockContextProvider>
			) : (
				innerBlocksContent
			) }
		</div>
	);
};

export default Edit;
