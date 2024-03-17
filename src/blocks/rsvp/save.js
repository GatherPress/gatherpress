import {addFilter} from '@wordpress/hooks';

function myPluginAddExtraProps(extraProps, blockType, attributes) {
	console.log(blockType);
	console.log(attributes);
	if (blockType.name === 'core/button') {
		console.log('yOOOO');
		console.log(blockType.name);
		console.log(attributes);
		extraProps = {
			...extraProps,
			'data-a11y-dialog-show': attributes.modalId,
		};
		console.log(extraProps);
	}

	return extraProps;
}
addFilter('blocks.getSaveContent.extraProps', 'my-plugin/add-extra-props', myPluginAddExtraProps);
console.log('save');
/**
 * External dependencies
 */
import classnames from 'classnames';

/**
 * WordPress dependencies
 */
import { useBlockProps, useInnerBlocksProps } from '@wordpress/block-editor';

export default function save( { attributes, className } ) {
	const { fontSize, style } = attributes;
	const blockProps = useBlockProps.save( {
		className: classnames( className, {
			'has-custom-font-size': fontSize || style?.typography?.fontSize,
		} ),
	} );
	const innerBlocksProps = useInnerBlocksProps.save( blockProps );
	return <div { ...innerBlocksProps } />;
}
