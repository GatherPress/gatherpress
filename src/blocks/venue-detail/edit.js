/**
 * WordPress dependencies.
 */
import { __ } from '@wordpress/i18n';
import { InspectorControls, RichText, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, SelectControl } from '@wordpress/components';
import { useSelect, useDispatch } from '@wordpress/data';
import { store as coreStore } from '@wordpress/core-data';
import { store as blockEditorStore } from '@wordpress/block-editor';
import { createBlock } from '@wordpress/blocks';

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
	const { placeholder, fieldType, metadata } = attributes;
	const blockProps = useBlockProps();

	// Get the venue post ID from context (provided by venue-v2 block).
	const venuePostId = context?.postId || 0;

	// Get the bound meta field name from metadata.bindings.
	const metaFieldName = metadata?.bindings?.content?.args?.key;

	// Get and update the meta field value.
	const { editEntityRecord } = useDispatch( coreStore );

	// Block insertion for Enter key handling at beginning.
	const { insertBlocks, selectBlock } = useDispatch( blockEditorStore );
	const { getBlockRootClientId, getBlockIndex } = useSelect( ( select ) => select( blockEditorStore ), [] );

	const fieldValue = useSelect(
		( select ) => {
			if ( ! venuePostId || ! metaFieldName ) {
				return '';
			}

			const { getEditedEntityRecord } = select( coreStore );
			const venuePost = getEditedEntityRecord(
				'postType',
				PT_VENUE,
				venuePostId
			);

			return venuePost?.meta?.[ metaFieldName ] || '';
		},
		[ venuePostId, metaFieldName ]
	);

	const updateFieldValue = ( newValue ) => {
		// Update the entity record (marks as dirty, handled by WordPress save flow).
		editEntityRecord( 'postType', PT_VENUE, venuePostId, {
			meta: {
				[ metaFieldName ]: newValue,
			},
		} );
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
			onKeyDown: ( event ) => {
				if ( 'Enter' === event.key && ! event.shiftKey ) {
					// Always prevent default to avoid line break/snap behavior.
					event.preventDefault();

					const selection = window.getSelection();
					if ( ! selection.rangeCount ) {
						return;
					}

					const range = selection.getRangeAt( 0 );
					const contentElement = event.currentTarget;
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
					}
					// At the end.
					else if ( cursorPosition === textContent.length ) {
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
