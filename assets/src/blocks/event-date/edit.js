/**
 * WordPress dependencies.
 */
import { __ } from '@wordpress/i18n';
import { useBlockProps } from '@wordpress/block-editor';
import { Flex, FlexBlock, FlexItem, Icon, TextControl } from '@wordpress/components';
import { useSelect, useDispatch } from '@wordpress/data';
import { useEffect } from '@wordpress/element';

const Edit = ( props ) => {
	const { attributes, setAttributes, isSelected } = props;
	const blockProps = useBlockProps();

	return (
		<div { ...blockProps }>
			FOOBAR
		</div>
	);
}

export default Edit;
