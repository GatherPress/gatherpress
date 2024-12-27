/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import {
	useBlockProps,
	RichText,
	InspectorControls,
} from '@wordpress/block-editor';
import { useEffect } from '@wordpress/element';
import { createBlock } from '@wordpress/blocks';
import { PanelBody } from '@wordpress/components';
import { dispatch } from '@wordpress/data';

/**
 * Edit Component
 *
 * @param {Object}   props                   Block properties.
 * @param {Object}   props.attributes        Block attributes.
 * @param {Function} props.setAttributes     Function to update attributes.
 * @param {string}   props.clientId          Unique ID of the block.
 * @param {Function} props.insertBlocksAfter Function to insert blocks after this block.
 * @param {Object}   props.context           Context provided by parent blocks.
 * @return {JSX.Element} The rendered edit component.
 */
const Edit = ({
	attributes,
	setAttributes,
	clientId,
	insertBlocksAfter,
	context,
}) => {
	const { text, url, itemPadding, itemTextColor } = attributes;

	// Synchronize attributes with parent block context only if they differ
	useEffect(() => {
		const contextPadding = context['gatherpress/dropdown/itemPadding'];
		const contextTextColor = context['gatherpress/dropdown/itemTextColor'];

		if (
			JSON.stringify(itemPadding) !== JSON.stringify(contextPadding) ||
			itemTextColor !== contextTextColor
		) {
			setAttributes({
				itemPadding: contextPadding || itemPadding,
				itemTextColor: contextTextColor || itemTextColor,
			});
		}
	}, [context, itemPadding, itemTextColor, setAttributes]);

	const isButtonLike = !url || url === '#';

	const blockProps = useBlockProps({
		style: {
			padding: `${itemPadding?.top || 0}px ${itemPadding?.right || 0}px ${itemPadding?.bottom || 0}px ${itemPadding?.left || 0}px`,
			color: itemTextColor,
		},
	});

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
				tagName="a"
				href={isButtonLike ? undefined : url}
				role={isButtonLike ? 'button' : undefined}
				tabIndex={isButtonLike ? 0 : undefined}
				aria-pressed={isButtonLike ? 'false' : undefined}
				value={text}
				onChange={(value) => {
					setAttributes({ text: value });

					// Update the metadata name for List View
					dispatch('core/block-editor').updateBlockAttributes(
						clientId,
						{
							metadata: {
								name:
									value || __('Dropdown Item', 'gatherpress'),
							},
						}
					);
				}}
				placeholder={__('Item Text…', 'gatherpress')}
				allowedFormats={['core/bold', 'core/italic']}
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
				onClick={(event) => {
					if (isButtonLike) {
						event.preventDefault();
					}
				}}
			/>
		</>
	);
};

export default Edit;
