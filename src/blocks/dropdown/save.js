import { useBlockProps, InnerBlocks } from '@wordpress/block-editor';

const Save = ({ attributes }) => {
	const blockProps = useBlockProps.save();
	const {
		dropdownId,
		label,
		labelColor,
		itemBgColor,
		itemPadding,
		itemHoverBgColor,
		itemTextColor,
		itemHoverTextColor,
		itemDividerColor,
		itemDividerThickness,
		dropdownBorderColor,
		dropdownBorderThickness,
		dropdownBorderRadius,
	} = attributes;

	const dropdownStyles = `
		.wp-block-gatherpress-dropdown .wp-block-gatherpress-dropdown-item {
			padding: ${itemPadding.top} ${itemPadding.right} ${itemPadding.bottom} ${itemPadding.left};
			color: ${itemTextColor || 'inherit'};
			background-color: ${itemBgColor || 'transparent'};
		}

		.wp-block-gatherpress-dropdown .wp-block-gatherpress-dropdown-item:hover {
			color: ${itemHoverTextColor || 'inherit'};
			background-color: ${itemHoverBgColor || 'transparent'};
		}

		.wp-block-gatherpress-dropdown .wp-block-gatherpress-dropdown-item:not(:first-child) {
			border-top: ${itemDividerThickness || 1}px solid ${itemDividerColor || 'transparent'};
		}
	`;

	return (
		<>
			<style>{dropdownStyles}</style>
			<div {...blockProps}>
				{/* eslint-disable-next-line jsx-a11y/anchor-is-valid */}
				<a
					href="#"
					role="button"
					aria-expanded="false"
					aria-controls={dropdownId}
					tabIndex={0}
					className="wp-block-gatherpress-dropdown__trigger"
					style={{
						color: labelColor,
					}}
				>
					{label}
				</a>
				<div
					id={dropdownId}
					role="region"
					className="wp-block-gatherpress-dropdown__menu"
					style={{
						backgroundColor: itemBgColor,
						border: `${dropdownBorderThickness || 1}px solid ${dropdownBorderColor || '#000'}`,
						borderRadius: `${dropdownBorderRadius || 0}px`,
						zIndex: attributes.dropdownZIndex,
						width: attributes.dropdownMaxWidth,
					}}
				>
					<InnerBlocks.Content />
				</div>
			</div>
		</>
	);
};

export default Save;
