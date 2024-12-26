/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { useBlockProps, RichText } from '@wordpress/block-editor';
import { createBlock } from '@wordpress/blocks';
import { InspectorControls } from '@wordpress/block-editor';
import { PanelBody } from '@wordpress/components';

/**
 * Edit Component
 *
 * @param {Object} props Block properties.
 * @return {JSX.Element} The rendered edit component.
 */
const Edit = ({ attributes, setAttributes, clientId, onReplace, insertBlocksAfter }) => {
	const blockProps = useBlockProps();
	const { text, url } = attributes;

	const isButtonLike = !url || url === '#';

	return (
		<div {...blockProps}>
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
				tagName="a"
				href={isButtonLike ? undefined : url}
				role={isButtonLike ? 'button' : undefined}
				tabIndex={isButtonLike ? 0 : undefined}
				aria-pressed={isButtonLike ? 'false' : undefined}
				value={text}
				onChange={(value) => {
					setAttributes({ text: value });

					// Update the metadata name for List View
					wp.data.dispatch('core/block-editor').updateBlockAttributes(clientId, {
						metadata: { name: value || __('Dropdown Item', 'gatherpress') },
					});
				}}
				placeholder={__('Item Text...', 'gatherpress')}
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
						const newBlock = createBlock('gatherpress/dropdown-item', { text: '' });
						insertBlocksAfter([newBlock]);
					}
				}}
				onClick={(event) => {
					if (isButtonLike) {
						event.preventDefault();
					}
				}}
			/>
		</div>
	);
};

export default Edit;
