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
import { useState, useEffect, useRef } from '@wordpress/element';

/**
 * Internal dependencies.
 */
import TEMPLATE from './template';

const Edit = () => {
	const blockRef = useRef( null );
	const blockProps = useBlockProps( { ref: blockRef } );
	const [ showMessage, setShowMessage ] = useState( false );

	// Toggle visibility of success message blocks for preview.
	useEffect( () => {
		const updateMessageVisibility = () => {
			if ( ! blockRef.current ) {
				return;
			}

			const messageElements = blockRef.current.querySelectorAll(
				'.gatherpress--rsvp-form-message',
			);

			messageElements.forEach( ( element ) => {
				element.style.setProperty(
					'display',
					showMessage ? 'block' : 'none',
					'important',
				);
			} );
		};

		// Watch for DOM changes.
		const observer = new MutationObserver( updateMessageVisibility );

		if ( blockRef.current ) {
			observer.observe( blockRef.current, {
				childList: true,
				subtree: true,
				attributes: true,
				attributeFilter: [ 'class' ],
			} );
		}

		// Initial call.
		updateMessageVisibility();

		return () => observer.disconnect();
	}, [ showMessage ] );

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
