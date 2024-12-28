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
import { dispatch } from '@wordpress/data';
import { useEffect } from '@wordpress/element';

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
	const { text, url } = attributes;
	const blockProps = useBlockProps();
	useEffect(() => {
		// console.log(text);
	}, [text]);
	return (
		<>
			<InspectorControls>
				<PanelBody title={__('Dropdown Item Settings', 'gatherpress')}>
					<p>
						{__(
							'This item behaves like a button if the link is empty or set to "#".',
							'gatherpress'
						)}
					</p>
				</PanelBody>
			</InspectorControls>
			<RichText
				{...blockProps}
				tagName="div"
				href={url}
				value={text}
				onChange={(value) => {
					// Parse the content and clean it up.
					const parser = new DOMParser();
					const parsedDoc = parser.parseFromString(
						value,
						'text/html'
					);
					const anchors = parsedDoc.querySelectorAll('a');

					// Default fallback.
					let newText = value.trim();

					if (anchors.length === 0) {
						newText = `<a href="#">${newText}</a>`;
					}

					// If a link exists, use its href and content.
					if (anchors.length > 1) {
						const firstAnchor = anchors[anchors[0]];
						newText = firstAnchor.outerHTML.trim();
						// Temporarily change `text` to force re-render.
						setAttributes({ text: '' });
					}

					setTimeout(() => {
						// Update attributes with the cleaned-up values.
						setAttributes({ text: newText });
					}, 0);

					// Update metadata for List View.
					dispatch('core/block-editor').updateBlockAttributes(
						clientId,
						{
							metadata: {
								name:
									newText ||
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
				}}
			/>
		</>
	);
};

export default Edit;
