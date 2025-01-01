/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { useBlockProps, RichText } from '@wordpress/block-editor';
import { useEffect } from '@wordpress/element';

/**
 * External dependencies
 */
import { v4 as uuidv4 } from 'uuid';

/**
 * Edit function for Guest Count Input Block
 * @param root0
 * @param root0.attributes
 * @param root0.setAttributes
 */
const Edit = ({ attributes, setAttributes }) => {
	const blockProps = useBlockProps();
	const { label, inputId } = attributes;

	// Generate UUID for input if not already set.
	useEffect(() => {
		if (!inputId) {
			setAttributes({ inputId: 'input-' + uuidv4() });
		}
	}, [inputId, setAttributes]);

	return (
		<p {...blockProps}>
			<RichText
				tagName="label"
				htmlFor={inputId}
				value={label}
				onChange={(newLabel) => setAttributes({ label: newLabel })}
				placeholder={__('Enter label…', 'gatherpress')}
				aria-label={__(
					'Editable label for guest count input',
					'gatherpress'
				)}
				allowedFormats={['core/bold', 'core/italic']}
				multiline={false}
			/>
			<input
				type="number"
				id={inputId}
				placeholder="0"
				aria-label={label || __('Guest Count Input', 'gatherpress')}
				disabled={true}
				min="0"
				max="0"
			/>
		</p>
	);
};

export default Edit;
