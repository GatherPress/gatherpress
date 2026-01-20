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
import { useSelect, useDispatch } from '@wordpress/data';
import { useEffect, useRef } from '@wordpress/element';
import { createBlocksFromInnerBlocksTemplate } from '@wordpress/blocks';

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
	const { context, isSelected, clientId } = props;
	const blockProps = useBlockProps();
	const { replaceInnerBlocks } = useDispatch( 'core/block-editor' );
	const prevModeRef = useRef( null );

	const currentPostId = getCurrentContextualPostId( context?.postId );
	const isDescendentOfQueryLoop = Number.isFinite( context?.queryId );
	const isEventContext = isEventPostType( context?.postType );

	// Only fetch venue taxonomy when in event context.
	const [ venueTaxonomyIds ] = useEntityProp(
		'postType',
		isEventContext ? PT_EVENT : PT_VENUE,
		TAX_VENUE,
		isEventContext ? currentPostId : null
	);

	const isEditableEventContext =
		isEventContext &&
		! isDescendentOfQueryLoop &&
		Array.isArray( venueTaxonomyIds );

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

	// Determine venue post ID based on context.
	let venuePostId = 0;

	if ( isEventContext ) {
		// In event context: fetch venue post from venue term.
		const venuePostArray = GetVenuePostFromTermId( venueTermId );
		venuePostId =
			venuePostArray?.[ 0 ]?.id && venuePostArray[ 0 ].id !== currentPostId
				? venuePostArray[ 0 ].id
				: 0;
	} else {
		// In venue context: use the current post being edited.
		venuePostId = currentPostId;
	}

	// Choose template based on post type context.
	const template = isEventContext ? TEMPLATE_WITH_TITLE : TEMPLATE_WITHOUT_TITLE;

	// Replace blocks when switching between online and venue modes.
	useEffect( () => {
		const currentMode = hasOnlyOnlineEvent ? 'online' : 'venue';

		// Only replace if mode actually changed (not on initial render).
		if ( null !== prevModeRef.current && prevModeRef.current !== currentMode ) {
			const newTemplate = hasOnlyOnlineEvent ? TEMPLATE_ONLINE_EVENT : template;
			const newBlocks = createBlocksFromInnerBlocksTemplate( newTemplate );
			replaceInnerBlocks( clientId, newBlocks, false );
		}

		prevModeRef.current = currentMode;
	}, [ hasOnlyOnlineEvent, template, clientId, replaceInnerBlocks ] );

	return (
		<div { ...blockProps }>
			{ hasOnlyOnlineEvent ? (
				// Show online event template - no venue context needed.
				<InnerBlocks
					template={ TEMPLATE_ONLINE_EVENT }
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
					<InnerBlocks template={ template } templateLock={ false } />
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
