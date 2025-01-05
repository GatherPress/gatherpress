/**
 * WordPress dependencies.
 */
import { __ } from '@wordpress/i18n';
import { useBlockProps, RichText } from '@wordpress/block-editor';
import { useEffect } from '@wordpress/element';
import { useSelect } from '@wordpress/data';

/**
 * External dependencies.
 */
import { v4 as uuidv4 } from 'uuid';

/**
 * Edit component for the Guest Count Input Block.
 *
 * This component is used in the WordPress editor to manage the editable interface
 * for the Guest Count Input block. It allows users to configure the label for
 * the input field and preview its appearance.
 *
 * @since 1.0.0
 *
 * @param {Object}   props               The props object passed to the component.
 * @param {Object}   props.attributes    The attributes for the block.
 * @param {Function} props.setAttributes A function to update block attributes.
 *
 * @return {JSX.Element} The rendered edit interface for the block.
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

	const maxAttendanceLimit = useSelect(
		(select) =>
			select('core/editor').getEditedPostAttribute('meta')
				?.gatherpress_max_guest_limit,
		[]
	);

	// Do not show block if guests are not permitted.
	if (0 === maxAttendanceLimit) {
		return '';
	}

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
