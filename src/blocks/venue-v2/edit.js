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
import { getCurrentContextualPostId, isInFSETemplate } from '../../helpers/editor';
import { isEventPostType, DISABLED_FIELD_OPACITY } from '../../helpers/event';
import { GetVenuePostFromTermId } from '../../helpers/venue';
import VenueNavigator from '../../components/VenueNavigator';
import { CPT_EVENT, CPT_VENUE, TAX_VENUE } from '../../helpers/namespace';
import { TEMPLATE_WITH_TITLE, TEMPLATE_WITHOUT_TITLE } from './template';

const Edit = ( props ) => {
	const { context } = props;

	const eventId = getCurrentContextualPostId( context?.postId );
	const [ venueTaxonomyIds ] = useEntityProp(
		'postType',
		CPT_EVENT,
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

	// Find venue term ID (excluding online-event).
	const venueTermId =
		venueTerms.find( ( term ) => 'online-event' !== term?.slug )?.id || null;

	// Fetch venue post only if we have a venue term.
	const venuePostArray = GetVenuePostFromTermId( venueTermId );
	const venuePostId =
		venuePostArray?.[ 0 ]?.id && venuePostArray[ 0 ].id !== eventId
			? venuePostArray[ 0 ].id
			: 0;

	// Check if we have a physical venue selected.
	const hasVenue = 0 < venuePostId;

	// Dim the block when no venue is selected (except in query loop or FSE template).
	const blockProps = useBlockProps( {
		style: {
			opacity:
				isInFSETemplate() || isDescendentOfQueryLoop || hasVenue
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
					postType: CPT_VENUE,
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
