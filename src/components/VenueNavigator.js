/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import {
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalNavigatorProvider as NavigatorProvider,
	Navigator,
} from '@wordpress/components';
import { store as coreDataStore } from '@wordpress/core-data';
import { useSelect, useDispatch } from '@wordpress/data';
import { useState, useCallback, useMemo } from '@wordpress/element';

/**
 * Internal dependencies
 */
import { getVenuePostType, getVenueTaxonomy } from '../helpers/venue';
import CreateVenueForm from './VenueForm';
import { VenueComboboxProvider } from './VenueComboboxProvider';
import PopularVenues from './PopularVenues';
import { isPostTypeSupporting } from '../helpers/event';
import { getCurrentContextualPostId } from '../helpers/editor';

/**
 *
 * @param {Object} props Properties of the 'gatherpress/venue'-block.
 * @return {Component} A Navigator component to be rendered.
 */
export default function VenueNavigator( props = null ) {
	const addNewItemLabel = __( 'Add New Venue', 'gatherpress' );

	const venuePostType = getVenuePostType( props?.context?.postType );
	const venueTaxonomy = getVenueTaxonomy( venuePostType );

	/**
	 * Check if user can CREATE new venues.
	 *
	 * @todo Better use useResourcePermissions here!!
	 *       https://developer.wordpress.org/block-editor/reference-guides/packages/packages-core-data/#useresourcepermissions
	 */
	const userCanEdit = useSelect( ( select ) => {
		const venuePostTypeObj = select( 'core' ).getPostType( venuePostType );
		const restBase = venuePostTypeObj?.rest_base || venuePostType + 's';
		return select( coreDataStore ).canUser( 'create', restBase ); // needs to be plural, because canUser currently only supports resources in the wp/v2 namespace.
	}, [ venuePostType ] );

	const [ search, setSearch ] = useState( '' );

	// Check if we're in a venue-supporting context to show popular venues.
	// When used in panel context, props may be null, so check current editor post type.
	const isEventContext = isPostTypeSupporting( 'gatherpress-venue', props?.context?.postType );

	// Get current venue and update function for event context.
	const cId = getCurrentContextualPostId( props?.context?.postId );

	const { editPost } = useDispatch( 'core/editor' );

	// Read venue taxonomy IDs without triggering context=edit REST requests.
	const venueTaxonomyIds = useSelect(
		( wpSelect ) => {
			// Try editor in-memory state first (PHP preload data + pending edits).
			const editorAttr = wpSelect( 'core/editor' )?.getEditedPostAttribute( venueTaxonomy );
			if ( Array.isArray( editorAttr ) ) {
				return editorAttr;
			}

			if ( ! cId ) {
				return undefined;
			}

			// Fallback: query taxonomy terms with context=view (no edit permissions needed).
			const terms = wpSelect( 'core' ).getEntityRecords(
				'taxonomy',
				venueTaxonomy,
				{ post: cId, per_page: 100, context: 'view' }
			);
			return terms?.map( ( t ) => t.id );
		},
		[ venueTaxonomy, cId ]
	);

	const updateVenueTaxonomyIds = useCallback(
		( newIds ) => editPost( { [ venueTaxonomy ]: newIds } ),
		[ editPost, venueTaxonomy ]
	);

	// Get the online-event term to preserve it when selecting a venue.
	const onlineEventTermId = useSelect( ( wpSelect ) => {
		const terms = wpSelect( 'core' ).getEntityRecords( 'taxonomy', venueTaxonomy, {
			slug: 'online-event',
			per_page: 1,
		} );
		return terms?.[ 0 ]?.id || null;
	}, [ venueTaxonomy ] );

	// Check if online-event term is currently assigned.
	const hasOnlineEventTerm = useMemo( () => {
		if ( ! venueTaxonomyIds || ! onlineEventTermId ) {
			return false;
		}
		const onlineIdStr = String( onlineEventTermId );
		return venueTaxonomyIds.some( ( id ) => String( id ) === onlineIdStr );
	}, [ venueTaxonomyIds, onlineEventTermId ] );

	// Handler for popular venue selection.
	const handlePopularVenueSelect = useCallback(
		( venueId ) => {
			if ( isEventContext && updateVenueTaxonomyIds ) {
				let save = [ venueId ];
				// Preserve online-event term if it was set.
				if ( hasOnlineEventTerm && onlineEventTermId ) {
					save = [ ...save, onlineEventTermId ];
				}
				updateVenueTaxonomyIds( save );
			}
		},
		[ isEventContext, updateVenueTaxonomyIds, hasOnlineEventTerm, onlineEventTermId ]
	);

	return (
		<NavigatorProvider
			initialPath="/"
			style={ {
				width: '100%',
			} }
		>
			<Navigator.Screen
				path="/"
				style={ {
					padding: '.1em',
				} }
			>
				<VenueComboboxProvider
					{ ...props }
					search={ search }
					setSearch={ setSearch }
				/>
				{ isEventContext && (
					<PopularVenues
						onSelect={ handlePopularVenueSelect }
						currentId={ venueTaxonomyIds?.[ 0 ] }
						venuePostType={ venuePostType }
					/>
				) }
				{ userCanEdit && (
					<Navigator.Button
						path="/new"
						variant="primary"
						text={ addNewItemLabel }
					/>
				) }
			</Navigator.Screen>

			<Navigator.Screen
				path="/new"
				style={ {
					padding: '.1em',
				} }
			>
				<CreateVenueForm { ...props } search={ search } />
			</Navigator.Screen>
		</NavigatorProvider>
	);
}
