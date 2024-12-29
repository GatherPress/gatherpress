/**
 * WordPress dependencies.
 */
import { __ } from '@wordpress/i18n';
import {
	useBlockProps,
	RichText,
	InspectorControls,
} from '@wordpress/block-editor';
import { createBlock } from '@wordpress/blocks';
import { PanelBody } from '@wordpress/components';
import { dispatch, select } from '@wordpress/data';

/**
 * Edit Component
 *
 * @param {Object}   props                   Block properties.
 * @param {Object}   props.attributes        Block attributes.
 * @param {Function} props.setAttributes     Function to update attributes.
 * @param {string}   props.clientId          Unique ID of the block.
 * @param {Function} props.insertBlocksAfter Function to insert blocks after this block.
 * @return {JSX.Element} The rendered edit component.
 */
const Edit = ({ attributes, setAttributes, clientId, insertBlocksAfter }) => {
	const { text } = attributes;
	const blockProps = useBlockProps();

	return (
		<>
			<InspectorControls>
				<PanelBody title={__('Dropdown Item Settings', 'gatherpress')}>
					<p>
						{__(
							'This item behaves like a button if the link is set to "#".',
							'gatherpress'
						)}
					</p>
				</PanelBody>
			</InspectorControls>
			<RichText
				{...blockProps}
				tagName="div"
				value={text}
				onChange={(value) => {
					// Parse the content and clean it up.
					const parser = new DOMParser();
					const parsedDoc = parser.parseFromString(
						value,
						'text/html'
					);
					const anchors = parsedDoc.querySelectorAll('a');

					// Default fallback anchor tag.
					let openingTag = '<a href="#">';
					const closingTag = '</a>';
					let newText = value.trim();

					if (anchors.length > 0) {
						// Extract the opening tag from the first anchor.
						const firstAnchor = anchors[0];
						const href = firstAnchor.getAttribute('href') || '#';
						openingTag = `<a href="${href}">`;
					}

					// Remove all markup and clean text.
					const cleanText = parsedDoc.body.textContent.trim();

					// Wrap the clean text with the anchor tags.
					if (cleanText) {
						newText = `${openingTag}${cleanText}${closingTag}`;
					} else {
						newText = '';
					}

					// Update attributes with the cleaned-up values.
					setAttributes({ text: newText });

					// Update metadata for List View.
					dispatch('core/block-editor').updateBlockAttributes(
						clientId,
						{
							metadata: {
								name:
									cleanText ||
									__('Dropdown Item', 'gatherpress'),
							},
						}
					);
				}}
				placeholder={__('Item Text…', 'gatherpress')}
				allowedFormats={['core/link']}
				onSplit={(before, after) => {
					const newBlock = createBlock('gatherpress/dropdown-item', {
						text: after,
					});
					insertBlocksAfter([newBlock]);
					setAttributes({ text: before });
				}}
				onKeyDown={(event) => {
					if (event.key === 'Enter') {
						event.preventDefault();
						const newBlock = createBlock(
							'gatherpress/dropdown-item',
							{ text: '' }
						);
						insertBlocksAfter([newBlock]);
					}

					if (event.key === 'Backspace' && !attributes.text) {
						event.preventDefault();

						// Retrieve block order and index.
						const { getBlockOrder, getBlockIndex } =
							select('core/block-editor');
						const { removeBlock, selectBlock } =
							dispatch('core/block-editor');

						const blockOrder = getBlockOrder();
						const currentIndex = getBlockIndex(clientId);

						// Check if there's a previous block.
						if (currentIndex > 0) {
							const previousBlockId =
								blockOrder[currentIndex - 1];

							// Focus the previous block and set the caret to the end.
							selectBlock(previousBlockId, -1);

							// Remove the current block.
							removeBlock(clientId);
						}
					}
				}}
			/>
		</>
	);
};

export default Edit;
