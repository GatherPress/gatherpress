/**
 * WordPress dependencies.
 */
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, PanelRow } from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { store as coreStore } from '@wordpress/core-data';
import { store as editorStore } from '@wordpress/editor';

/**
 * Internal dependencies.
 */
import OnlineEvent from '../../components/OnlineEvent';
import OnlineEventLink from '../../components/OnlineEventLink';
import EditCover from '../../components/EditCover';
import { isGatherPressPostType } from '../../helpers/editor';
import { PT_EVENT, PT_VENUE } from '../../helpers/namespace';

/**
 * Edit component for the GatherPress Online Event v2 block.
 *
 * This component provides context-aware online event link fetching:
 * - In event context: displays event's online link
 * - In venue context: displays venue's online link, falls back to event's link if empty
 *
 * @since 1.0.0
 *
 * @param {Object}  props            - The component properties.
 * @param {Object}  props.context    - Block context from parent blocks.
 * @param {boolean} props.isSelected - Indicates whether the block is selected.
 *
 * @return {JSX.Element} The rendered React component.
 */
const Edit = ( { context, isSelected } ) => {
	const blockProps = useBlockProps();

	// Get the current contextual post ID.
	const contextPostId = context?.postId || 0;

	// Get the current editor post ID (for event context).
	const currentPostId = useSelect(
		( select ) => select( editorStore )?.getCurrentPostId(),
		[]
	);

	// Determine the online event link based on context.
	const onlineEventLink = useSelect(
		( select ) => {
			const { getEntityRecord } = select( coreStore );

			// If we have a context post ID, fetch that post's data.
			if ( contextPostId ) {
				// Try venue first.
				const venuePost = getEntityRecord( 'postType', PT_VENUE, contextPostId );
				if ( venuePost ) {
					const venueLink = venuePost.meta?.gatherpress_venue_online_link || '';

					// If venue has a link, use it.
					if ( venueLink ) {
						return venueLink;
					}

					// Otherwise, fallback to event link.
					const eventPost = getEntityRecord( 'postType', PT_EVENT, currentPostId );
					return eventPost?.meta?.gatherpress_online_event_link || '';
				}

				// Try event.
				const eventPost = getEntityRecord( 'postType', PT_EVENT, contextPostId );
				if ( eventPost ) {
					return eventPost.meta?.gatherpress_online_event_link || '';
				}
			}

			// Default to current editor post (event).
			const currentPost = getEntityRecord( 'postType', PT_EVENT, currentPostId );
			return currentPost?.meta?.gatherpress_online_event_link || '';
		},
		[ contextPostId, currentPostId ]
	);

	return (
		<>
			{ isGatherPressPostType() && (
				<InspectorControls>
					<PanelBody>
						<PanelRow>
							<OnlineEventLink />
						</PanelRow>
					</PanelBody>
				</InspectorControls>
			) }
			<div { ...blockProps }>
				<EditCover isSelected={ isSelected }>
					<OnlineEvent onlineEventLinkDefault={ onlineEventLink } />
				</EditCover>
			</div>
		</>
	);
};

export default Edit;
