/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import {
	useBlockProps,
	RichText,
	InspectorControls,
} from '@wordpress/block-editor';
import { createBlock } from '@wordpress/blocks';
import { PanelBody } from '@wordpress/components';
import { dispatch, select, useSelect } from '@wordpress/data';
import { applyFilters } from '@wordpress/hooks';

/**
 * Edit Component
 *
 * @param {Object}   props                   Block properties.
 * @param {Object}   props.attributes        Block attributes.
 * @param {Function} props.setAttributes     Function to update attributes.
 * @param {string}   props.clientId          Unique ID of the block.
 * @param {Function} props.insertBlocksAfter Function to insert blocks after this block.
 * @param {boolean}  props.isSelected        Whether the block is selected.
 * @param {Object}   props.context           Block context data.
 *
 * @return {JSX.Element} The rendered edit component.
 */
const Edit = ( {
	attributes,
	setAttributes,
	clientId,
	insertBlocksAfter,
	isSelected,
	context,
} ) => {
	const { text } = attributes;
	const blockProps = useBlockProps();

	/**
	 * Filters the text a dropdown item displays in the editor canvas.
	 *
	 * A dropdown item's text can carry placeholders that only resolve at
	 * render time, which leaves the author looking at a raw token. This
	 * filter lets whatever owns a placeholder resolve it for display while
	 * the stored attribute keeps the token untouched.
	 *
	 * The item itself knows nothing about any particular placeholder. It
	 * passes its own attributes, its post context, and `select` so a handler
	 * can subscribe to its own store; the `useSelect` wrapper is what makes
	 * the result re-render once that store resolves.
	 *
	 * @since 0.36.0
	 *
	 * @param {string} text    The item's stored text, markup included.
	 * @param {Object} details Block attributes, block context, and `select`.
	 *
	 * @return {string} The text to display in the canvas.
	 */
	const displayText = useSelect(
		( selectStore ) =>
			applyFilters( 'gatherpress.dropdownItemText', text, {
				attributes,
				context,
				select: selectStore,
			} ),
		[ text, attributes, context ],
	);

	// Show the stored text while editing so the author can see and keep any
	// placeholder, and the resolved text when they step away so the item
	// reads the way visitors will see it.
	const richTextValue = isSelected ? text : displayText;

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Dropdown Item Settings', 'gatherpress' ) }>
					<p>
						{ __(
							'This item behaves like a button if the link is set to "#".',
							'gatherpress',
						) }
					</p>
				</PanelBody>
			</InspectorControls>
			<RichText
				{ ...blockProps }
				tagName="div"
				value={ richTextValue }
				onChange={ ( value ) => {
					// Parse the content and clean it up.
					const parser = new DOMParser();
					const parsedDoc = parser.parseFromString(
						value,
						'text/html',
					);
					const anchors = parsedDoc.querySelectorAll( 'a' );

					// Default fallback anchor tag.
					let openingTag = '<a href="#">';
					const closingTag = '</a>';

					if ( 0 < anchors.length ) {
						// Extract the opening tag from the first anchor.
						const firstAnchor = anchors[ 0 ];

						// Capture attributes.
						const href = firstAnchor.getAttribute( 'href' ) || '#';
						const rel = firstAnchor.getAttribute( 'rel' );
						const target = firstAnchor.getAttribute( 'target' );

						// Start building the opening tag.
						openingTag = `<a href="${ href }"`;

						// Add rel attribute if it exits.
						if ( rel ) {
							openingTag += ` rel="${ rel }"`;
						}

						// Add target attribute if it exists.
						if ( target ) {
							openingTag += ` target="${ target }"`;
						}

						// Close the opening tag.
						openingTag += '>';
					}

					// Remove all markup and clean text.
					const cleanText = parsedDoc.body.textContent.trim();

					// Wrap the clean text with the anchor tags.
					let newText;
					if ( cleanText ) {
						newText = `${ openingTag }${ cleanText }${ closingTag }`;
					} else {
						newText = '';
					}

					// Update attributes with the cleaned-up values.
					setAttributes( { text: newText } );

					// Update metadata for List View.
					dispatch( 'core/block-editor' ).updateBlockAttributes(
						clientId,
						{
							metadata: {
								name:
									cleanText ||
									__( 'Dropdown Item', 'gatherpress' ),
							},
						},
					);
				} }
				placeholder={ __( 'Item Text…', 'gatherpress' ) }
				allowedFormats={ [ 'core/link' ] }
				onKeyDown={ ( event ) => {
					if ( 'Enter' === event.key ) {
						event.preventDefault();
						const newBlock = createBlock(
							'gatherpress/dropdown-item',
							{ text: '' },
						);
						insertBlocksAfter( [ newBlock ] );
					}

					if ( 'Backspace' === event.key && ! attributes.text ) {
						event.preventDefault();

						// Retrieve block order and index.
						const { getBlockOrder, getBlockIndex } =
							select( 'core/block-editor' );
						const { removeBlock, selectBlock } =
							dispatch( 'core/block-editor' );

						const blockOrder = getBlockOrder();
						const currentIndex = getBlockIndex( clientId );

						// Check if there's a previous block.
						if ( 0 < currentIndex ) {
							const previousBlockId =
								blockOrder[ currentIndex - 1 ];

							// Focus the previous block and set the caret to the end.
							selectBlock( previousBlockId, -1 );

							// Remove the current block.
							removeBlock( clientId );
						}
					}
				} }
			/>
		</>
	);
};

export default Edit;
