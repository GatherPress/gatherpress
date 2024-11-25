import { registerBlockVariation } from '@wordpress/blocks';
import { addFilter } from '@wordpress/hooks';
import { __ } from '@wordpress/i18n';
import { InspectorControls } from '@wordpress/block-editor';
import { PanelBody, RangeControl } from '@wordpress/components';

/**
 * Register the block variation for the Comment Template Grid.
 */
registerBlockVariation('core/comment-template', {
	name: 'comment-template-grid',
	title: __('Comment Template Grid', 'textdomain'),
	description: __('Display comments in a grid layout.', 'textdomain'),
	attributes: {
		className: 'gatherpress-comment-template-grid-layout is-layout-grid',
		gridColumns: 4, // Default number of columns
	},
	isDefault: false,
});

/**
 * Add custom attributes to the core/comment-template block.
 * @param settings
 * @param name
 */
const addGridAttributes = (settings, name) => {
	if (name === 'core/comment-template') {
		settings.attributes = {
			...settings.attributes,
			gridColumns: {
				type: 'number',
				default: 4, // Default columns for grid layout
			},
		};
	}
	return settings;
};

addFilter(
	'blocks.registerBlockType',
	'custom/comment-template-grid-attributes',
	addGridAttributes
);

/**
 * Add inspector controls to configure the number of columns.
 * @param BlockEdit
 */
const addGridControls = (BlockEdit) => (props) => {
	const { name, attributes, setAttributes } = props;

	// Check if the block is the grid variation of the comment-template block
	if (
		name === 'core/comment-template' &&
		attributes.className?.includes('gatherpress-comment-template-grid-layout')
	) {
		return (
			<>
				<BlockEdit {...props} />
				<InspectorControls>
					<PanelBody
						title={__('Grid Settings', 'textdomain')}
						initialOpen={true}
					>
						<RangeControl
							label={__('Number of Columns', 'textdomain')}
							value={attributes.gridColumns}
							onChange={(value) =>
								setAttributes({ gridColumns: value })
							}
							min={1}
							max={6}
						/>
					</PanelBody>
				</InspectorControls>
			</>
		);
	}

	return <BlockEdit {...props} />;
};

addFilter(
	'editor.BlockEdit',
	'custom/comment-template-grid-controls',
	addGridControls
);

/**
 * Apply dynamic inline styles for the grid layout.
 * @param extraProps
 * @param blockType
 * @param attributes
 */
const applyGridColumnsStyle = (extraProps, blockType, attributes) => {
	if (attributes.className?.includes('gatherpress-comment-template-grid-layout')) {
		console.log('here');
		console.log(attributes);
	}
	// Ensure this only applies to the parent comment-template block with the grid layout
	if (
		blockType.name === 'core/comment-template' &&
		attributes.className?.includes('gatherpress-comment-template-grid-layout') &&
		!attributes.isInnerBlock // This ensures it's not an inner block
	) {
		const columns = attributes.gridColumns || 4;
		extraProps.style = {
			...extraProps.style,
			'--comment-template-columns': columns,
		};
	}
	return extraProps;
};

addFilter(
	'blocks.getSaveContent.extraProps',
	'custom/comment-template-grid-style',
	applyGridColumnsStyle
);
