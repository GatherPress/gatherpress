/**
 * Internal dependencies
 */
import { VenueTermsCombobox } from './VenueTermsCombobox';
import { VenuePostsCombobox } from './VenuePostsCombobox';
import { isEventPostType } from '../helpers/event';

export const VenueComboboxProvider = ( { search, setSearch, ...props } ) => {
	const isEventContext = isEventPostType( props?.context?.postType );
	return (
		<>
			{ isEventContext && (
				<VenueTermsCombobox
					{ ...props }
					search={ search }
					setSearch={ setSearch }
				/>
			) }
			{ ! isEventContext && (
				<VenuePostsCombobox
					{ ...props }
					search={ search }
					setSearch={ setSearch }
				/>
			) }
		</>
	);
};
