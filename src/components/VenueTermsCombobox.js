/**
 * WordPress dependencies
 */
import { ComboboxControl } from '@wordpress/components';
import { useEntityProp } from '@wordpress/core-data';
import { useSelect } from '@wordpress/data';
import { useCallback, useMemo } from '@wordpress/element';
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

	// Get the online-event term to exclude it from venue selection.
	const onlineEventTermId = useSelect( ( wpSelect ) => {
		const terms = wpSelect( 'core' ).getEntityRecords( 'taxonomy', TAX_VENUE, {
			slug: 'online-event',
			per_page: 1,
		} );
		return terms?.[ 0 ]?.id || null;
	}, [] );

	// Filter out the online-event term to get only physical venue IDs.
	const physicalVenueIds = useMemo( () => {
		if ( ! venueTaxonomyIds || ! onlineEventTermId ) {
			return venueTaxonomyIds || [];
		}
		const onlineIdStr = String( onlineEventTermId );
		return venueTaxonomyIds.filter( ( id ) => String( id ) !== onlineIdStr );
	}, [ venueTaxonomyIds, onlineEventTermId ] );

	// The currently selected physical venue term ID (if any).
	const venueId = physicalVenueIds?.[ 0 ];
	const { venueOptions } = useVenueOptions( search, venueId );

	/**
	 * Debounced setter for the search input to avoid excessive queries.
	 *
	 * @param {string} value The search term entered by the user.
	 */
	const setSearchDebounced = useDebounce( ( value ) => {
		setSearch( value );
	}, 300 );

	// Check if online-event term is currently assigned.
	const hasOnlineEventTerm = useMemo( () => {
		if ( ! venueTaxonomyIds || ! onlineEventTermId ) {
			return false;
		}
		const onlineIdStr = String( onlineEventTermId );
		return venueTaxonomyIds.some( ( id ) => String( id ) === onlineIdStr );
	}, [ venueTaxonomyIds, onlineEventTermId ] );

	/**
	 * Updates the event's '_gatherpress_venue' taxonomy term relationship.
	 * Preserves the online-event term if it's currently assigned.
	 *
	 * @param {number|*} value The selected venue term ID,
	 *                         or other value if "Choose a venue" was selected.
	 */
	const update = useCallback(
		( value ) => {
			let save = [];
			if ( Number.isFinite( value ) ) {
				save = [ value ];
			}
			// Preserve online-event term if it was set.
			if ( hasOnlineEventTerm && onlineEventTermId ) {
				save = [ ...save, onlineEventTermId ];
			}
			updateVenueTaxonomyIds( save );
		},
		[ updateVenueTaxonomyIds, hasOnlineEventTerm, onlineEventTermId ]
	);

	/**
	 * Determines the current value for the combobox.
	 *
	 * @return {number|string} The currently selected physical venue term ID or 'loading'.
	 */
	const setValue = () => {
		return physicalVenueIds?.[ 0 ] || 'loading';
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
