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
import {
	PanelBody,
	PanelRow,
	SelectControl,
	TextControl,
	ToggleControl,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalVStack as VStack,
} from '@wordpress/components';
import { useEntityProp } from '@wordpress/core-data';
import { useSelect, useDispatch } from '@wordpress/data';
import { useEffect, useRef, useState } from '@wordpress/element';
import { createBlocksFromInnerBlocksTemplate } from '@wordpress/blocks';

/**
 * Internal dependencies
 */
import { getCurrentContextualPostId, isInFSETemplate } from '../../helpers/editor';
import { isEventPostType } from '../../helpers/event';
import { GetVenuePostFromTermId } from '../../helpers/venue';
import VenueNavigator from '../../components/VenueNavigator';
import { CPT_EVENT, CPT_VENUE, TAX_VENUE } from '../../helpers/namespace';
import {
	TEMPLATE_WITH_TITLE,
	TEMPLATE_WITHOUT_TITLE,
	TEMPLATE_ONLINE_EVENT,
} from './template';

/**
 * Get the composite template based on venue states.
 *
 * @param {boolean} hasPhysicalVenue Whether a physical venue is selected.
 * @param {boolean} hasOnlineEvent   Whether online event is enabled.
 * @param {boolean} isEventContext   Whether we're in an event context.
 * @param {number}  eventId          The event post ID to pass to online-event-v2.
 * @return {Array} The composite template array.
 */
function getCompositeTemplate( hasPhysicalVenue, hasOnlineEvent, isEventContext, eventId = 0 ) {
	const venueTemplate = isEventContext ? TEMPLATE_WITH_TITLE : TEMPLATE_WITHOUT_TITLE;

	// Create online event template with postId override for correct context.
	const onlineEventTemplate = eventId
		? [ [ 'gatherpress/online-event-v2', { postId: eventId } ] ]
		: TEMPLATE_ONLINE_EVENT;

	// Both venue and online event.
	if ( hasPhysicalVenue && hasOnlineEvent ) {
		return [ ...venueTemplate, ...onlineEventTemplate ];
	}
	// Venue only (or empty venue when nothing selected).
	if ( hasPhysicalVenue || ! hasOnlineEvent ) {
		return venueTemplate;
	}
	// Online event only (no venue).
	return onlineEventTemplate;
}

const Edit = ( props ) => {
	const { attributes, setAttributes, context, clientId } = props;
	const { displayCondition } = attributes;
	const blockProps = useBlockProps();
	const { replaceInnerBlocks } = useDispatch( 'core/block-editor' );
	const { editPost, unlockPostSaving } = useDispatch( 'core/editor' );
	const prevStateRef = useRef( null );

	const eventId = getCurrentContextualPostId( context?.postId );
	const [ venueTaxonomyIds, updateVenueTaxonomyIds ] = useEntityProp(
		'postType',
		CPT_EVENT,
		TAX_VENUE,
		eventId
	);

	const isDescendentOfQueryLoop = Number.isFinite( context?.queryId );
	const isEventContext = isEventPostType( context?.postType );
	const isEditableEventContext =
		! isDescendentOfQueryLoop && Array.isArray( venueTaxonomyIds );

	// Get the online-event term to find its ID.
	const onlineEventTerm = useSelect( ( wpSelect ) => {
		const terms = wpSelect( 'core' ).getEntityRecords( 'taxonomy', TAX_VENUE, {
			slug: 'online-event',
			per_page: 1,
		} );
		return terms?.[ 0 ] || null;
	}, [] );

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

	// Check if we have a physical venue (not online-event).
	const hasPhysicalVenue = venueTerms.some(
		( term ) => 'online-event' !== term?.slug
	);

	// Check if we have the online-event term.
	const hasOnlineEvent = venueTerms.some(
		( term ) => 'online-event' === term?.slug
	);

	// Find venue term ID (excluding online-event).
	const venueTermId =
		venueTerms.find( ( term ) => 'online-event' !== term.slug )?.id || null;

	// Fetch venue post only if we have a venue term.
	const venuePostArray = GetVenuePostFromTermId( venueTermId );
	const venuePostId =
		venuePostArray?.[ 0 ]?.id && venuePostArray[ 0 ].id !== eventId
			? venuePostArray[ 0 ].id
			: 0;

	// Get online event link from meta.
	const onlineEventLinkMeta = useSelect(
		( wpSelect ) =>
			wpSelect( 'core/editor' ).getEditedPostAttribute( 'meta' )
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
			// Also clear the link when toggling off.
			updateOnlineEventLink( '' );
		}

		updateVenueTaxonomyIds( newTerms );
	};

	// Replace blocks when venue/online states change template structure.
	useEffect( () => {
		if ( ! isEditableEventContext ) {
			return;
		}

		const currentState = `${ hasPhysicalVenue }-${ hasOnlineEvent }`;

		// Only replace if state actually changed (not on initial render).
		if ( null !== prevStateRef.current && prevStateRef.current !== currentState ) {
			const newTemplate = getCompositeTemplate(
				hasPhysicalVenue,
				hasOnlineEvent,
				isEventContext,
				eventId
			);
			const newBlocks = createBlocksFromInnerBlocksTemplate( newTemplate );
			replaceInnerBlocks( clientId, newBlocks, false );
		}

		prevStateRef.current = currentState;
	}, [
		hasPhysicalVenue,
		hasOnlineEvent,
		isEventContext,
		isEditableEventContext,
		clientId,
		replaceInnerBlocks,
		eventId,
	] );

	// Get the initial template.
	const template = getCompositeTemplate(
		hasPhysicalVenue,
		hasOnlineEvent,
		isEventContext,
		eventId
	);

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
				{ ( isDescendentOfQueryLoop || isInFSETemplate() ) && (
					<PanelBody
						title={ __( 'Display settings', 'gatherpress' ) }
						initialOpen={ true }
					>
						<SelectControl
							label={ __( 'Display condition', 'gatherpress' ) }
							help={ __(
								'Control when this block renders based on venue type.',
								'gatherpress'
							) }
							value={ displayCondition }
							options={ [
								{ label: __( 'Any', 'gatherpress' ), value: 'any' },
								{
									label: __( 'Physical venue only', 'gatherpress' ),
									value: 'physical',
								},
								{
									label: __( 'Online event only', 'gatherpress' ),
									value: 'online',
								},
							] }
							onChange={ ( value ) =>
								setAttributes( { displayCondition: value } )
							}
						/>
					</PanelBody>
				) }
				{ ! isDescendentOfQueryLoop && isEventContext && (
					<PanelBody
						title={ __( 'Venue settings', 'gatherpress' ) }
						initialOpen={ true }
					>
						<VStack spacing={ 6 }>
							<PanelRow>
								<VenueNavigator { ...props } />
							</PanelRow>
							<div>
								<ToggleControl
									label={ __( 'This is an online event', 'gatherpress' ) }
									checked={ hasOnlineEvent }
									onChange={ toggleOnlineEvent }
								/>
								{ hasOnlineEvent && (
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
							</div>
						</VStack>
					</PanelBody>
				) }
			</InspectorControls>
		</div>
	);
};

export default Edit;
