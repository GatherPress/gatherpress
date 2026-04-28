/**
 * WordPress dependencies.
 */
import { useBlockProps, InnerBlocks } from '@wordpress/block-editor';

/**
 * Internal dependencies.
 */
import TEMPLATE from './template';
import { hasValidEventId, usePostTypeSupports, DISABLED_FIELD_OPACITY } from '../../helpers/event';
import { isInFSETemplate } from '../../helpers/editor';

const Edit = ( { attributes, context } ) => {
	// Check if we're inside a query loop and if context is an event.
	// `usePostTypeSupports` is reactive so the block re-renders the moment the
	// post-type definition resolves; the non-reactive variant would miss it
	// and leave the block permanently dimmed in Query Loops.
	const isDescendentOfQueryLoop = Number.isFinite( context?.queryId );
	const isEventContext = usePostTypeSupports( 'gatherpress-event-date', context?.postType );

	// Only use postId if context is an event or have an explicit override.
	const postId =
		( attributes?.postId || null ) ??
		( ( isDescendentOfQueryLoop || isEventContext ) ? context?.postId : null ) ??
		null;

	// Check if block has a valid event connection.
	// Only check if we're in an event context.
	const isValidEvent =
		( isDescendentOfQueryLoop || isEventContext ) &&
		hasValidEventId( postId, context?.postType );

	const blockProps = useBlockProps( {
		style: {
			opacity: ( isInFSETemplate() || isValidEvent ) ? 1 : DISABLED_FIELD_OPACITY,
		},
	} );

	return (
		<div { ...blockProps }>
			<InnerBlocks template={ TEMPLATE } />
		</div>
	);
};

export default Edit;
