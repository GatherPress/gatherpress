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
import TEMPLATE from './template';

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

	return (
		<div { ...blockProps }>
			<BlockContextProvider
				value={ {
					postId: venuePostId,
					postType: PT_VENUE,
				} }
			>
				<InnerBlocks template={ TEMPLATE } templateLock={ false } />
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
			</BlockContextProvider>
		</div>
	);
};

export default Edit;
