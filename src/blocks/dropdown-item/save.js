/**
 * WordPress dependencies
 */
import { useBlockProps, RichText } from '@wordpress/block-editor';

/**
 * Save Component
 *
 * @param {Object} props Block properties.
 * @return {JSX.Element} The rendered save component.
 */
const Save = ({ attributes }) => {
	const blockProps = useBlockProps.save();
	const { text, url, isButton } = attributes;

	return (
		<RichText.Content
			{...blockProps}
			tagName={isButton ? 'button' : 'a'}
			href={isButton ? undefined : url}
			value={text}
		/>
	);
};

export default Save;
