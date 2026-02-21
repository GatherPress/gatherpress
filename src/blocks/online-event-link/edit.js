/**
 * WordPress dependencies.
 */
import { __ } from '@wordpress/i18n';
import { RichText, useBlockProps } from '@wordpress/block-editor';
import { useSelect } from '@wordpress/data';
import { store as coreStore } from '@wordpress/core-data';
import { store as editorStore } from '@wordpress/editor';

/**
 * Internal dependencies.
 */
import { CPT_EVENT } from '../../helpers/namespace';

/**
 * Edit component for the GatherPress Online Event v2 block.
 *
 * Provides inline editing of online event link with context-aware fetching:
 * - In event context: displays event's online link URL (frontend is RSVP-aware)
 * - In venue context: displays venue's online link URL
 * - Link text is editable with default "Online event"
 *
 * Note: On the frontend, event links are only shown to users who have RSVP'd
 * as "attending" and the event hasn't passed. Venue links are always shown.
 *
 * @since 1.0.0
 *
 * @param {Object}   props               - Component properties.
 * @param {Object}   props.context       - Block context.
 * @param {Object}   props.attributes    - Block attributes.
 * @param {Function} props.setAttributes - Function to update block attributes.
 *
 * @return {JSX.Element} The rendered React component.
 */
const Edit = ( { context, attributes, setAttributes } ) => {
	const blockProps = useBlockProps();
	const { linkText } = attributes;

	// Get the current contextual post ID and type.
	const contextPostId = context?.postId || 0;

	// Get current editor post info.
	const { currentPostId, currentPostType } = useSelect(
		( select ) => ( {
			currentPostId: select( editorStore )?.getCurrentPostId(),
			currentPostType: select( editorStore )?.getCurrentPostType(),
		} ),
		[]
	);

	// Determine which post and meta field to use (only events have online links).
	const { postId, postType, metaKey } = useSelect(
		( select ) => {
			// If we have context, check if it's an event.
			if ( contextPostId ) {
				const { getEntityRecord } = select( coreStore );
				const contextPost = getEntityRecord( 'postType', 'any', contextPostId );
				const contextType = contextPost?.type;

				if ( CPT_EVENT === contextType ) {
					return {
						postId: contextPostId,
						postType: CPT_EVENT,
						metaKey: 'gatherpress_online_event_link',
					};
				}
			}

			// Fall back to current editor post if it's an event.
			if ( CPT_EVENT === currentPostType ) {
				return {
					postId: currentPostId,
					postType: currentPostType,
					metaKey: 'gatherpress_online_event_link',
				};
			}

			// Not an event context - no online link.
			return {
				postId: null,
				postType: null,
				metaKey: null,
			};
		},
		[ contextPostId, currentPostId, currentPostType ]
	);

	// Get the URL from the meta field.
	const linkUrl = useSelect(
		( select ) => {
			if ( ! postId || ! metaKey ) {
				return '';
			}

			const { getEditedEntityRecord } = select( coreStore );
			const post = getEditedEntityRecord( 'postType', postType, postId );

			return post?.meta?.[ metaKey ] || '';
		},
		[ postId, postType, metaKey ]
	);

	// Update the link text (stored in block attributes).
	const updateLinkText = ( newValue ) => {
		setAttributes( { linkText: newValue } );
	};

	// Use default text if linkText is empty.
	const displayText = linkText || __( 'Online event', 'gatherpress' );

	// Conditionally set tag and props based on whether we have a URL.
	const hasUrl = !! linkUrl;
	const tagProps = hasUrl
		? {
			tagName: 'a',
			href: linkUrl,
			target: '_blank',
			rel: 'noopener noreferrer',
		}
		: {
			tagName: 'span',
		};

	return (
		<div { ...blockProps }>
			<RichText
				{ ...tagProps }
				value={ displayText }
				onChange={ updateLinkText }
				placeholder={ __( 'Online event', 'gatherpress' ) }
				allowedFormats={ [ 'gatherpress/tooltip' ] }
				className="gatherpress-online-event__link"
			/>
		</div>
	);
};

export default Edit;
