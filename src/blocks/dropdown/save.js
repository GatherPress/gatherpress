/**
 * WordPress dependencies
 */
import { useBlockProps, InnerBlocks } from '@wordpress/block-editor';

/**
 * Save Component
 *
 * @param {Object} props Block properties.
 * @return {JSX.Element} The rendered save component.
 */
const Save = ({ attributes, clientId }) => {
	const blockProps = useBlockProps.save();
	const dropdownId = `dropdown-${clientId}`;

	return (
		<div {...blockProps}>
			<a
				href="#"
				role="button"
				aria-expanded="false"
				aria-controls={dropdownId}
				tabIndex={0}
				style={{
					color: attributes.labelColor,
				}}
			>
				{attributes.label}
			</a>
			<div
				id={dropdownId}
				role="region"
				style={{
					backgroundColor: attributes.dropdownBgColor,
				}}
			>
				<InnerBlocks.Content />
			</div>
		</div>
	);
};

export default Save;
