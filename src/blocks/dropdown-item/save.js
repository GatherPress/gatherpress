/**
 * WordPress dependencies.
 */
import { useBlockProps, RichText } from '@wordpress/block-editor';

/**
 * Save Component
 *
 * @param {Object} props            Block properties.
 * @param {Object} props.attributes Block attributes.
 * @return {JSX.Element} The rendered save component.
 */
const Save = ( { attributes } ) => {
	const blockProps = useBlockProps.save();
	const { text } = attributes;

	return <RichText.Content { ...blockProps } tagName="div" value={ text } />;
};

export default Save;
