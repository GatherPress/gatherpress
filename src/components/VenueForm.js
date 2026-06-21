/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import {
	Spinner,
	Button,
	TextControl,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalHStack as HStack,
	useNavigator,
} from '@wordpress/components';
import { useState } from '@wordpress/element';
import { useSelect, useDispatch } from '@wordpress/data';
import apiFetch from '@wordpress/api-fetch';
import { store as coreDataStore } from '@wordpress/core-data';

/**
 * Internal dependencies
 */
import { getVenuePostType, getVenueTaxonomy } from '../helpers/venue';
import { isPostTypeSupporting } from '../helpers/event';
import { geocodeAddress } from '../helpers/geocoding';
import AddressAutocompleteField from './AddressAutocompleteField';

/**
 * Venue form component for creating or editing a venue.
 *
 * Renders a form with fields for venue name and address,
 * along with save and cancel buttons.
 *
 * @since 0.34.0
 *
 * @param {Object}   props                     Component props.
 * @param {string}   props.title               The venue title.
 * @param {Function} props.onChangeTitle       Callback when title changes.
 * @param {string}   props.titleError          Error message for title validation.
 * @param {string}   props.address             The venue address.
 * @param {Function} props.onChangeAddress     Callback when address changes.
 * @param {boolean}  props.hasEdits            Whether the form has been edited.
 * @param {boolean}  props.hasValidationErrors Whether there are validation errors.
 * @param {Object}   props.lastError           The last error from saving.
 * @param {boolean}  props.isSaving            Whether the form is currently saving.
 * @param {Function} props.onCancel            Callback when cancel is clicked.
 * @param {Function} props.onSave              Callback when save is clicked.
 *
 * @return {JSX.Element} The venue form component.
 */
function VenueForm( {
	title,
	onChangeTitle,
	titleError,
	address,
	onChangeAddress,
	hasEdits,
	hasValidationErrors,
	lastError,
	isSaving,
	onCancel,
	onSave,
} ) {
	return (
		<>
			<div className="gatherpress-new-venue-form">
				<TextControl
					__next40pxDefaultSize
					label={ __( 'Venue name', 'gatherpress' ) }
					value={ title }
					onChange={ onChangeTitle }
					help={ titleError }
					className={ titleError ? 'has-error' : '' }
				/>
				<AddressAutocompleteField
					variant="settings"
					value={ address }
					onChange={ onChangeAddress }
					help={ __(
						'Address will be automatically geocoded for map display.',
						'gatherpress'
					) }
				/>
			</div>
			{ lastError ? (
				<div className="form-error">Error: { lastError.message }</div>
			) : (
				false
			) }
			<HStack justify="flex-start" style={ { marginTop: '1rem' } }>
				<Button
					onClick={ onSave }
					variant="primary"
					disabled={ ! hasEdits || hasValidationErrors || isSaving }
				>
					{ isSaving ? (
						<>
							<Spinner />
							{ __( 'Saving', 'gatherpress' ) }
						</>
					) : (
						__( 'Save', 'gatherpress' )
					) }
				</Button>
				<Button
					onClick={ onCancel }
					variant="tertiary"
					disabled={ isSaving }
				>
					{ __( 'Back', 'gatherpress' ) }
				</Button>
			</HStack>
		</>
	);
}

/**
 * Create venue form component with state management.
 *
 * Handles the complete workflow of creating a new venue post,
 * including geocoding the address and updating event relationships.
 *
 * @since 0.34.0
 *
 * @param {Object} props        Component props.
 * @param {string} props.search Initial search text to populate the title.
 *
 * @return {JSX.Element} The create venue form component.
 */
function CreateVenueForm( { search, ...props } ) {
	const [ title, setTitle ] = useState( search );
	const [ address, setAddress ] = useState( '' );
	const [ titleError, setTitleError ] = useState( '' );

	// Use context post type if provided, otherwise fall back to the editor's current post type.
	// This is necessary because CreateVenueForm can be rendered from slotfill.js without any props.
	const currentPostType = useSelect(
		( select ) =>
			props?.context?.postType ||
			select( 'core/editor' )?.getCurrentPostType(),
		[ props?.context?.postType ]
	);
	const venuePostType = getVenuePostType( currentPostType );
	const venueTaxonomy = getVenueTaxonomy( venuePostType );

	const { lastError, isSaving } = useSelect(
		( select ) => ( {
			lastError: select( coreDataStore ).getLastEntitySaveError(
				'postType',
				venuePostType
			),
			isSaving: select( coreDataStore ).isSavingEntityRecord(
				'postType',
				venuePostType
			),
		} ),
		[ venuePostType ]
	);

	/**
	 * Validates the venue title.
	 *
	 * @param {string} value - The title value to validate.
	 *
	 * @return {string} Error message if validation fails, empty string if valid.
	 */
	const validateTitle = ( value ) => {
		if ( ! value || '' === value.trim() ) {
			return __( 'Venue name is required.', 'gatherpress' );
		}
		if ( 2 > value.trim().length ) {
			return __(
				'Venue name must be at least 2 characters.',
				'gatherpress'
			);
		}
		return '';
	};

	/**
	 * Handles title change with validation.
	 *
	 * @param {string} value - The new title value.
	 */
	const handleTitleChange = ( value ) => {
		setTitle( value );
		const error = validateTitle( value );
		setTitleError( error );
	};

	const venueRestBase = useSelect(
		( select ) => {
			const venuePostTypeObj = select( 'core' ).getPostType( venuePostType );
			return venuePostTypeObj?.rest_base || venuePostType + 's';
		},
		[ venuePostType ]
	);

	const { editPost } = useDispatch( 'core/editor' );
	const updateVenueTaxonomyIds = ( newIds ) =>
		editPost( { [ venueTaxonomy ]: newIds } );

	const { goTo } = useNavigator();

	/**
	 * Navigates back to the main venue selection screen.
	 */
	const navigateBack = () => {
		goTo( '/', { isBack: true } );
	};

	/**
	 * Updates the venue block attributes with the newly created venue post ID.
	 *
	 * @param {number} postId     The ID of the newly created venue post.
	 * @param {Object} blockProps Block props containing setAttributes function.
	 */
	const updateVenueDetailsBlockAttributes = ( postId, blockProps = null ) => {
		if ( 'undefined' !== typeof blockProps.setAttributes ) {
			const newAttributes = {
				...blockProps.attributes,
				selectedPostId: postId,
				selectedPostType: venuePostType,
			};
			blockProps.setAttributes( newAttributes );
		}
	};

	/**
	 * Creates a new venue post with the provided details.
	 *
	 * Uses the new individual meta fields architecture.
	 * Phone and website can be added later when editing the full venue post.
	 * Structured-address fields (city, state, postcode, etc.) are populated
	 * server-side by an async cron handler that fires when `gatherpress_address`
	 * changes — they don't need to be sent in this initial POST.
	 *
	 * @param {string} newTitle   - The title of the new venue.
	 * @param {string} newAddress - The address of the new venue.
	 * @param {string} latitude   - Latitude coordinate (from geocoding).
	 * @param {string} longitude  - Longitude coordinate (from geocoding).
	 *
	 * @return {Promise<Object>} A promise that resolves to the newly created venue post.
	 */
	const createNewVenuePost = async (
		newTitle,
		newAddress,
		latitude = '',
		longitude = ''
	) => {
		return apiFetch( {
			path: `/wp/v2/${ venueRestBase }`,
			method: 'POST',
			data: {
				title,
				status: 'publish', // 'draft' is the default
				meta: {
					gatherpress_address: newAddress,
					gatherpress_latitude: latitude,
					gatherpress_longitude: longitude,
				},
			},
		} );
	};

	/**
	 * Fetches the term based on the slug of the newly created venue post and updates the currently edited event with the venue taxonomy term.
	 *
	 * @param {string}   newPostSlug              The slug of the newly created venue post.
	 * @param {Function} wpUpdateVenueTaxonomyIds Callback to update the venue taxonomy of the currently edited event with the given terms.
	 */
	const fetchTermAndUpdateEvent = async (
		newPostSlug,
		wpUpdateVenueTaxonomyIds
	) => {
		try {
			const terms = await apiFetch( {
				path: `/wp/v2/${ venueTaxonomy }?slug=${ newPostSlug }`,
			} );

			if ( 0 < terms.length ) {
				const term = terms[ 0 ];
				// Update the currently edited event with the venue taxonomy term.
				wpUpdateVenueTaxonomyIds( [ term.id ] );
			}
		} catch ( error ) {
			// eslint-disable-next-line no-console
			console.error( 'Error fetching term:', error );
		}
	};

	/**
	 * A functional component for the block editor that handles the complete process of
	 * creating a new venue post and updating the event post with the venue term.
	 */
	const updateVenueTermOnEventPost = async () => {
		try {
			// Geocode the address to get lat/long coordinates.
			const { latitude, longitude } = await geocodeAddress( address );

			const newPost = await createNewVenuePost(
				title,
				address,
				latitude,
				longitude
			);
			const newPostSlug = '_' + newPost.slug;
			await fetchTermAndUpdateEvent( newPostSlug, updateVenueTaxonomyIds );
		} catch ( error ) {
			// eslint-disable-next-line no-console
			console.error(
				'Error in the updateVenueTermOnEventPost process:',
				error
			);
		}
	};

	/**
	 * Updates the venue post on block attributes when saving from non-event context.
	 */
	const updateVenuePostOnBlockAttributes = async () => {
		try {
			// Geocode the address to get lat/long coordinates.
			const { latitude, longitude } = await geocodeAddress( address );

			const newPost = await createNewVenuePost(
				title,
				address,
				latitude,
				longitude
			);
			updateVenueDetailsBlockAttributes( newPost.id, props );
		} catch ( error ) {
			// eslint-disable-next-line no-console
			console.error(
				'Error in the updateVenuePostOnBlockAttributes process:',
				error
			);
		}
	};

	/**
	 * Function to handle the save action for the venue form.
	 * This function is called when the save button is clicked.
	 */
	const saveBogus = async () => {
		if ( isPostTypeSupporting( 'gatherpress-venue' ) ) {
			// This should only run for the VenueTermsCombobox.
			await updateVenueTermOnEventPost();
		} else {
			// This should only run for the VenuePostsCombobox.
			await updateVenuePostOnBlockAttributes();
		}
		// In both cases, go home.
		navigateBack();
	};

	const hasValidationErrors = !! titleError;

	return (
		<VenueForm
			title={ title ?? '' }
			onChangeTitle={ handleTitleChange }
			titleError={ titleError }
			address={ address ?? '' }
			onChangeAddress={ setAddress }
			hasEdits={ !! title }
			hasValidationErrors={ hasValidationErrors }
			onSave={ saveBogus }
			lastError={ lastError }
			onCancel={ navigateBack }
			isSaving={ isSaving }
		/>
	);
}

export default CreateVenueForm;
