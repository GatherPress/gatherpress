/**
 * WordPress dependencies
 */
import { ComboboxControl } from '@wordpress/components';
import { useCallback } from '@wordpress/element';
import { useDebounce } from '@wordpress/compose';
import { __, sprintf } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { usePostTypeLabel } from '../helpers/editor';
import { useVenueOptions, getVenuePostType } from '../helpers/venue';

/**
 * VenuePostsCombobox component.
 *
 * Renders a searchable combobox for selecting a 'gatherpress_venue' post.
 * Fetches available venues as options based on the search input,
 * and updates block attributes when a new venue is selected.
 *
 * @since 0.34.0
 *
 * @param {Object}   props           Properties of the 'gatherpress/venue'-block.
 * @param {string}   props.search    Current search string for venue filtering.
 * @param {Function} props.setSearch Function to update the search string.
 *
 * @return {JSX.Element} Venue post selection combobox control.
 */
export const VenuePostsCombobox = ( { search, setSearch, ...props } ) => {
	// Get the currently selected venue post ID from block attributes.
	const venueId = props?.attributes?.selectedPostId;

	const venuePostType = getVenuePostType( props?.context?.postType );

	// Read the singular label so the panel title reflects what the post type
	// is actually called — a custom venue post type with
	// `singular_name => 'Location'` shows "Add New Location" without any
	// extra wiring (#1612).
	const singularLabel = usePostTypeLabel(
		'singular_name',
		venuePostType,
		__( 'Venue', 'gatherpress' )
	);
	const comboBoxLabel = sprintf(
		/* translators: %s: Singular post type label, e.g. "Venue". */
		__( 'Choose a %s', 'gatherpress' ),
		singularLabel
	);

	// Fetch available venue options using a custom query hook.
	const { venueOptions } = useVenueOptions(
		search,
		venueId,
		'postType',
		venuePostType
	);

	/**
	 * Updates the block attributes when a new venue is selected.
	 *
	 * @param {number|string} value The ID of the newly selected venue.
	 */
	const update = useCallback(
		( value ) => {
			// Setup the 'gatherpress_venue' post to provide context for,
			// after a new 'gatherpress_venue' post was selected.
			const newAttributes = {
				...props.attributes,
				selectedPostId: value,
				selectedPostType: venuePostType,
			};
			props.setAttributes( newAttributes );
		},
		[ props, venuePostType ]
	);

	/**
	 * Debounced setter for the search input to avoid excessive queries.
	 *
	 * @param {string} value The search term entered by the user.
	 */
	const setSearchDebounced = useDebounce( ( value ) => {
		setSearch( value );
	}, 300 );

	/**
	 * Determines the current value for the combobox.
	 *
	 * @return {number|string} The currently selected venue post ID or 'loading'.
	 */
	const setValue = () => {
		return props?.attributes?.selectedPostId || 'loading';
	};

	return (
		<ComboboxControl
			label={ comboBoxLabel }
			__next40pxDefaultSize
			onChange={ update }
			onFilterValueChange={ setSearchDebounced }
			options={ venueOptions }
			value={ setValue() }
		/>
	);
};
