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
import { getCurrentContextualPostId, hasValidBlockContext, isInFSETemplate } from '../../helpers/editor';
import { usePostTypeSupports, findEventPostById, DISABLED_FIELD_OPACITY } from '../../helpers/event';
import { GetVenuePostFromTermId, GetVenuePostFromEventId, findVenuePostById, getVenuePostType, getVenueTaxonomy, useVenueTaxonomyIds } from '../../helpers/venue';
import VenueNavigator from '../../components/VenueNavigator';
import { TEMPLATE_WITH_TITLE, TEMPLATE_WITHOUT_TITLE } from './template';

const Edit = ( props ) => {
	const { attributes, context } = props;

	const isDescendentOfQueryLoop = Number.isFinite( context?.queryId );
	// Reactive supports checks so the block re-renders once the post-type
	// definition (and its supports) load — non-reactive `select()` calls miss
	// that resolution and leave the block permanently dimmed in Query Loops.
	const isEventContext = usePostTypeSupports( 'gatherpress-venue', context?.postType );
	const isVenueContext = usePostTypeSupports( 'gatherpress-venue-information', context?.postType );

	// Resolve the effective post type from context or the current editor post type.
	const currentEditorPostType = useSelect(
		( select ) => select( 'core/editor' )?.getCurrentPostType(),
		[]
	);

	// Resolve the postIdOverride target across event-supporting and venue-
	// supporting post types so the block can light up on a non-event /
	// non-venue host (e.g. a regular page). Event lookup wins on tie — the
	// rest of the block's flow is event-centric, so an ID that resolves as
	// an event is treated as one even if a venue with the same ID also
	// exists. Returns null when the override is unset or resolves to
	// neither bucket.
	const overrideId = attributes?.postId || null;
	const overrideResolution = useSelect(
		( select ) => {
			if ( ! overrideId ) {
				return null;
			}
			const eventPost = findEventPostById( select, overrideId );
			if ( eventPost ) {
				return { kind: 'event', postType: eventPost.type };
			}
			const venuePost = findVenuePostById( select, overrideId );
			if ( venuePost ) {
				return { kind: 'venue', postType: venuePost.type };
			}
			return null;
		},
		[ overrideId ]
	);

	// When the override resolves to an event, use the override as the event
	// being walked (so the venue taxonomy lookup uses the override's event
	// post type, not the host's). When the override resolves to a venue,
	// the post type comes from that venue directly.
	const effectivePostType =
		'event' === overrideResolution?.kind
			? overrideResolution.postType
			: context?.postType || currentEditorPostType;
	const venuePostType =
		'venue' === overrideResolution?.kind
			? overrideResolution.postType
			: getVenuePostType( effectivePostType );

	// `eventId` is the post whose venue taxonomy we walk to find the venue.
	// Only set it when we genuinely have an event in hand — otherwise the
	// REST query below fires against the host page and 403s, since the
	// `_gatherpress_venue` taxonomy isn't associated with non-event posts.
	const isOverrideEvent = 'event' === overrideResolution?.kind;
	const isOverrideVenue = 'venue' === overrideResolution?.kind;
	const isOverrideActive = !! overrideId;

	let eventId = null;
	if ( isOverrideEvent ) {
		eventId = overrideId;
	} else if ( ! isOverrideActive && isEventContext ) {
		eventId = getCurrentContextualPostId( context?.postId );
	}
	const venueTaxonomy = getVenueTaxonomy( venuePostType );

	// Skip the taxonomy walk when there's no event to walk against (no event
	// host + no event override), when the host post IS the venue, when the
	// override resolved to a venue directly (no walk needed), or when in a
	// Query Loop (handled by GetVenuePostFromEventId below).
	const skipVenueTaxonomyLookup =
		null === eventId ||
		isVenueContext ||
		isDescendentOfQueryLoop ||
		isOverrideVenue;

	const venueTaxonomyIds = useVenueTaxonomyIds(
		venueTaxonomy,
		eventId,
		skipVenueTaxonomyLookup
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

	// Resolution priority for the rendered venue:
	// 1. postIdOverride that points at a venue post → use it directly.
	// 2. Host post is a venue → use the current post.
	// 3. Otherwise → walk the event's venue taxonomy (event may itself be
	//    the override, see `eventId` resolution above).
	let venuePostId = 0;
	if ( isOverrideVenue ) {
		venuePostId = overrideId;
	} else if ( isVenueContext && ! isOverrideActive ) {
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
				hasSupport: isEventContext,
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
				{ ! isDescendentOfQueryLoop && ! isInFSETemplate() && isEventContext && (
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
