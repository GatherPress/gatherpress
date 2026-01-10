/**
 * WordPress dependencies.
 */
import { useBlockProps, InnerBlocks } from '@wordpress/block-editor';

/**
 * Internal dependencies.
 */
import TEMPLATE from './template';
import { hasValidEventId } from '../../helpers/event';
import { isInFSETemplate } from '../../helpers/editor';

const Edit = ( { attributes } ) => {
	const { postId } = attributes;

	// Check if block has a valid event connection.
	const isValidEvent = hasValidEventId( postId );

	const blockProps = useBlockProps( {
		style: {
			opacity: ( isInFSETemplate() || isValidEvent ) ? 1 : 0.3,
		},
	} );

	return (
		<div { ...blockProps }>
			<InnerBlocks template={ TEMPLATE } />
		</div>
	);
};

export default Edit;
