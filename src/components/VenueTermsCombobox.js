/**
 * WordPress dependencies
 */
import { ComboboxControl } from '@wordpress/components';
import { useEntityProp } from '@wordpress/core-data';
import { useCallback } from '@wordpress/element';
import { useDebounce } from '@wordpress/compose';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies.
 */
import { getCurrentContextualPostId } from '../helpers/editor';
import { useVenueOptions } from '../helpers/venue';
import { CPT_EVENT, TAX_VENUE } from '../helpers/namespace';

/**
 * VenueTermsCombobox component.
 *
 * Renders a searchable ComboboxControl
 * for selecting a venue (taxonomy term) for the current GatherPress event.
 *
 * It retrieves the current contextual event post ID,
 * reads and updates the `_gatherpress_venue` taxonomy term IDs
 * attached to that event, and fetches available venue options for selection.
 *
 * @since 1.0.0
 *
 * @param {Object}   props           Properties of the 'gatherpress/venue-v2'-block.
 * @param {string}   props.search    Current search string for venue filtering.
 * @param {Function} props.setSearch Function to update the search string.
 *
 * @return {JSX.Element} Venue term selection combobox control.
 */
export const VenueTermsCombobox = ( { search, setSearch, ...props } ) => {
	// Get the current contextual post ID, falling back to editor post ID if not passed.
	const cId = getCurrentContextualPostId( props?.context?.postId );
	const [ venueTaxonomyIds, updateVenueTaxonomyIds ] = useEntityProp(
		'postType',
		CPT_EVENT,
		TAX_VENUE,
		cId
	);

	// The currently selected venue term ID (if any).
	const venueId = venueTaxonomyIds?.[ 0 ];
	const { venueOptions } = useVenueOptions( search, venueId );

	/**
	 * Debounced setter for the search input to avoid excessive queries.
	 *
	 * @param {string} value The search term entered by the user.
	 */
	const setSearchDebounced = useDebounce( ( value ) => {
		setSearch( value );
	}, 300 );

	/**
	 * Updates the event's '_gatherpress_venue' taxonomy term relationship.
	 *
	 * @param {number|*} value The selected venue term ID,
	 *                         or other value if "Choose a venue" was selected
	 */
	const update = useCallback(
		( value ) => {
			const save = Number.isFinite( value ) ? [ value ] : [];
			updateVenueTaxonomyIds( save );
		},
		[ updateVenueTaxonomyIds ]
	);

	/**
	 * Determines the current value for the combobox.
	 *
	 * @return {number|string} The currently selected venue term ID or 'loading'.
	 */
	const setValue = () => {
		return venueTaxonomyIds?.[ 0 ] || 'loading';
	};

	return (
		<>
			<ComboboxControl
				label={ __( 'Choose a venue', 'gatherpress' ) }
				__next40pxDefaultSize
				onChange={ update }
				onFilterValueChange={ setSearchDebounced }
				options={ venueOptions }
				value={ setValue() }
			/>
		</>
	);
};
