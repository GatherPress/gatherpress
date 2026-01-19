/**
 * WordPress dependencies
 */
import { __, sprintf } from '@wordpress/i18n';
import {
	Spinner,
	Button,
	TextControl,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalHStack as HStack,
	useNavigator,
} from '@wordpress/components';
import { useState } from '@wordpress/element';
import { useSelect } from '@wordpress/data';
import apiFetch from '@wordpress/api-fetch';
import { useEntityProp, store as coreDataStore } from '@wordpress/core-data';

/**
 * Internal dependencies
 */
import { PT_EVENT, PT_VENUE, TAX_VENUE } from '../helpers/namespace';
import { isEventPostType } from '../helpers/event';
import { getCurrentContextualPostId } from '../helpers/editor';

/**
 * Geocodes an address using Nominatim OpenStreetMap API.
 *
 * @param {string} address - The full address to geocode.
 * @return {Promise<Object>} Promise resolving to { latitude, longitude } or { latitude: '', longitude: '' } on error.
 */
async function geocodeAddress( address ) {
	if ( ! address || '' === address.trim() ) {
		return { latitude: '', longitude: '' };
	}

	try {
		const response = await fetch(
			`https://nominatim.openstreetmap.org/search?q=${ encodeURIComponent(
				address
			) }&format=geojson`
		);

		if ( ! response.ok ) {
			throw new Error(
				sprintf(
					/* translators: %s: Error message */
					__( 'Network response was not ok %s', 'gatherpress' ),
					response.statusText
				)
			);
		}

		const data = await response.json();

		if ( 0 < data.features.length ) {
			const latitude = String(
				data.features[ 0 ].geometry.coordinates[ 1 ]
			);
			const longitude = String(
				data.features[ 0 ].geometry.coordinates[ 0 ]
			);
			return { latitude, longitude };
		}

		// No results found.
		return { latitude: '', longitude: '' };
	} catch ( error ) {
		// eslint-disable-next-line no-console
		console.error( 'Geocoding error:', error );
		return { latitude: '', longitude: '' };
	}
}

function VenueForm( {
	title,
	onChangeTitle,
	address,
	onChangeAddress,
	hasEdits,
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
					__nextHasNoMarginBottom
					label={ __( 'Venue name', 'gatherpress' ) }
					value={ title }
					onChange={ onChangeTitle }
				/>
				<TextControl
					__next40pxDefaultSize
					label={ __( 'Full Address', 'gatherpress' ) }
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
					disabled={ ! hasEdits || isSaving }
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

function CreateVenueForm( { search, ...props } ) {
	const [ title, setTitle ] = useState( search );
	const [ address, setAddress ] = useState( '' );

	const { lastError, isSaving } = useSelect(
		( select ) => ( {
			lastError: select( coreDataStore ).getLastEntitySaveError(
				'postType',
				PT_VENUE
			),
			isSaving: select( coreDataStore ).isSavingEntityRecord(
				'postType',
				PT_VENUE
			),
		} ),
		[]
	);

	const cId = getCurrentContextualPostId( props?.context?.postId );

	const [ , updateVenueTaxonomyIds ] = useEntityProp(
		'postType',
		PT_EVENT,
		TAX_VENUE,
		cId
	);

	const { goTo } = useNavigator();
	const navigateBack = () => {
		goTo( '/', { isBack: true } );
	};

	const updateVenueDetailsBlockAttributes = ( postId, blockProps = null ) => {
		if ( 'undefined' !== typeof blockProps.setAttributes ) {
			const newAttributes = {
				...blockProps.attributes,
				selectedPostId: postId,
				selectedPostType: PT_VENUE,
			};
			blockProps.setAttributes( newAttributes );
		}
	};

	/**
	 * Creates a new venue post with the provided details.
	 *
	 * Uses the new individual meta fields architecture.
	 * Phone and website can be added later when editing the full venue post.
	 *
	 * @param {string} newTitle   - The title of the new venue.
	 * @param {string} newAddress - The address of the new venue.
	 * @param {string} latitude   - Latitude coordinate (from geocoding).
	 * @param {string} longitude  - Longitude coordinate (from geocoding).
	 * @return {Object} The newly created venue post.
	 */
	const createNewVenuePost = async (
		newTitle,
		newAddress,
		latitude = '',
		longitude = ''
	) => {
		try {
			const newPost = await apiFetch( {
				path: `/wp/v2/${ PT_VENUE }s`, // !! Watch out & beware of the 's' at the end. // @TODO Make this nicer.
				method: 'POST',
				data: {
					title,
					status: 'publish', // 'draft' is the default
					meta: {
						// Individual meta fields.
						gatherpress_venue_address: newAddress,
						gatherpress_venue_latitude: latitude,
						gatherpress_venue_longitude: longitude,
						// Phone and website can be added later via full venue editor.
						gatherpress_venue_phone: '',
						gatherpress_venue_website: '',
					},
				},
			} );

			// console.log(`${newPost.title.rendered} Venue saved successfully.`, newPost );
			return newPost;
		} catch ( error ) {
			// console.error('Error creating post:', error);
			throw error;
		}
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
				path: `/wp/v2/${ TAX_VENUE }?slug=${ newPostSlug }`,
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
		if ( isEventPostType() ) {
			// This should only run for the VenueTermsCombobox.
			await updateVenueTermOnEventPost();
		} else {
			// This should only run for the VenuePostsCombobox.
			await updateVenuePostOnBlockAttributes();
		}
		// In both cases, go home.
		navigateBack();
	};

	return (
		<VenueForm
			title={ title ?? '' }
			onChangeTitle={ setTitle }
			address={ address ?? '' }
			onChangeAddress={ setAddress }
			hasEdits={ !! title }
			onSave={ saveBogus }
			lastError={ lastError }
			onCancel={ navigateBack }
			isSaving={ isSaving }
		/>
	);
}

export default CreateVenueForm;
