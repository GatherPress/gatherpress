/**
 * WordPress dependencies
 */
import {
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalNavigatorProvider as NavigatorProvider,
	Navigator,
} from '@wordpress/components';
import { store as coreDataStore } from '@wordpress/core-data';
import { useSelect } from '@wordpress/data';
import { useState } from '@wordpress/element';

/**
 * Internal dependencies
 */
import { PT_VENUE } from '../helpers/namespace';
import CreateVenueForm from './VenueForm';
import { VenueComboboxProvider } from './VenueComboboxProvider';

/**
 *
 * @param {Object} props Properties of the 'gatherpress/venue-v2'-block.
 * @return {Component} A Navigator component to be rendered.
 */
export default function VenueNavigator( props = null ) {
	const addNewItemLabel = useSelect( ( select ) => {
		const { getPostType } = select( coreDataStore );
		return getPostType( PT_VENUE )?.labels?.add_new_item;
	}, [] );

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
				{ userCanEdit && (
					<Navigator.Button
						path="/new"
						variant="tertiary"
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
