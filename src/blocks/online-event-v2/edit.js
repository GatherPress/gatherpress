/**
 * WordPress dependencies.
 */
import {
	BlockContextProvider,
	InnerBlocks,
	InspectorControls,
	useBlockProps,
} from '@wordpress/block-editor';
import { PanelBody, TextControl, ToggleControl } from '@wordpress/components';
import { useSelect, useDispatch } from '@wordpress/data';
import { useEntityProp } from '@wordpress/core-data';
import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies.
 */
import TEMPLATE from './template';
import { isInFSETemplate } from '../../helpers/editor';
import { DISABLED_FIELD_OPACITY } from '../../helpers/event';
import { TAX_VENUE, CPT_EVENT } from '../../helpers/namespace';

/**
 * Edit component for the GatherPress Online Event v2 block.
 *
 * Container block that holds an icon and online event link.
 * If a postId attribute is set (override), it provides that as context to children.
 * Dims when the event doesn't have the online-event term.
 *
 * @since 1.0.0
 *
 * @param {Object} props            Block props.
 * @param {Object} props.attributes Block attributes.
 * @param {Object} props.context    Block context.
 *
 * @return {JSX.Element} The rendered React component.
 */
const Edit = ( { attributes, context } ) => {
	const { postId } = attributes;

	// Determine the event ID to check for online-event term.
	const eventId = postId || context?.postId || null;

	const isDescendentOfQueryLoop = Number.isFinite( context?.queryId );
	const { editPost, unlockPostSaving } = useDispatch( 'core/editor' );

	// Get the current post info and venue taxonomy.
	const { currentPostId, currentPostType, onlineEventTerm } = useSelect(
		( select ) => ( {
			currentPostId: select( 'core/editor' )?.getCurrentPostId(),
			currentPostType: select( 'core/editor' )?.getCurrentPostType(),
			onlineEventTerm:
				select( 'core' ).getEntityRecords( 'taxonomy', TAX_VENUE, {
					slug: 'online-event',
					per_page: 1,
				} )?.[ 0 ] || null,
		} ),
		[]
	);

	const isEditingEvent = CPT_EVENT === currentPostType;
	const showControls =
		! isDescendentOfQueryLoop && ! isInFSETemplate() && isEditingEvent;

	// Get venue taxonomy IDs for the current event.
	const [ venueTaxonomyIds, updateVenueTaxonomyIds ] = useEntityProp(
		'postType',
		CPT_EVENT,
		TAX_VENUE,
		currentPostId
	);

	// Get online event link from meta.
	const onlineEventLinkMeta = useSelect(
		( select ) =>
			select( 'core/editor' ).getEditedPostAttribute( 'meta' )
				?.gatherpress_online_event_link || '',
		[]
	);

	const [ onlineEventLink, setOnlineEventLink ] = useState( onlineEventLinkMeta );

	// Sync link state with meta.
	useEffect( () => {
		setOnlineEventLink( onlineEventLinkMeta );
	}, [ onlineEventLinkMeta ] );

	// Update the online event link meta.
	const updateOnlineEventLink = ( value ) => {
		editPost( { meta: { gatherpress_online_event_link: value } } );
		setOnlineEventLink( value );
		unlockPostSaving();
	};

	// Toggle the online-event term.
	const toggleOnlineEvent = ( shouldAdd ) => {
		if ( ! onlineEventTerm ) {
			return;
		}

		let currentTerms = [];
		if ( Array.isArray( venueTaxonomyIds ) ) {
			currentTerms = [ ...venueTaxonomyIds ];
		} else if ( venueTaxonomyIds ) {
			currentTerms = [ venueTaxonomyIds ];
		}

		const termId = onlineEventTerm.id;
		const termIdStr = String( termId );
		const hasTermAlready = currentTerms.some(
			( id ) => String( id ) === termIdStr
		);

		let newTerms;
		if ( shouldAdd ) {
			if ( ! hasTermAlready ) {
				newTerms = [ ...currentTerms, termId ];
			} else {
				newTerms = currentTerms;
			}
		} else {
			newTerms = currentTerms.filter( ( id ) => String( id ) !== termIdStr );
		}

		updateVenueTaxonomyIds( newTerms );
	};

	// Check if the event has the online-event term (reactive to changes).
	const isOnlineEvent = useSelect(
		( select ) => {
			const onlineTermId = onlineEventTerm?.id;

			if ( ! onlineTermId ) {
				return false;
			}

			// Check if eventId matches the current post being edited.
			const editorPostId = select( 'core/editor' )?.getCurrentPostId();
			const editorPostType = select( 'core/editor' )?.getCurrentPostType();
			const isCurrentPost = eventId && editorPostId === eventId;
			const isEditorEvent = CPT_EVENT === editorPostType;

			let venueTermIds;

			if ( isCurrentPost || ( ! eventId && isEditorEvent ) ) {
				// Use live editor data for current post.
				venueTermIds =
					select( 'core/editor' ).getEditedPostAttribute( TAX_VENUE );
			} else if ( eventId ) {
				// Fetch from saved post data.
				const post = select( 'core' ).getEntityRecord(
					'postType',
					CPT_EVENT,
					eventId
				);
				venueTermIds = post?.[ TAX_VENUE ];
			} else {
				return false;
			}

			if ( ! venueTermIds?.length ) {
				return false;
			}

			return venueTermIds.some(
				( id ) => String( id ) === String( onlineTermId )
			);
		},
		[ eventId, onlineEventTerm ]
	);

	const blockProps = useBlockProps( {
		style: {
			opacity:
				isInFSETemplate() || isDescendentOfQueryLoop || isOnlineEvent
					? 1
					: DISABLED_FIELD_OPACITY,
		},
	} );

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
			{ showControls && (
				<InspectorControls>
					<PanelBody
						title={ __( 'Online Event Settings', 'gatherpress' ) }
						initialOpen={ true }
					>
						<ToggleControl
							label={ __( 'This is an online event', 'gatherpress' ) }
							checked={ isOnlineEvent }
							onChange={ toggleOnlineEvent }
						/>
						{ isOnlineEvent && (
							<TextControl
								label={ __( 'Online event link', 'gatherpress' ) }
								value={ onlineEventLink }
								placeholder={ __(
									'Add link to online event',
									'gatherpress'
								) }
								onChange={ updateOnlineEventLink }
							/>
						) }
					</PanelBody>
				</InspectorControls>
			) }
		</div>
	);
};

export default Edit;
