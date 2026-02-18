/**
 * WordPress dependencies.
 */
import { __, sprintf } from '@wordpress/i18n';
import {
	BlockControls,
	InspectorControls,
	RichText,
	useBlockProps,
	store as blockEditorStore,
} from '@wordpress/block-editor';
import {
	PanelBody,
	Popover,
	SelectControl,
	ToggleControl,
	ToolbarButton,
	ToolbarGroup,
} from '@wordpress/components';
import { useSelect, useDispatch, select } from '@wordpress/data';
import { store as coreStore } from '@wordpress/core-data';
import { createBlock } from '@wordpress/blocks';
import { useCallback, useEffect, useRef, useState } from '@wordpress/element';
import { useDebounce } from '@wordpress/compose';
import { link as linkIcon } from '@wordpress/icons';

/**
 * Internal dependencies.
 */
import { CPT_VENUE } from '../../helpers/namespace';

/**
 * Cleans a URL for display by removing protocol, www, and trailing slash.
 *
 * @param {string} url - The URL to clean.
 * @return {string} The cleaned URL for display.
 */
const cleanUrlForDisplay = ( url ) => {
	if ( ! url ) {
		return '';
	}
	return url
		.replace( /^https?:\/\//, '' )
		.replace( /^www\./, '' )
		.replace( /\/$/, '' );
};

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
const Edit = ( {
	attributes,
	setAttributes,
	context,
	clientId,
	insertBlocksAfter,
} ) => {
	const { placeholder, fieldType, linkTarget, cleanUrl } = attributes;
	const blockProps = useBlockProps();
	const [ isLinkPopoverOpen, setIsLinkPopoverOpen ] = useState( false );
	const [ isUrlFieldFocused, setIsUrlFieldFocused ] = useState( false );
	const linkButtonRef = useRef( null );

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

	const updateFieldValue = useCallback(
		( newValue ) => {
			// Strip any HTML tags from the value (plain text only).
			const strippedValue = newValue.replace( /<[^>]*>/g, '' );
			updateVenueField( jsonFieldName, strippedValue );
		},
		[ jsonFieldName, updateVenueField ]
	);

	const updateWebsiteUrl = useCallback(
		( newValue ) => {
			const strippedValue = newValue.replace( /<[^>]*>/g, '' );
			updateVenueField( 'website', strippedValue );
		},
		[ updateVenueField ]
	);

	// Get dispatch functions for venue store.
	const { updateVenueLatitude, updateVenueLongitude } =
		useDispatch( 'gatherpress/venue' );

	// Get mapCustomLatLong setting from venue store.
	const { mapCustomLatLong } = useSelect(
		( selectData ) => ( {
			mapCustomLatLong:
				selectData( 'gatherpress/venue' ).getMapCustomLatLong(),
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
				updateVenueField( { latitude: '', longitude: '' } );
			}
			return;
		}

		fetch(
			`https://nominatim.openstreetmap.org/search?q=${ encodeURIComponent( address ) }&format=geojson`
		)
			.then( ( response ) => {
				if ( ! response.ok ) {
					throw new Error(
						sprintf(
							/* translators: %s: Error message */
							__( 'Network response was not ok %s', 'gatherpress' ),
							response.statusText
						)
					);
				}
				return response.json();
			} )
			.then( ( data ) => {
				let lat = null;
				let lng = null;

				if ( 0 < data.features.length ) {
					lat = data.features[ 0 ].geometry.coordinates[ 1 ];
					lng = data.features[ 0 ].geometry.coordinates[ 0 ];
				}

				if ( ! mapCustomLatLong ) {
					// Update venue store for live preview.
					updateVenueLatitude( lat );
					updateVenueLongitude( lng );

					// Update meta for the venue post.
					updateVenueField( {
						latitude: lat ? String( lat ) : '',
						longitude: lng ? String( lng ) : '',
					} );
				}
			} )
			.catch( ( error ) => {
				// eslint-disable-next-line no-console
				console.warn( '[VenueDetail] Geocoding failed:', error );
			} );
	}, [
		fieldType,
		mapCustomLatLong,
		updateVenueLatitude,
		updateVenueLongitude,
		updateVenueField,
	] );

	const debouncedGeocode = useDebounce( geocodeAddress, 300 );

	// Trigger geocoding when address field value changes.
	useEffect( () => {
		if ( 'address' === fieldType ) {
			debouncedGeocode();
		}
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ fieldValue, fieldType ] );

	// Common onKeyDown handler for RichText fields.
	const handleKeyDown = ( event ) => {
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
	};

	// Render the URL field with link settings popover.
	const renderUrlField = () => {
		// When focused, show raw URL for editing. When blurred, show cleaned URL if enabled.
		let displayValue = fieldValue;
		if ( ! isUrlFieldFocused && cleanUrl ) {
			displayValue = cleanUrlForDisplay( fieldValue );
		}
		const placeholderText = placeholder || __( 'Website…', 'gatherpress' );

		return (
			<>
				<BlockControls>
					<ToolbarGroup>
						<ToolbarButton
							ref={ linkButtonRef }
							icon={ linkIcon }
							title={ __( 'Link settings', 'gatherpress' ) }
							onClick={ () =>
								setIsLinkPopoverOpen( ! isLinkPopoverOpen )
							}
							isPressed={ isLinkPopoverOpen }
						/>
					</ToolbarGroup>
				</BlockControls>
				{ isLinkPopoverOpen && (
					<Popover
						anchor={ linkButtonRef.current }
						onClose={ () => setIsLinkPopoverOpen( false ) }
						placement="bottom"
						shift
					>
						<div
							style={ {
								padding: '16px',
								width: '280px',
							} }
						>
							<ToggleControl
								label={ __( 'Open in new tab', 'gatherpress' ) }
								checked={ '_blank' === linkTarget }
								onChange={ ( value ) =>
									setAttributes( {
										linkTarget: value ? '_blank' : '_self',
									} )
								}
							/>
							<ToggleControl
								label={ __( 'Clean URL display', 'gatherpress' ) }
								checked={ cleanUrl }
								onChange={ ( value ) =>
									setAttributes( { cleanUrl: value } )
								}
							/>
						</div>
					</Popover>
				) }
				<RichText
					tagName={ fieldValue ? 'a' : 'span' }
					href={ fieldValue || undefined }
					target={
						fieldValue && '_blank' === linkTarget
							? '_blank'
							: undefined
					}
					rel={
						fieldValue && '_blank' === linkTarget
							? 'noopener noreferrer'
							: undefined
					}
					className="gatherpress-venue-detail__url"
					value={ displayValue }
					onChange={ updateWebsiteUrl }
					placeholder={ placeholderText }
					allowedFormats={ [] }
					onKeyDown={ handleKeyDown }
					onFocus={ () => setIsUrlFieldFocused( true ) }
					onBlur={ () => setIsUrlFieldFocused( false ) }
					onClick={ ( e ) => fieldValue && e.preventDefault() }
				/>
			</>
		);
	};

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
			onKeyDown: handleKeyDown,
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
				return renderUrlField();

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
