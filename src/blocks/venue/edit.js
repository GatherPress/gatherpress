/**
 * WordPress dependencies
 */
import {
	BlockContextProvider,
	BlockControls,
	InnerBlocks,
	InspectorControls,
	useBlockProps,
	store as blockEditorStore,
} from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';
import { applyFilters } from '@wordpress/hooks';
import {
	PanelBody,
	PanelRow,
	ToolbarButton,
	ToolbarGroup,
} from '@wordpress/components';
import { useDispatch, useSelect } from '@wordpress/data';
import { createBlock } from '@wordpress/blocks';
import { useMemo, useState } from '@wordpress/element';

/**
 * Internal dependencies
 */
import { getCurrentContextualPostId, hasValidBlockContext, isInFSETemplate } from '../../helpers/editor';
import { usePostTypeSupports, findEventPostById, DISABLED_FIELD_OPACITY } from '../../helpers/event';
import { useVenuePostFromTermId, GetVenuePostFromEventId, findVenuePostById, getVenuePostType, getVenueTaxonomy, useVenueTaxonomyIds } from '../../helpers/venue';
import VenueNavigator from '../../components/VenueNavigator';
import PatternPicker, { PatternChooserModal } from '../../components/PatternPicker';
import { TEMPLATE_WITH_TITLE, TEMPLATE_WITHOUT_TITLE } from './templates/venue-details';

/**
 * Recursively turn an InnerBlocks-shape `[ name, attrs, inner ]` template
 * tuple tree into instantiated block objects, ready for `replaceInnerBlocks`.
 *
 * @param {Array} template Tuples in `[ blockName, attributes, innerBlocks ]` form.
 *
 * @return {Array} Created block instances.
 */
function templateToBlocks( template ) {
	return template.map( ( [ name, attributes, innerBlocks ] ) =>
		createBlock(
			name,
			attributes,
			templateToBlocks( innerBlocks || [] )
		)
	);
}

/**
 * Default starter patterns offered by the Venue block's pattern picker.
 *
 * Two layouts that share the address + phone + website + map base — one
 * prepends a `core/post-title` (intended for event posts where the title
 * names the event hosting the venue), the other omits it (intended for
 * venue posts where the post itself is the venue and the page title
 * already names it). Both are always available regardless of host post
 * type — the picker lists both and `<defaultTemplate>` (computed from
 * host context) decides which auto-loads when `patternPicked` is true.
 *
 * Run through the `gatherpress.venuePatterns` filter inside `Edit` (see
 * the call site below for the post-type-aware contract) so other plugins
 * or themes can register their own venue layouts. Each entry is shaped
 * `{ name, title, description, template }` — `template` is an `InnerBlocks`
 * tuple tree (`[ blockName, attributes, innerBlocks ]`).
 *
 * @since 0.27.0
 */
const DEFAULT_PATTERNS = [
	{
		name: 'gatherpress/venue-details-with-title',
		title: __( 'Venue Details with Title', 'gatherpress' ),
		description: __(
			'Post title above the venue address, phone, website, and map. Default for event posts.',
			'gatherpress'
		),
		template: TEMPLATE_WITH_TITLE,
	},
	{
		name: 'gatherpress/venue-details',
		title: __( 'Venue Details', 'gatherpress' ),
		description: __(
			'Address, phone, website, and an embedded map (no post title). Default for venue posts.',
			'gatherpress'
		),
		template: TEMPLATE_WITHOUT_TITLE,
	},
];

const Edit = ( props ) => {
	const { attributes, setAttributes, clientId, context } = props;
	const [ isToolbarChooserOpen, setIsToolbarChooserOpen ] = useState( false );
	const { patternPicked } = attributes;
	const { replaceInnerBlocks } = useDispatch( blockEditorStore );

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
	// being walked (so the source taxonomy lookup uses the override's event
	// post type, not the host's). When the override resolves to a venue,
	// the post type comes from that venue directly.
	const effectivePostType =
		'event' === overrideResolution?.kind
			? overrideResolution.postType
			: context?.postType || currentEditorPostType;

	// Source post type — defaults to gatherpress_venue. When set explicitly
	// (e.g. sourcePostType: "production"), the block resolves its connected
	// source post via the shadow taxonomy of that CPT instead of venues.
	// The venue-helper chain (getVenueTaxonomy, useVenueTaxonomyIds,
	// useVenuePostFromTermId) is parameterized by post type and works for
	// any shadow-source-supporting CPT without renaming.
	const sourcePostType =
		attributes?.sourcePostType || getVenuePostType( effectivePostType );
	const venuePostType =
		'venue' === overrideResolution?.kind
			? overrideResolution.postType
			: sourcePostType;

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
	// Pass venuePostType through so non-venue source types (e.g. production)
	// resolve the right post type — without it the helper defaults to
	// gatherpress_venue and the wrong CPT is queried.
	const venuePostFromTerm = useVenuePostFromTermId( venueTermId, venuePostType );
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

	// Auto-load default template — picks the with-title variant on event
	// hosts, the without-title variant elsewhere (including venue posts).
	// Both patterns remain available in the picker modal regardless of
	// context, so authors can manually pick the other variant if they want.
	//
	/**
	 * Default template seeded into auto-loaded Venue blocks.
	 *
	 * Fires only when the picker is suppressed (the canonical instance on
	 * a new event or venue post — `patternPicked: true` set in the post type
	 * `template` arg / venue-template pattern). Lets a plugin or theme swap
	 * the layout that appears without the user clicking through the picker.
	 * Receives the context-resolved template (with-title in event hosts,
	 * without-title elsewhere). The picker itself is filterable separately
	 * via `gatherpress.venuePatterns`.
	 *
	 * @since 0.27.0
	 *
	 * @param {Array} defaultTemplate Default `InnerBlocks` tuple tree
	 *                                resolved from `TEMPLATE_WITH_TITLE` /
	 *                                `TEMPLATE_WITHOUT_TITLE` based on
	 *                                whether the host post supports events.
	 * @return {Array} Tuple tree handed to `<InnerBlocks template={ ... } />`.
	 */
	const defaultTemplate = applyFilters(
		'gatherpress.venueDefaultTemplate',
		isEventContext ? TEMPLATE_WITH_TITLE : TEMPLATE_WITHOUT_TITLE
	);

	/**
	 * Patterns shown in the Venue block's picker modal.
	 *
	 * Receives the bundled defaults plus the host post type so consumers
	 * can vary the offered patterns by where the block is being edited —
	 * e.g. a companion plugin's `production` post type can swap in a
	 * production-specific layout, while leaving the standard event/venue
	 * picker untouched. `hostPostType` is the post type of the post being
	 * edited (resolved from block context, falling back to the editor's
	 * current post type).
	 *
	 * @since 0.27.0
	 *
	 * @param {Array}       patterns     Default array containing the bundled
	 *                                   "Venue Details with Title" and
	 *                                   "Venue Details" patterns.
	 * @param {string|null} hostPostType Post type of the post being edited.
	 *
	 * @return {Array} Patterns shown in the picker modal, in display order.
	 *
	 * @example
	 *   addFilter(
	 *     'gatherpress.venuePatterns',
	 *     'my-plugin/extra-venue-pattern',
	 *     ( patterns, hostPostType ) => {
	 *       if ( 'production' !== hostPostType ) {
	 *         return patterns;
	 *       }
	 *       return [ ...patterns, {
	 *         name: 'my-plugin/map-only',
	 *         title: __( 'Map only', 'my-plugin' ),
	 *         description: __( '...', 'my-plugin' ),
	 *         template: [ [ 'gatherpress/venue-map' ] ],
	 *       } ];
	 *     }
	 *   );
	 */
	const patterns = useMemo(
		() =>
			applyFilters(
				'gatherpress.venuePatterns',
				DEFAULT_PATTERNS,
				effectivePostType
			),
		[ effectivePostType ]
	);

	const innerBlockCount = useSelect(
		( select ) =>
			select( blockEditorStore ).getBlocks( clientId ).length,
		[ clientId ]
	);
	const showPatternPicker =
		! patternPicked && 0 === innerBlockCount;

	const handlePatternPick = ( pattern ) => {
		replaceInnerBlocks(
			clientId,
			templateToBlocks( pattern.template )
		);
		setAttributes( { patternPicked: true } );
	};

	// Dim the block when no venue is selected or no valid context. The
	// pattern-picker placeholder always renders at full opacity — dimming
	// the picker would obscure the actionable Choose button.
	const blockProps = useBlockProps( {
		style: {
			opacity:
				showPatternPicker ||
				hasValidBlockContext( {
					isDescendentOfQueryLoop,
					hasSupport: isEventContext,
					hasData: hasVenue,
				} )
					? 1
					: DISABLED_FIELD_OPACITY,
		},
	} );

	return (
		<div { ...blockProps }>
			{ ! showPatternPicker && (
				<BlockControls>
					<ToolbarGroup>
						<ToolbarButton
							text={ __( 'Choose pattern', 'gatherpress' ) }
							onClick={ () => setIsToolbarChooserOpen( true ) }
						/>
					</ToolbarGroup>
				</BlockControls>
			) }
			{ isToolbarChooserOpen && (
				<PatternChooserModal
					patterns={ patterns }
					onPick={ handlePatternPick }
					onClose={ () => setIsToolbarChooserOpen( false ) }
				/>
			) }
			<BlockContextProvider
				value={ {
					postId: venuePostId,
					postType: venuePostType,
				} }
			>
				{ showPatternPicker && (
					<PatternPicker
						label={ __( 'Venue', 'gatherpress' ) }
						icon="location"
						instructions={ __(
							'Choose a pattern for the venue.',
							'gatherpress'
						) }
						patterns={ patterns }
						showStartBlank={ false }
						onPick={ handlePatternPick }
					/>
				) }
				{ ! showPatternPicker &&
					( patternPicked && 0 === innerBlockCount ? (
						<InnerBlocks
							template={ defaultTemplate }
							templateLock={ false }
						/>
					) : (
						<InnerBlocks templateLock={ false } />
					) ) }
			</BlockContextProvider>
			<InspectorControls>
				{ 'gatherpress_venue' === sourcePostType && ! isDescendentOfQueryLoop && ! isInFSETemplate() && isEventContext && (
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
