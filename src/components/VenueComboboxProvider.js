/**
 * Internal dependencies
 */
import { VenueTermsCombobox } from './VenueTermsCombobox';
import { VenuePostsCombobox } from './VenuePostsCombobox';
import { isPostTypeSupporting } from '../helpers/event';

export const VenueComboboxProvider = ( { search, setSearch, ...props } ) => {
	const isEventContext = isPostTypeSupporting( 'gatherpress-event-venue', props?.context?.postType );
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
