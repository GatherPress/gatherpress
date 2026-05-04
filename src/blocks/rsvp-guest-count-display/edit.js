/**
 * WordPress dependencies
 */
import { _n, sprintf } from '@wordpress/i18n';
import { useBlockProps } from '@wordpress/block-editor';
import { useSelect } from '@wordpress/data';
import { useEffect } from '@wordpress/element';

/**
 * Internal dependencies
 */
import { getEditorDocument } from '../../helpers/editor';
import { DISABLED_FIELD_OPACITY, usePostTypeSupports } from '../../helpers/event';

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

	// Check if context post type supports RSVP.
	// `usePostTypeSupports` is reactive so the block re-renders the moment the
	// post-type definition resolves; the non-reactive variant would miss it
	// and leave the block permanently dimmed in Query Loops.
	const isEventContext = usePostTypeSupports( 'gatherpress-rsvp', context?.postType );

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
			// Find the first ancestor RSVP or RSVP Response block that
			// declared a postId override — replaces the original `for` loop
			// with `find` so the dispatch is one expression.
			const parentBlocks =
				select( 'core/block-editor' ).getBlockParents( clientId, true ) || [];
			const overrideParent = parentBlocks
				.map( ( id ) => select( 'core/block-editor' ).getBlock( id ) )
				.find(
					( parent ) =>
						( 'gatherpress/rsvp' === parent?.name ||
							'gatherpress/rsvp-response' === parent?.name ) &&
						parent.attributes?.postId,
				);

			let postIdOverride = overrideParent?.attributes?.postId ?? null;

			// Fall back to context postId only if it's an event.
			if ( ! postIdOverride && contextPostId && isEventContext ) {
				postIdOverride = contextPostId;
			}

			// If we have a Post ID override, fetch from that post.
			// Use context post type if available; it corresponds to the same post type as the override.
			if ( postIdOverride ) {
				const overridePostType =
					context?.postType ||
					select( 'core/editor' )?.getCurrentPostType();
				const post = select( 'core' ).getEntityRecord( 'postType', overridePostType, postIdOverride );
				return post?.meta?.gatherpress_max_guest_limit || 0;
			}

			// Otherwise check current post. Read supports through the `select`
			// parameter so this branch re-runs once the post-type definition
			// resolves — the imperative `isPostTypeSupporting` helper would
			// race the post-type cache and freeze this branch at 0.
			const currentPostType = select( 'core/editor' )?.getCurrentPostType();
			const currentSupportsRsvp = !! select( 'core' )
				.getPostType( currentPostType )?.supports?.[ 'gatherpress-rsvp' ];

			return currentSupportsRsvp
				? ( select( 'core/editor' ).getEditedPostAttribute( 'meta' )
					?.gatherpress_max_guest_limit || 0 )
				: 0;
		},
		[ clientId, contextPostId, isEventContext, context?.postType ],
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
			styleElement.textContent = `#block-${ clientId } { opacity: ${ DISABLED_FIELD_OPACITY } !important; }`;
		} else {
			styleElement.textContent = '';
		}

		// Cleanup on unmount.
		return () => {
			styleElement?.remove();
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
