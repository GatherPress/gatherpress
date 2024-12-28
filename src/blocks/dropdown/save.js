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
		openOn,
		dropdownBorderColor,
		dropdownBorderThickness,
		dropdownBorderRadius,
	} = attributes;

	const parentElement = blockProps.className?.replace(/\s+/g, '.') || '';

	const dropdownStyles = `
		#${dropdownId} .wp-block-gatherpress-dropdown-item a {
			padding: ${itemPadding.top}px ${itemPadding.right}px ${itemPadding.bottom}px ${itemPadding.left}px;
			color: ${itemTextColor || 'inherit'};
			background-color: ${itemBgColor || 'transparent'};
		}

		#${dropdownId} .wp-block-gatherpress-dropdown-item a:hover {
			color: ${itemHoverTextColor || 'inherit'};
			background-color: ${itemHoverBgColor || 'transparent'};
		}

		#${dropdownId} .wp-block-gatherpress-dropdown-item:not(:first-child) {
			border-top: ${itemDividerThickness || 1}px solid ${itemDividerColor || 'transparent'};
		}

		${
			openOn === 'hover' && parentElement
				? `
					.${parentElement}:hover #${dropdownId},
					.${parentElement}:focus-within #${dropdownId} {
						display: block;
					}
				`
				: ''
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
