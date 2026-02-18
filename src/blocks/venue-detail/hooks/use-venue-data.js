/**
 * WordPress dependencies.
 */
import { useSelect, useDispatch, select } from '@wordpress/data';
import { store as coreStore } from '@wordpress/core-data';
import { useCallback } from '@wordpress/element';

/**
 * Internal dependencies.
 */
import { CPT_VENUE } from '../../../helpers/namespace';
import { getJsonFieldName } from '../helpers';

/**
 * Custom hook for managing venue data in the block editor.
 *
 * Handles fetching venue information and provides update functions
 * for modifying venue meta fields.
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

			// If we're editing a venue post directly and context doesn't provide a valid ID,
			// use the current post ID.
			const effectiveVenuePostId =
				contextPostId ||
				( currentPostType === CPT_VENUE ? currentPostId : 0 );

			return {
				venuePostId: effectiveVenuePostId,
				isEditingCurrentPost:
					currentPostId === effectiveVenuePostId &&
					currentPostType === CPT_VENUE,
			};
		},
		[ context?.postId ]
	);

	// Map field type to JSON field name.
	const jsonFieldName = getJsonFieldName( fieldType );

	// Get dispatch functions for both stores.
	const { editEntityRecord } = useDispatch( coreStore );
	const { editPost } = useDispatch( 'core/editor' );

	// Get venue info from meta.
	const venueInfo = useSelect(
		( selectData ) => {
			if ( ! venuePostId ) {
				return {};
			}

			let venueInfoJson = '{}';

			if ( isEditingCurrentPost ) {
				const meta =
					selectData( 'core/editor' )?.getEditedPostAttribute(
						'meta'
					) || {};
				venueInfoJson = meta?.gatherpress_venue_information || '{}';
			} else {
				const { getEditedEntityRecord } = selectData( coreStore );
				const venuePost = getEditedEntityRecord(
					'postType',
					CPT_VENUE,
					venuePostId
				);
				venueInfoJson =
					venuePost?.meta?.gatherpress_venue_information || '{}';
			}

			try {
				return JSON.parse( venueInfoJson );
			} catch ( e ) {
				return {};
			}
		},
		[ venuePostId, isEditingCurrentPost ]
	);

	const fieldValue = jsonFieldName ? venueInfo[ jsonFieldName ] || '' : '';

	// Generic function to update venue meta fields.
	// Accepts either (fieldName, value) or an object of { fieldName: value } pairs.
	const updateVenueField = useCallback(
		( fieldNameOrFields, newValue ) => {
			if ( ! venuePostId ) {
				return;
			}

			let venueInfoJson = '{}';

			if ( isEditingCurrentPost ) {
				const meta =
					select( 'core/editor' )?.getEditedPostAttribute( 'meta' ) ||
					{};
				venueInfoJson = meta?.gatherpress_venue_information || '{}';
			} else {
				const currentVenueInfo = select(
					coreStore
				)?.getEditedEntityRecord( 'postType', CPT_VENUE, venuePostId );
				venueInfoJson =
					currentVenueInfo?.meta?.gatherpress_venue_information ||
					'{}';
			}

			let updatedVenueInfo = {};
			try {
				updatedVenueInfo = JSON.parse( venueInfoJson );
			} catch ( e ) {
				updatedVenueInfo = {};
			}

			// Support both single field and multiple fields.
			if ( 'object' === typeof fieldNameOrFields ) {
				Object.assign( updatedVenueInfo, fieldNameOrFields );
			} else {
				updatedVenueInfo[ fieldNameOrFields ] = newValue;
			}

			if ( isEditingCurrentPost ) {
				editPost( {
					meta: {
						gatherpress_venue_information:
							JSON.stringify( updatedVenueInfo ),
					},
				} );
			} else {
				editEntityRecord( 'postType', CPT_VENUE, venuePostId, {
					meta: {
						gatherpress_venue_information:
							JSON.stringify( updatedVenueInfo ),
					},
				} );
			}
		},
		[ venuePostId, isEditingCurrentPost, editEntityRecord, editPost ]
	);

	// Update the current field value (strips HTML tags).
	const updateFieldValue = useCallback(
		( newValue ) => {
			const strippedValue = newValue.replace( /<[^>]*>/g, '' );
			updateVenueField( jsonFieldName, strippedValue );
		},
		[ jsonFieldName, updateVenueField ]
	);

	// Update the website URL specifically (strips HTML tags).
	const updateWebsiteUrl = useCallback(
		( newValue ) => {
			const strippedValue = newValue.replace( /<[^>]*>/g, '' );
			updateVenueField( 'website', strippedValue );
		},
		[ updateVenueField ]
	);

	return {
		venuePostId,
		isEditingCurrentPost,
		venueInfo,
		fieldValue,
		updateVenueField,
		updateFieldValue,
		updateWebsiteUrl,
	};
}
