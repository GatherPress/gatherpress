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
	const { text, url, isButton, itemHoverTextColor, itemHoverBgColor } =
		attributes;

	return (
		<RichText.Content
			{...blockProps}
			tagName={isButton ? 'button' : 'a'}
			href={isButton ? undefined : url}
			value={text}
			data-hover-text-color={itemHoverTextColor || undefined}
			data-hover-bg-color={itemHoverBgColor || undefined}
		/>
	);
};

export default Save;
