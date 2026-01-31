/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import {
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalNavigatorProvider as NavigatorProvider,
	Navigator,
} from '@wordpress/components';
import { store as coreDataStore, useEntityProp } from '@wordpress/core-data';
import { useSelect } from '@wordpress/data';
import { useState, useCallback } from '@wordpress/element';

/**
 * Internal dependencies
 */
import { PT_VENUE, PT_EVENT, TAX_VENUE } from '../helpers/namespace';
import CreateVenueForm from './VenueForm';
import { VenueComboboxProvider } from './VenueComboboxProvider';
import PopularVenues from './PopularVenues';
import { isEventPostType } from '../helpers/event';
import { getCurrentContextualPostId } from '../helpers/editor';

/**
 *
 * @param {Object} props Properties of the 'gatherpress/venue-v2'-block.
 * @return {Component} A Navigator component to be rendered.
 */
export default function VenueNavigator( props = null ) {
	const addNewItemLabel = __( 'Add Venue', 'gatherpress' );

	/**
	 * Check if user can CREATE new venues.
	 *
	 * @todo Better use useResourcePermissions here!!
	 *       https://developer.wordpress.org/block-editor/reference-guides/packages/packages-core-data/#useresourcepermissions
	 */
	const userCanEdit = useSelect( ( select ) => {
		return select( coreDataStore ).canUser( 'create', PT_VENUE + 's' ); // needs to be plural, because canUser currently only supports resources in the wp/v2 namespace.
	}, [] );

	const [ search, setSearch ] = useState( '' );

	// Check if we're in an event context to show popular venues.
	// When used in panel context, props may be null, so check current editor post type.
	const isEventContext = props?.context?.postType
		? isEventPostType( props.context.postType )
		: isEventPostType();

	// Get current venue and update function for event context.
	const cId = getCurrentContextualPostId( props?.context?.postId );
	const [ venueTaxonomyIds, updateVenueTaxonomyIds ] = useEntityProp(
		'postType',
		PT_EVENT,
		TAX_VENUE,
		cId
	);

	// Handler for popular venue selection.
	const handlePopularVenueSelect = useCallback(
		( venueId ) => {
			if ( isEventContext && updateVenueTaxonomyIds ) {
				updateVenueTaxonomyIds( [ venueId ] );
			}
		},
		[ isEventContext, updateVenueTaxonomyIds ]
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
					/>
				) }
				{ userCanEdit && (
					<Navigator.Button
						path="/new"
						variant="link"
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
