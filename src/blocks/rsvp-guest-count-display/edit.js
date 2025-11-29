/**
 * WordPress dependencies.
 */
import { _n, sprintf } from '@wordpress/i18n';
import { useBlockProps } from '@wordpress/block-editor';
import { useSelect } from '@wordpress/data';
import { useEffect } from '@wordpress/element';

/**
 * Internal dependencies.
 */
import { getEditorDocument } from '../../helpers/editor';
import { DISABLED_FIELD_OPACITY } from '../../helpers/event';

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
	const contextPostId = context?.postId;
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
			// Check if parent RSVP or RSVP Response block has a postId override.
			const parentBlocks = select( 'core/block-editor' ).getBlockParents( clientId, true );
			let postIdOverride = null;

			if ( parentBlocks && 0 < parentBlocks.length ) {
				for ( const parentId of parentBlocks ) {
					const parent = select( 'core/block-editor' ).getBlock( parentId );
					if ( parent && 'gatherpress/rsvp' === parent.name && parent.attributes?.postId ) {
						postIdOverride = parent.attributes.postId;
						break;
					}
					if ( parent && 'gatherpress/rsvp-response' === parent.name && parent.attributes?.postId ) {
						postIdOverride = parent.attributes.postId;
						break;
					}
				}
			}

			// If no parent block postId, check context postId.
			if ( ! postIdOverride && contextPostId ) {
				postIdOverride = contextPostId;
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
		[ clientId, contextPostId ],
	);

	// Apply dimming via CSS when max attendance limit is 0.
	const shouldDim = 0 === maxAttendanceLimit;

	useEffect( () => {
		const editorDoc = getEditorDocument();
		const styleId = `gatherpress-guest-count-visibility-${ clientId }`;
		let styleElement = editorDoc.getElementById( styleId );

		if ( ! styleElement ) {
			styleElement = editorDoc.createElement( 'style' );
			styleElement.id = styleId;
			editorDoc.head.appendChild( styleElement );
		}

		if ( shouldDim ) {
			styleElement.textContent = `#block-${ clientId } { opacity: ${ DISABLED_FIELD_OPACITY } !important; pointer-events: none !important; }`;
		} else {
			styleElement.textContent = '';
		}

		// Cleanup on unmount.
		return () => {
			if ( styleElement && styleElement.parentNode ) {
				styleElement.parentNode.removeChild( styleElement );
			}
		};
	}, [ shouldDim, clientId ] );

	const blockProps = useBlockProps();

	const guestText = sprintf(
		/* translators: %d: Number of guests. Singular and plural forms are used for 1 guest and multiple guests, respectively. */
		_n( '+%d guest', '+%d guests', guestCount, 'gatherpress' ),
		guestCount,
	);

	return <div { ...blockProps }>{ guestText }</div>;
};

export default Edit;
