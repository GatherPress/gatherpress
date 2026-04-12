/**
 * WordPress dependencies.
 */
import {
	BlockContextProvider,
	InnerBlocks,
	InspectorControls,
	useBlockProps,
} from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';
import { PanelBody, PanelRow } from '@wordpress/components';
import { useSelect } from '@wordpress/data';

/**
 * Internal dependencies
 */
import { getCurrentContextualPostId, hasValidBlockContext } from '../../helpers/editor';
import { isPostTypeSupporting, DISABLED_FIELD_OPACITY } from '../../helpers/event';
import { GetVenuePostFromTermId, GetVenuePostFromEventId, getVenuePostType, getVenueTaxonomy } from '../../helpers/venue';
import VenueNavigator from '../../components/VenueNavigator';
import { TEMPLATE_WITH_TITLE, TEMPLATE_WITHOUT_TITLE } from './template';

const Edit = ( props ) => {
	const { context } = props;

	const isDescendentOfQueryLoop = Number.isFinite( context?.queryId );
	const isEventContext = isPostTypeSupporting( 'gatherpress-venue', context?.postType );
	const isVenueContext = isPostTypeSupporting( 'gatherpress-venue-information', context?.postType );

	// Resolve the effective post type from context or the current editor post type.
	const currentEditorPostType = useSelect(
		( select ) => select( 'core/editor' )?.getCurrentPostType(),
		[]
	);
	const effectivePostType = context?.postType || currentEditorPostType;
	const venuePostType = getVenuePostType( effectivePostType );

	const eventId = getCurrentContextualPostId( context?.postId );
	const venueTaxonomy = getVenueTaxonomy( venuePostType );

	// Read venue taxonomy IDs without triggering context=edit REST requests.
	const venueTaxonomyIds = useSelect(
		( wpSelect ) => {
			if ( isVenueContext || isDescendentOfQueryLoop ) {
				return undefined;
			}

			// Try editor in-memory state first (PHP preload data + pending edits).
			const editorAttr = wpSelect( 'core/editor' )?.getEditedPostAttribute( venueTaxonomy );
			if ( Array.isArray( editorAttr ) ) {
				return editorAttr;
			}

			if ( ! eventId ) {
				return undefined;
			}

			// Fallback: query taxonomy terms with context=view (no edit permissions needed).
			const terms = wpSelect( 'core' ).getEntityRecords(
				'taxonomy',
				venueTaxonomy,
				{ post: eventId, per_page: 100, context: 'view' }
			);
			return terms?.map( ( t ) => t.id );
		},
		[ isVenueContext, isDescendentOfQueryLoop, eventId, venueTaxonomy ]
	);

	const isEditableEventContext =
		! isDescendentOfQueryLoop &&
		! isVenueContext &&
		Array.isArray( venueTaxonomyIds );

	// Fetch venue terms for direct event editing.
	const venueTerms = useSelect(
		( wpSelect ) => {
			if ( ! isEditableEventContext || ! venueTaxonomyIds?.length ) {
				return [];
			}

			return venueTaxonomyIds
				.map( ( termId ) =>
					wpSelect( 'core' ).getEntityRecord(
						'taxonomy',
						venueTaxonomy,
						termId
					)
				)
				.filter( Boolean );
		},
		[ isEditableEventContext, venueTaxonomyIds, venueTaxonomy ]
	);

	// Find venue term ID (excluding online-event).
	const venueTermId =
		venueTerms.find( ( term ) => 'online-event' !== term?.slug )?.id ||
		null;

	// Fetch venue post - use different methods for Query Loop vs direct editing.
	const venuePostFromTerm = GetVenuePostFromTermId( venueTermId );
	const venuePostFromEvent = GetVenuePostFromEventId(
		isDescendentOfQueryLoop ? context?.postId : null,
		context?.postType
	);

	// Use Query Loop result if available, otherwise use direct editing result.
	const venuePostArray = isDescendentOfQueryLoop
		? venuePostFromEvent
		: venuePostFromTerm;

	// When on a venue post, use the current post ID directly.
	// Otherwise, resolve from the event's venue taxonomy.
	let venuePostId = 0;
	if ( isVenueContext ) {
		venuePostId = getCurrentContextualPostId( context?.postId );
	} else if (
		venuePostArray?.[ 0 ]?.id &&
		venuePostArray[ 0 ].id !== eventId
	) {
		venuePostId = venuePostArray[ 0 ].id;
	}

	// Check if we have a physical venue selected.
	const hasVenue = 0 < venuePostId;

	// Dim the block when no venue is selected or no valid context.
	const blockProps = useBlockProps( {
		style: {
			opacity: hasValidBlockContext( {
				isDescendentOfQueryLoop,
				postType: context?.postType,
				support: 'gatherpress-venue',
				hasData: hasVenue,
			} )
				? 1
				: DISABLED_FIELD_OPACITY,
		},
	} );

	// Get the template based on context.
	const template = isEventContext ? TEMPLATE_WITH_TITLE : TEMPLATE_WITHOUT_TITLE;

	return (
		<div { ...blockProps }>
			<BlockContextProvider
				value={ {
					postId: venuePostId,
					postType: venuePostType,
				} }
			>
				<InnerBlocks template={ template } templateLock={ false } />
			</BlockContextProvider>
			<InspectorControls>
				{ ! isDescendentOfQueryLoop && isEventContext && (
					<PanelBody
						title={ __( 'Venue settings', 'gatherpress' ) }
						initialOpen={ true }
					>
						<PanelRow>
							<VenueNavigator { ...props } />
						</PanelRow>
					</PanelBody>
				) }
			</InspectorControls>
		</div>
	);
};

export default Edit;
