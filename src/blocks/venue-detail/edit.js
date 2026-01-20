/**
 * WordPress dependencies.
 */
import { __, sprintf } from '@wordpress/i18n';
import {
	InspectorControls,
	RichText,
	useBlockProps,
	store as blockEditorStore,
} from '@wordpress/block-editor';
import { PanelBody, SelectControl } from '@wordpress/components';
import { useSelect, useDispatch, select } from '@wordpress/data';
import { store as coreStore } from '@wordpress/core-data';
import { createBlock } from '@wordpress/blocks';
import { useCallback, useEffect, useRef } from '@wordpress/element';
import { useDebounce } from '@wordpress/compose';

/**
 * Internal dependencies.
 */
import { PT_VENUE } from '../../helpers/namespace';

/**
 * Edit component for the Venue Detail block.
 *
 * Provides inline editing of venue meta fields with automatic
 * cross-post editing warnings (like post-title block).
 *
 * @since 1.0.0
 *
 * @param {Object}   props                   - Component properties.
 * @param {Object}   props.attributes        - Block attributes.
 * @param {Function} props.setAttributes     - Function to set block attributes.
 * @param {Object}   props.context           - Block context.
 * @param {string}   props.clientId          - Block client ID.
 * @param {Function} props.insertBlocksAfter - Function to insert blocks after this block.
 *
 * @return {JSX.Element} The rendered React component.
 */
const Edit = ( { attributes, setAttributes, context, clientId, insertBlocksAfter } ) => {
	const { placeholder, fieldType } = attributes;
	const blockProps = useBlockProps();

	// Get the venue post ID from context (provided by venue-v2 block).
	const venuePostId = context?.postId || 0;

	// Check if we're editing the current post or a different venue post.
	const { isEditingCurrentPost } = useSelect(
		( selectData ) => {
			const currentPostId = selectData( 'core/editor' ).getCurrentPostId();
			const currentPostType = selectData( 'core/editor' ).getCurrentPostType();
			return {
				isEditingCurrentPost: currentPostId === venuePostId && currentPostType === PT_VENUE,
			};
		},
		[ venuePostId ]
	);

	// Map field type to JSON field name.
	const fieldMapping = {
		address: 'fullAddress',
		phone: 'phoneNumber',
		url: 'website',
	};

	const jsonFieldName = fieldMapping[ fieldType ] || '';

	// Get dispatch functions for both stores.
	const { editEntityRecord } = useDispatch( coreStore );
	const { editPost } = useDispatch( 'core/editor' );

	// Block insertion for Enter key handling at beginning.
	const { insertBlocks, selectBlock } = useDispatch( blockEditorStore );
	const { getBlockRootClientId, getBlockIndex } = useSelect(
		( selectEditor ) => selectEditor( blockEditorStore ),
		[]
	);

	const fieldValue = useSelect(
		( selectData ) => {
			if ( ! venuePostId || ! jsonFieldName ) {
				return '';
			}

			let venueInfoJson = '{}';

			if ( isEditingCurrentPost ) {
				// Read from core/editor store for the current post being edited.
				const meta = selectData( 'core/editor' ).getEditedPostAttribute( 'meta' ) || {};
				venueInfoJson = meta.gatherpress_venue_information || '{}';
			} else {
				// Read from core store for a different venue post.
				const { getEditedEntityRecord } = selectData( coreStore );
				const venuePost = getEditedEntityRecord(
					'postType',
					PT_VENUE,
					venuePostId
				);
				venueInfoJson = venuePost?.meta?.gatherpress_venue_information || '{}';
			}

			// Parse venue information from JSON field.
			let venueInfo = {};
			try {
				venueInfo = JSON.parse( venueInfoJson );
			} catch ( e ) {
				venueInfo = {};
			}

			return venueInfo[ jsonFieldName ] || '';
		},
		[ venuePostId, jsonFieldName, isEditingCurrentPost ]
	);

	const updateFieldValue = useCallback(
		( newValue ) => {
			// Strip any HTML tags from the value (plain text only).
			const strippedValue = newValue.replace( /<[^>]*>/g, '' );

			let venueInfoJson = '{}';

			if ( isEditingCurrentPost ) {
				// Read from core/editor store for the current post.
				const meta = select( 'core/editor' ).getEditedPostAttribute( 'meta' ) || {};
				venueInfoJson = meta.gatherpress_venue_information || '{}';
			} else {
				// Read from core store for a different venue post.
				const currentVenueInfo = select( coreStore ).getEditedEntityRecord(
					'postType',
					PT_VENUE,
					venuePostId
				);
				venueInfoJson = currentVenueInfo?.meta?.gatherpress_venue_information || '{}';
			}

			let venueInfo = {};
			try {
				venueInfo = JSON.parse( venueInfoJson );
			} catch ( e ) {
				venueInfo = {};
			}

			// Update the specific field.
			venueInfo[ jsonFieldName ] = strippedValue;

			if ( isEditingCurrentPost ) {
				// Use editPost for current post (same store as VenueInformation.js).
				editPost( {
					meta: {
						gatherpress_venue_information: JSON.stringify( venueInfo ),
					},
				} );
			} else {
				// Use editEntityRecord for different venue post.
				editEntityRecord( 'postType', PT_VENUE, venuePostId, {
					meta: {
						gatherpress_venue_information: JSON.stringify( venueInfo ),
					},
				} );
			}
		},
		[ jsonFieldName, venuePostId, isEditingCurrentPost, editEntityRecord, editPost ]
	);

	// Get dispatch functions for venue store.
	const { updateVenueLatitude, updateVenueLongitude } = useDispatch( 'gatherpress/venue' );

	// Get mapCustomLatLong setting from venue store.
	const { mapCustomLatLong } = useSelect(
		( selectData ) => ( {
			mapCustomLatLong: selectData( 'gatherpress/venue' ).getMapCustomLatLong(),
		} ),
		[]
	);

	// Track address for geocoding.
	const addressRef = useRef( 'address' === fieldType ? fieldValue : '' );
	if ( 'address' === fieldType ) {
		addressRef.current = fieldValue;
	}

	// Geocode address field changes.
	const geocodeAddress = useCallback( () => {
		const address = addressRef.current;

		if ( 'address' !== fieldType ) {
			return;
		}

		// If address is empty, clear lat/long.
		if ( ! address ) {
			if ( ! mapCustomLatLong ) {
				// Clear venue store for live preview.
				updateVenueLatitude( '' );
				updateVenueLongitude( '' );

				// Clear meta for the venue post.
				let venueInfoJson = '{}';
				let venueInfo = {};

				if ( isEditingCurrentPost ) {
					// Read from core/editor store for the current post.
					const meta = select( 'core/editor' ).getEditedPostAttribute( 'meta' ) || {};
					venueInfoJson = meta.gatherpress_venue_information || '{}';
				} else {
					// Read from core store for a different venue post.
					const currentVenueInfo = select( coreStore ).getEditedEntityRecord(
						'postType',
						PT_VENUE,
						venuePostId
					);
					venueInfoJson = currentVenueInfo?.meta?.gatherpress_venue_information || '{}';
				}

				try {
					venueInfo = JSON.parse( venueInfoJson );
				} catch ( e ) {
					venueInfo = {};
				}

				venueInfo.latitude = '';
				venueInfo.longitude = '';

				if ( isEditingCurrentPost ) {
					// Use editPost for current post.
					editPost( {
						meta: {
							gatherpress_venue_information: JSON.stringify( venueInfo ),
						},
					} );
				} else {
					// Use editEntityRecord for different venue post.
					editEntityRecord( 'postType', PT_VENUE, venuePostId, {
						meta: {
							gatherpress_venue_information: JSON.stringify( venueInfo ),
						},
					} );
				}
			}
			return;
		}

		fetch(
			`https://nominatim.openstreetmap.org/search?q=${ encodeURIComponent( address ) }&format=json&addressdetails=1`,
		)
			.then( ( response ) => {
				if ( ! response.ok ) {
					throw new Error(
						sprintf(
							/* translators: %s: Error message */
							__( 'Network response was not ok %s', 'gatherpress' ),
							response.statusText,
						),
					);
				}
				return response.json();
			} )
			.then( ( data ) => {
				let lat = null;
				let lng = null;
				let addressComponents = null;

				if ( 0 < data.length ) {
					lat = data[ 0 ].lat;
					lng = data[ 0 ].lon;
					addressComponents = data[ 0 ].address || null;
				}

				if ( ! mapCustomLatLong ) {
					// Update venue store for live preview.
					updateVenueLatitude( lat );
					updateVenueLongitude( lng );

					// Update meta for the venue post.
					let venueInfoJson = '{}';
					let venueInfo = {};

					if ( isEditingCurrentPost ) {
						// Read from core/editor store for the current post.
						const meta = select( 'core/editor' ).getEditedPostAttribute( 'meta' ) || {};
						venueInfoJson = meta.gatherpress_venue_information || '{}';
					} else {
						// Read from core store for a different venue post.
						const currentVenueInfo = select( coreStore ).getEditedEntityRecord(
							'postType',
							PT_VENUE,
							venuePostId
						);
						venueInfoJson = currentVenueInfo?.meta?.gatherpress_venue_information || '{}';
					}

					try {
						venueInfo = JSON.parse( venueInfoJson );
					} catch ( e ) {
						venueInfo = {};
					}

					venueInfo.latitude = lat ? String( lat ) : '';
					venueInfo.longitude = lng ? String( lng ) : '';

					// Save address components if available.
					if ( addressComponents ) {
						venueInfo.address = addressComponents;
					}

					if ( isEditingCurrentPost ) {
						// Use editPost for current post.
						editPost( {
							meta: {
								gatherpress_venue_information: JSON.stringify( venueInfo ),
							},
						} );
					} else {
						// Use editEntityRecord for different venue post.
						editEntityRecord( 'postType', PT_VENUE, venuePostId, {
							meta: {
								gatherpress_venue_information: JSON.stringify( venueInfo ),
							},
						} );
					}
				}
			} )
			.catch( ( error ) => {
				// eslint-disable-next-line no-console
				console.warn( '[VenueDetail] Geocoding failed:', error );
			} );
	}, [ fieldType, venuePostId, isEditingCurrentPost, mapCustomLatLong, updateVenueLatitude, updateVenueLongitude, editPost, editEntityRecord ] );

	const debouncedGeocode = useDebounce( geocodeAddress, 300 );

	// Trigger geocoding when address field value changes.
	useEffect( () => {
		if ( 'address' === fieldType ) {
			debouncedGeocode();
		}
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ fieldValue, fieldType ] );

	// Render different field types with appropriate HTML elements.
	const renderEditableField = () => {
		const placeholderText =
			placeholder || __( 'Venue detail…', 'gatherpress' );

		// Common RichText props.
		const richTextProps = {
			value: fieldValue,
			onChange: updateFieldValue,
			placeholder: placeholderText,
			allowedFormats: [], // Plain text only.
			onKeyDown: ( event ) => {
				if ( 'Enter' === event.key && ! event.shiftKey ) {
					// Always prevent default to avoid line break/snap behavior.
					event.preventDefault();

					const contentElement = event.currentTarget;
					const selection =
						contentElement.ownerDocument.defaultView.getSelection();
					if ( ! selection.rangeCount ) {
						return;
					}

					const range = selection.getRangeAt( 0 );
					const textContent = contentElement.textContent || '';

					// Calculate cursor position.
					const preRange = document.createRange();
					preRange.selectNodeContents( contentElement );
					preRange.setEnd( range.startContainer, range.startOffset );
					const cursorPosition = preRange.toString().length;

					// At the beginning.
					if ( 0 === cursorPosition ) {
						const newBlock = createBlock( 'core/paragraph' );
						const rootClientId = getBlockRootClientId( clientId );
						const blockIndex = getBlockIndex( clientId );
						// Insert at the current block's index (pushes current block down).
						insertBlocks( newBlock, blockIndex, rootClientId );
						// Select the newly created block to move focus to it.
						selectBlock( newBlock.clientId );
					} else if ( cursorPosition === textContent.length ) {
						// At the end.
						const newBlock = createBlock( 'core/paragraph' );
						insertBlocksAfter( [ newBlock ] );
					}
					// In the middle - do nothing (already prevented default).
				}
			},
		};

		switch ( fieldType ) {
			case 'address':
				return (
					<RichText
						{ ...richTextProps }
						tagName="address"
						className="gatherpress-venue-detail__address"
						style={ { display: 'inline' } }
					/>
				);

			case 'phone':
				// Render as a link with tel: href.
				return fieldValue ? (
					<RichText
						{ ...richTextProps }
						tagName="a"
						href={ `tel:${ fieldValue }` }
						className="gatherpress-venue-detail__phone"
						onClick={ ( e ) => e.preventDefault() }
					/>
				) : (
					<RichText
						{ ...richTextProps }
						tagName="span"
						className="gatherpress-venue-detail__phone"
					/>
				);

			case 'url':
				// Render as a link.
				return fieldValue ? (
					<RichText
						{ ...richTextProps }
						tagName="a"
						href={ fieldValue }
						target="_blank"
						rel="noopener noreferrer"
						className="gatherpress-venue-detail__url"
						onClick={ ( e ) => e.preventDefault() }
					/>
				) : (
					<RichText
						{ ...richTextProps }
						tagName="span"
						className="gatherpress-venue-detail__url"
					/>
				);

			default:
				return (
					<RichText
						{ ...richTextProps }
						tagName="div"
						className="gatherpress-venue-detail__text"
					/>
				);
		}
	};

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Field settings', 'gatherpress' ) }>
					<SelectControl
						label={ __( 'Field type', 'gatherpress' ) }
						value={ fieldType }
						options={ [
							{
								label: __( 'Text', 'gatherpress' ),
								value: 'text',
							},
							{
								label: __( 'Address', 'gatherpress' ),
								value: 'address',
							},
							{
								label: __( 'Phone', 'gatherpress' ),
								value: 'phone',
							},
							{
								label: __( 'URL', 'gatherpress' ),
								value: 'url',
							},
						] }
						onChange={ ( value ) =>
							setAttributes( { fieldType: value } )
						}
						help={ __(
							'Choose how this field should be displayed and formatted.',
							'gatherpress'
						) }
					/>
				</PanelBody>
			</InspectorControls>
			<div { ...blockProps }>{ renderEditableField() }</div>
		</>
	);
};

export default Edit;
