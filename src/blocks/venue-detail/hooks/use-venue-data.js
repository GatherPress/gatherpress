/**
 * WordPress dependencies.
 */
import { useSelect, useDispatch } from '@wordpress/data';
import { store as coreStore } from '@wordpress/core-data';
// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
import { __unstableStripHTML as stripHTML } from '@wordpress/dom';
import { useCallback } from '@wordpress/element';

/**
 * Internal dependencies.
 */
import { isPostTypeSupporting } from '../../../helpers/event';
import { getMetaKey } from '../helpers';

/**
 * Custom hook for managing venue data in the block editor.
 *
 * Handles fetching venue information and provides update functions
 * for modifying individual venue meta fields.
 *
 * @since 1.0.0
 *
 * @param {Object} context   - Block context containing postId.
 * @param {string} fieldType - The type of field (address, phone, url, text).
 * @return {Object} Venue data and update functions.
 */
export function useVenueData( context, fieldType ) {
	// Determine the venue post ID and whether we're editing the current post.
	const { venuePostId, isEditingCurrentPost } = useSelect(
		( selectData ) => {
			const currentPostId =
				selectData( 'core/editor' )?.getCurrentPostId();
			const currentPostType =
				selectData( 'core/editor' )?.getCurrentPostType();
			const contextPostId = context?.postId || 0;

			// Check if the context post type is a venue via its registered supports.
			const contextPostIsVenue = !! contextPostId && isPostTypeSupporting( 'gatherpress-venue', context?.postType );

			// Only use contextPostId if it's actually a venue post.
			// Otherwise, check if we're editing a venue directly.
			let effectiveVenuePostId = 0;
			if ( contextPostIsVenue ) {
				effectiveVenuePostId = contextPostId;
			} else if ( isPostTypeSupporting( 'gatherpress-venue', currentPostType ) ) {
				effectiveVenuePostId = currentPostId;
			}

			return {
				venuePostId: effectiveVenuePostId,
				isEditingCurrentPost:
					currentPostId === effectiveVenuePostId &&
					isPostTypeSupporting( 'gatherpress-venue', currentPostType ),
			};
		},
		[ context?.postId, context?.postType ]
	);

	// Map field type to its individual venue meta key.
	const metaKey = getMetaKey( fieldType );

	// Get dispatch functions for both stores.
	const { editEntityRecord } = useDispatch( coreStore );
	const { editPost } = useDispatch( 'core/editor' );

	// Read the live venue meta — either from the editor (if we're editing the
	// venue itself) or from the core entity record (if we're embedded in
	// another post like an event).
	const venueMeta = useSelect(
		( selectData ) => {
			if ( ! venuePostId ) {
				return {};
			}

			if ( isEditingCurrentPost ) {
				return (
					selectData( 'core/editor' )?.getEditedPostAttribute(
						'meta'
					) || {}
				);
			}

			const venuePost = selectData( coreStore ).getEditedEntityRecord(
				'postType',
				context?.postType,
				venuePostId
			);

			return venuePost?.meta || {};
		},
		[ venuePostId, isEditingCurrentPost, context?.postType ]
	);

	const fieldValue = metaKey ? venueMeta[ metaKey ] || '' : '';

	// Generic function to update one or more individual venue meta keys.
	const updateVenueField = useCallback(
		( meta ) => {
			if ( ! venuePostId ) {
				return;
			}

			if ( isEditingCurrentPost ) {
				editPost( { meta } );
			} else {
				editEntityRecord( 'postType', context?.postType, venuePostId, {
					meta,
				} );
			}
		},
		[ venuePostId, isEditingCurrentPost, editEntityRecord, editPost, context?.postType ]
	);

	// Update the current field value (strips HTML tags).
	const updateFieldValue = useCallback(
		( newValue ) => {
			if ( ! metaKey ) {
				return;
			}

			updateVenueField( { [ metaKey ]: stripHTML( newValue ) } );
		},
		[ metaKey, updateVenueField ]
	);

	// Update the website URL specifically (strips HTML tags).
	const updateWebsiteUrl = useCallback(
		( newValue ) => {
			updateVenueField( {
				gatherpress_website: stripHTML( newValue ),
			} );
		},
		[ updateVenueField ]
	);

	return {
		venuePostId,
		isEditingCurrentPost,
		venueMeta,
		fieldValue,
		updateVenueField,
		updateFieldValue,
		updateWebsiteUrl,
	};
}
