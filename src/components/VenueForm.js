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
import { useSelect } from '@wordpress/data';
import apiFetch from '@wordpress/api-fetch';
import { useEntityProp, store as coreDataStore } from '@wordpress/core-data';

/**
 * Internal dependencies
 */
import { PT_EVENT, PT_VENUE, TAX_VENUE } from '../helpers/namespace';
import { isEventPostType } from '../helpers/event';
import { getCurrentContextualPostId } from '../helpers/editor';

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
					label={ __( 'Venue title', 'gatherpress' ) } // Would be nice to use apply_filters('enter_title_here) on this.
					value={ title }
					onChange={ onChangeTitle }
				/>
				<TextControl
					__next40pxDefaultSize
					__nextHasNoMarginBottom
					label={ __( 'Full Address', 'gatherpress' ) }
					value={ address }
					onChange={ onChangeAddress }
				/>
			</div>
			{ lastError ? (
				<div className="form-error">Error: { lastError.message }</div>
			) : (
				false
			) }
			<HStack justify="flex-start">
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
	const [ address, setAddress ] = useState();

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
	 * Creates a new venue post with the provided title and address.
	 *
	 * Have been & could also run,
	 * based on "const { saveEntityRecord } = useDispatch( coreDataStore )".
	 *
	 * @param {string} newTitle   - The title of the new venue.
	 * @param {string} newAddress - The address of the new venue.
	 * @return {Object} The newly created venue post.
	 */
	const createNewVenuePost = async ( newTitle, newAddress ) => {
		try {
			const newPost = await apiFetch( {
				path: `/wp/v2/${ PT_VENUE }s`, // !! Watch out & beware of the 's' at the end. // @TODO Make this nicer.
				method: 'POST',
				data: {
					title,
					status: 'publish', // 'draft' is the default
					meta: {
						// @TODO: Should become 'geo_address', when #560 is resolved!
						gatherpress_venue_information: JSON.stringify( {
							fullAddress: newAddress,
						} ),
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
			const newPost = await createNewVenuePost( title, address );
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
	 *
	 */
	const updateVenuePostOnBlockAttributes = async () => {
		try {
			const newPost = await createNewVenuePost( title, address );
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
