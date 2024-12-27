/**
 * WordPress dependencies
 */
import { useBlockProps, RichText } from '@wordpress/block-editor';

/**
 * Save Component
 *
 * @param {Object} props            Block properties.
 * @param {Object} props.attributes Block attributes.
 * @return {JSX.Element} The rendered save component.
 */
const Save = ({ attributes }) => {
	const blockProps = useBlockProps.save();
	const { text, url, isButton, itemPadding, itemTextColor } = attributes;

	return (
		<RichText.Content
			{...blockProps}
			tagName={isButton ? 'button' : 'a'}
			href={isButton ? undefined : url}
			value={text}
			style={{
				padding: `${itemPadding?.top || 0}px ${itemPadding?.right || 0}px ${itemPadding?.bottom || 0}px ${itemPadding?.left || 0}px`,
				color: itemTextColor || undefined,
			}}
		/>
	);
};

export default Save;
