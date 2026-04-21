/**
 * WordPress dependencies.
 */
import { useSelect, useDispatch, select } from '@wordpress/data';
import { store as coreStore } from '@wordpress/core-data';
// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
import { __unstableStripHTML as stripHTML } from '@wordpress/dom';
import { useCallback } from '@wordpress/element';

/**
 * Internal dependencies.
 */
import { isPostTypeSupporting } from '../../../helpers/event';
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

			// Check if the context post type is a venue via its registered supports.
			const contextPostIsVenue = !! contextPostId && isPostTypeSupporting( 'gatherpress-venue-information', context?.postType );

			// Only use contextPostId if it's actually a venue post.
			// Otherwise, check if we're editing a venue directly.
			let effectiveVenuePostId = 0;
			if ( contextPostIsVenue ) {
				effectiveVenuePostId = contextPostId;
			} else if ( isPostTypeSupporting( 'gatherpress-venue-information', currentPostType ) ) {
				effectiveVenuePostId = currentPostId;
			}

			return {
				venuePostId: effectiveVenuePostId,
				isEditingCurrentPost:
					currentPostId === effectiveVenuePostId &&
					isPostTypeSupporting( 'gatherpress-venue-information', currentPostType ),
			};
		},
		[ context?.postId, context?.postType ]
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

			let venueInfoJson;

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
					context?.postType,
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
		[ venuePostId, isEditingCurrentPost, context?.postType ]
	);

	const fieldValue = jsonFieldName ? venueInfo[ jsonFieldName ] || '' : '';

	// Generic function to update venue meta fields.
	// Accepts either (fieldName, value) or an object of { fieldName: value } pairs.
	const updateVenueField = useCallback(
		( fieldNameOrFields, newValue ) => {
			if ( ! venuePostId ) {
				return;
			}

			let venueInfoJson;

			if ( isEditingCurrentPost ) {
				const meta =
					select( 'core/editor' )?.getEditedPostAttribute( 'meta' ) ||
					{};
				venueInfoJson = meta?.gatherpress_venue_information || '{}';
			} else {
				const currentVenueInfo = select(
					coreStore
				)?.getEditedEntityRecord( 'postType', context?.postType, venuePostId );
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
				editEntityRecord( 'postType', context?.postType, venuePostId, {
					meta: {
						gatherpress_venue_information:
							JSON.stringify( updatedVenueInfo ),
					},
				} );
			}
		},
		[ venuePostId, isEditingCurrentPost, editEntityRecord, editPost, context?.postType ]
	);

	// Update the current field value (strips HTML tags).
	const updateFieldValue = useCallback(
		( newValue ) => {
			updateVenueField( jsonFieldName, stripHTML( newValue ) );
		},
		[ jsonFieldName, updateVenueField ]
	);

	// Update the website URL specifically (strips HTML tags).
	const updateWebsiteUrl = useCallback(
		( newValue ) => {
			updateVenueField( 'website', stripHTML( newValue ) );
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
