/**
 * WordPress dependencies.
 */
import {
	useBlockProps,
	InnerBlocks,
	InspectorControls,
} from '@wordpress/block-editor';
import { PanelBody, ToggleControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useState, useEffect } from '@wordpress/element';

/**
 * Internal dependencies.
 */
import TEMPLATE from './template';

const Edit = () => {
	const blockProps = useBlockProps();
	const [ showMessage, setShowMessage ] = useState( false );

	// Toggle visibility of success message blocks for preview.
	useEffect( () => {
		const messageElements = document.querySelectorAll(
			'.gatherpress-rsvp-form-message',
		);
		messageElements.forEach( ( element ) => {
			element.style.display = showMessage ? 'block' : 'none';
		} );
	}, [ showMessage ] );

	// Hide message blocks immediately on mount.
	useEffect( () => {
		const messageElements = document.querySelectorAll(
			'.gatherpress-rsvp-form-message',
		);
		messageElements.forEach( ( element ) => {
			element.style.display = 'none';
		} );
	}, [] );

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Preview Settings', 'gatherpress' ) }>
					<ToggleControl
						label={ __( 'Show success message', 'gatherpress' ) }
						help={ __(
							'Toggle to preview the success message that appears after form submission. This setting is not saved.',
							'gatherpress',
						) }
						checked={ showMessage }
						onChange={ setShowMessage }
					/>
				</PanelBody>
			</InspectorControls>
			<div { ...blockProps }>
				<InnerBlocks template={ TEMPLATE } />
			</div>
		</>
	);
};

export default Edit;
