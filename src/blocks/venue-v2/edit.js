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
import { useEntityProp } from '@wordpress/core-data';
import { useSelect } from '@wordpress/data';

/**
 * Internal dependencies
 */
import { getCurrentContextualPostId } from '../../helpers/editor';
import { isEventPostType } from '../../helpers/event';
import { GetVenuePostFromTermId } from '../../helpers/venue';
import VenueNavigator from '../../components/VenueNavigator';
import { PT_EVENT, PT_VENUE, TAX_VENUE } from '../../helpers/namespace';
import { TEMPLATE_WITH_TITLE, TEMPLATE_WITHOUT_TITLE, TEMPLATE_ONLINE_EVENT } from './template';

const Edit = ( props ) => {
	const { context, isSelected } = props;
	const blockProps = useBlockProps();

	const eventId = getCurrentContextualPostId( context?.postId );
	const [ venueTaxonomyIds ] = useEntityProp(
		'postType',
		PT_EVENT,
		TAX_VENUE,
		eventId
	);

	const isDescendentOfQueryLoop = Number.isFinite( context?.queryId );
	const isEventContext = isEventPostType( context?.postType );
	const isEditableEventContext =
		! isDescendentOfQueryLoop && Array.isArray( venueTaxonomyIds );

	// Fetch venue terms.
	const venueTerms = useSelect(
		( wpSelect ) => {
			if ( ! isEditableEventContext || ! venueTaxonomyIds?.length ) {
				return [];
			}

			return venueTaxonomyIds
				.map( ( termId ) =>
					wpSelect( 'core' ).getEntityRecord(
						'taxonomy',
						TAX_VENUE,
						termId
					)
				)
				.filter( Boolean );
		},
		[ isEditableEventContext, venueTaxonomyIds ]
	);

	// Check if we only have the online-event term.
	const hasOnlyOnlineEvent =
		isEventContext &&
		venueTerms &&
		1 === venueTerms.length &&
		'online-event' === venueTerms[ 0 ]?.slug;

	// Find venue term ID (excluding online-event).
	const venueTermId =
		venueTerms.find( ( term ) => 'online-event' !== term.slug )?.id ||
		null;

	// Fetch venue post only if we have a venue term.
	const venuePostArray = GetVenuePostFromTermId( venueTermId );
	const venuePostId =
		venuePostArray?.[ 0 ]?.id && venuePostArray[ 0 ].id !== eventId
			? venuePostArray[ 0 ].id
			: 0;

	// Choose template based on post type context.
	const template = isEventContext ? TEMPLATE_WITH_TITLE : TEMPLATE_WITHOUT_TITLE;

	// Determine which template to use based on online event status.
	const activeTemplate = hasOnlyOnlineEvent ? TEMPLATE_ONLINE_EVENT : template;

	// Use a key to force remount when switching between modes.
	const innerBlocksKey = hasOnlyOnlineEvent ? 'online' : `venue-${ venuePostId }`;

	return (
		<div { ...blockProps }>
			{ hasOnlyOnlineEvent ? (
				// Show online event template - no venue context needed.
				<InnerBlocks
					key={ innerBlocksKey }
					template={ activeTemplate }
					templateLock={ false }
				/>
			) : (
				// Show normal venue template for physical venues.
				<BlockContextProvider
					value={ {
						postId: venuePostId,
						postType: PT_VENUE,
					} }
				>
					<InnerBlocks
						key={ innerBlocksKey }
						template={ activeTemplate }
						templateLock={ false }
					/>
				</BlockContextProvider>
			) }
			{ ! isDescendentOfQueryLoop &&
				isSelected &&
				isEventContext && (
				<InspectorControls>
					<PanelBody
						title={ __( 'Venue settings', 'gatherpress' ) }
						initialOpen={ true }
					>
						<PanelRow>
							<VenueNavigator { ...props } />
						</PanelRow>
					</PanelBody>
				</InspectorControls>
			) }
		</div>
	);
};

export default Edit;
