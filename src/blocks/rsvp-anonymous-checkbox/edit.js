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
 * Edit component for the RSVP Anonymous Checkbox Block.
 *
 * This component is used in the WordPress editor to manage the editable interface
 * for the RSVP Anonymous Checkbox block. It allows users to configure the label
 * for the checkbox and preview its appearance in the editor.
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

	const enableAnonymousRsvp = useSelect(
		(select) =>
			select('core/editor').getEditedPostAttribute('meta')
				?.gatherpress_enable_anonymous_rsvp,
		[]
	);

	// Do not show block if anonymous are not permitted.
	if (0 === enableAnonymousRsvp) {
		return '';
	}

	return (
		<p {...blockProps}>
			<input
				type="checkbox"
				id={inputId}
				aria-label={label || __('Anonymous Checkbox', 'gatherpress')}
				disabled={true}
			/>
			<RichText
				tagName="label"
				htmlFor={inputId}
				value={label}
				onChange={(newLabel) => setAttributes({ label: newLabel })}
				placeholder={__('Enter label…', 'gatherpress')}
				aria-label={__(
					'Editable label for anonymous checkbox',
					'gatherpress'
				)}
				allowedFormats={['core/bold', 'core/italic']}
				multiline={false}
			/>
		</p>
	);
};

export default Edit;
