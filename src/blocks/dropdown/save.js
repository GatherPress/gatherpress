import { useBlockProps, InnerBlocks } from '@wordpress/block-editor';

const Save = ({ attributes }) => {
	const blockProps = useBlockProps.save();
	const { dropdownId, label, labelColor, itemBgColor } = attributes;

	// Derive a base class from the parent block's className.
	const parentClass = blockProps.className || '';

	return (
		<div {...blockProps}>
			{/* eslint-disable-next-line jsx-a11y/anchor-is-valid */}
			<a
				href="#"
				role="button"
				aria-expanded="false"
				aria-controls={dropdownId}
				tabIndex={0}
				className={`${parentClass}__trigger`}
				style={{
					color: labelColor,
				}}
			>
				{label}
			</a>
			<div
				id={dropdownId}
				role="region"
				className={`${parentClass}__menu`}
				style={{
					backgroundColor: itemBgColor,
				}}
			>
				<InnerBlocks.Content />
			</div>
		</div>
	);
};

export default Save;
