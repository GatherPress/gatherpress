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
	const originalDisplayValues = useRef( new WeakMap() );

	// Toggle visibility of success message blocks and form elements for preview.
	useEffect( () => {
		const updateMessageVisibility = () => {
			if ( ! blockRef.current ) {
				return;
			}

			// Toggle success message visibility.
			const messageElements = blockRef.current.querySelectorAll(
				'.gatherpress--rsvp-form-message',
			);

			messageElements.forEach( ( element ) => {
				element.style.setProperty(
					'display',
					showMessage ? 'block' : 'none',
					'important',
				);
				element.setAttribute( 'aria-hidden', showMessage ? 'false' : 'true' );
				element.setAttribute( 'aria-live', 'polite' );
				element.setAttribute( 'role', 'status' );
			} );

			// Hide/show form field blocks.
			const formFieldBlocks = blockRef.current.querySelectorAll(
				'.wp-block-gatherpress-form-field',
			);
			formFieldBlocks.forEach( ( block ) => {
				// Store original display value if not already stored.
				if ( ! originalDisplayValues.current.has( block ) ) {
					const computedStyle = window.getComputedStyle( block );
					originalDisplayValues.current.set( block, computedStyle.display );
				}

				const originalDisplay = originalDisplayValues.current.get( block ) || 'block';
				block.style.setProperty(
					'display',
					showMessage ? 'none' : originalDisplay,
					'important',
				);
			} );

			// Hide/show buttons within .wp-block-buttons, except those with gatherpress-modal--trigger-close class.
			// Look for all button containers first.
			const buttonContainers = blockRef.current.querySelectorAll( '.wp-block-button' );
			buttonContainers.forEach( ( container ) => {
				// Check if the container or its button has the modal close class.
				const button = container.querySelector( 'button, .wp-block-button__link, input[type="submit"], input[type="button"], a' );
				if ( button ) {
					const hasCloseClass = container.classList.contains( 'gatherpress-modal--trigger-close' ) ||
						button.classList.contains( 'gatherpress-modal--trigger-close' );

					if ( ! hasCloseClass ) {
						// Store original display value if not already stored.
						if ( ! originalDisplayValues.current.has( container ) ) {
							const computedStyle = window.getComputedStyle( container );
							originalDisplayValues.current.set( container, computedStyle.display );
						}

						const originalDisplay = originalDisplayValues.current.get( container ) || 'inline-block';
						container.style.setProperty(
							'display',
							showMessage ? 'none' : originalDisplay,
							'important',
						);
					}
				}
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
