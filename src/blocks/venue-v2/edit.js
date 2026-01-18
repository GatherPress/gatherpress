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

/**
 * Internal dependencies
 */
import { getCurrentContextualPostId } from '../../helpers/editor';
import { isEventPostType } from '../../helpers/event';
import {
	GetVenuePostFromEventId,
	GetVenuePostFromTermId,
} from '../../helpers/venue';
import VenueNavigator from '../../components/VenueNavigator';
import { PT_EVENT, PT_VENUE, TAX_VENUE } from '../../helpers/namespace';
import TEMPLATE from './template';

const List = ( {
	props,
	blockProps,
	venuePostContext,
	isDescendentOfQueryLoop,
	isSelected,
} ) => {
	return (
		<div { ...blockProps }>
			<BlockContextProvider
				value={ {
					postId: venuePostContext,
					postType: PT_VENUE,
				} }
			>
				<InnerBlocks template={ TEMPLATE } />
				{ ! isDescendentOfQueryLoop && isSelected && (
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

const Edit = ( props ) => {
	const { attributes, context, isSelected } = props;
	const blockProps = useBlockProps();

	// If this 'venue' block is on the root-level of a 'gatherpress_event' post,
	// the desired post is the currently edited post.
	// Alternatively the block could be part of a `core/query` block,
	// then props.context provides `postType` and `postId` to use.
	const cId = getCurrentContextualPostId( context?.postId );

	const [ venueTaxonomyIds ] = useEntityProp(
		'postType',
		PT_EVENT,
		TAX_VENUE,
		cId
	);

	const isDescendentOfQueryLoop = Number.isFinite( context?.queryId );

	const isEventContext = isEventPostType( context?.postType );
	const venuePostFromEventId = GetVenuePostFromEventId( cId );

	const isEditableEventContext =
		! isDescendentOfQueryLoop && venueTaxonomyIds instanceof Array;
	const taxIds =
		venueTaxonomyIds &&
		1 <= venueTaxonomyIds.length &&
		Number.isFinite( venueTaxonomyIds[ 0 ] )
			? venueTaxonomyIds[ 0 ]
			: null;
	const venuePostFromTermId = GetVenuePostFromTermId( taxIds );

	let venuePost = null;
	if ( isEditableEventContext ) {
		// Editable event: derive venue from its term ID
		venuePost = venuePostFromTermId;
	} else if ( isEventContext ) {
		// Non-editable event: derive venue from its event ID
		venuePost = venuePostFromEventId;
	}

	const venuePostContext =
		venuePost && 1 <= venuePost.length && Number.isFinite( venuePost[ 0 ].id )
			? venuePost[ 0 ].id
			: attributes?.selectedPostId;

	return (
		<List
			props={ props }
			blockProps={ blockProps }
			venuePostContext={ venuePostContext }
			isDescendentOfQueryLoop={ isDescendentOfQueryLoop }
			isSelected={ isSelected }
		/>
	);
};

export default Edit;
