/**
 * WordPress dependencies.
 */
import { _n, sprintf } from '@wordpress/i18n';
import { useBlockProps } from '@wordpress/block-editor';
import { useSelect } from '@wordpress/data';

/**
 * Edit function for the RSVP Guest Count Display Block.
 *
 * This function defines the edit interface for the RSVP Guest Count Display Block,
 * rendering the block's UI within the editor. It utilizes block context for dynamic data.
 *
 * @since 1.0.0
 *
 * @param {Object} root0          - The root properties object.
 * @param {Object} root0.context  - The block's context, providing dynamic data.
 * @param {string} root0.clientId - The unique ID of the block instance.
 *
 * @return {JSX.Element} The rendered edit interface for the block.
 */
const Edit = ( { context, clientId } ) => {
	const { commentId } = context;
	const rsvpResponses = context?.[ 'gatherpress/rsvpResponses' ] ?? null;

	// Example guest count.
	let guestCount = 1;

	if ( commentId && rsvpResponses ) {
		const matchedResponse = rsvpResponses.attending.records.find(
			( response ) => response.commentId === commentId,
		);

		if ( matchedResponse ) {
			guestCount = matchedResponse.guests;
		}
	}

	// Get max attendance limit from meta - check Post ID override first.
	const maxAttendanceLimit = useSelect(
		( select ) => {
			// Check if parent RSVP block has a postId override.
			const parentBlocks = select( 'core/block-editor' ).getBlockParents( clientId, true );
			let postIdOverride = null;

			if ( parentBlocks && parentBlocks.length > 0 ) {
				for ( const parentId of parentBlocks ) {
					const parent = select( 'core/block-editor' ).getBlock( parentId );
					if ( parent && parent.name === 'gatherpress/rsvp' && parent.attributes?.postId ) {
						postIdOverride = parent.attributes.postId;
						break;
					}
				}
			}

			// If we have a Post ID override, fetch from that post.
			if ( postIdOverride ) {
				const post = select( 'core' ).getEntityRecord( 'postType', 'gatherpress_event', postIdOverride );
				return post?.meta?.gatherpress_max_guest_limit || 0;
			}

			// Otherwise check current post.
			const currentPostType = select( 'core/editor' )?.getCurrentPostType();
			const isCurrentPostEvent = 'gatherpress_event' === currentPostType;

			if ( isCurrentPostEvent ) {
				return select( 'core/editor' ).getEditedPostAttribute( 'meta' )
					?.gatherpress_max_guest_limit || 0;
			}

			return 0;
		},
		[ clientId ],
	);

	// Add the no-render attribute when max attendance limit is 0 and no comment context.
	const shouldNoRender = 0 === maxAttendanceLimit && ! commentId;

	const blockProps = useBlockProps( {
		'data-gatherpress-no-render': shouldNoRender ? 'true' : undefined,
	} );

	const guestText = sprintf(
		/* translators: %d: Number of guests. Singular and plural forms are used for 1 guest and multiple guests, respectively. */
		_n( '+%d guest', '+%d guests', guestCount, 'gatherpress' ),
		guestCount,
	);

	return <div { ...blockProps }>{ guestText }</div>;
};

export default Edit;
