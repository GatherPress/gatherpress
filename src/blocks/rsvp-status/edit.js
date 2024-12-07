/**
 * WordPress dependencies
 */
import { useBlockProps, RichText } from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';

const Edit = ({ attributes, setAttributes }) => {
	const { content } = attributes;

	const blockProps = useBlockProps();

	return (
		<div {...blockProps}>
			<RichText
				tagName="div"
				value={content}
				onChange={(newContent) =>
					setAttributes({ content: newContent })
				}
				placeholder={__('Write RSVP status…', 'gatherpress')}
			/>
		</div>
	);
};

export default Edit;
