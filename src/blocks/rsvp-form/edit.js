/**
 * WordPress dependencies.
 */
import {
	useBlockProps,
	InnerBlocks,
	InspectorControls,
} from '@wordpress/block-editor';
import { PanelBody, SelectControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useState, useEffect } from '@wordpress/element';
import { useSelect } from '@wordpress/data';

/**
 * Internal dependencies.
 */
import TEMPLATE from './template';

const Edit = ( { clientId } ) => {
	const [ formState, setFormState ] = useState( 'default' );

	// Get all inner blocks and their attributes to generate CSS.
	const innerBlocks = useSelect( ( select ) => {
		const { getBlock } = select( 'core/block-editor' );
		const block = getBlock( clientId );
		return block ? block.innerBlocks : [];
	}, [ clientId ] );

	// Generate CSS for visibility based on form state.
	useEffect( () => {
		const styles = [];
		const collectVisibilityStyles = ( blocks, depth = 0 ) => {
			blocks.forEach( ( block ) => {
				if ( block.attributes?.formVisibility ) {
					const visibility = block.attributes.formVisibility;
					const selector = `#block-${ block.clientId }`;

					if ( 'showOnSuccess' === visibility ) {
						if ( 'success' !== formState ) {
							styles.push( `${ selector } { display: none !important; }` );
						}
					} else if ( 'hideOnSuccess' === visibility ) {
						if ( 'success' === formState ) {
							styles.push( `${ selector } { display: none !important; }` );
						}
					}
				}
				if ( 0 < block.innerBlocks?.length ) {
					collectVisibilityStyles( block.innerBlocks, depth + 1 );
				}
			} );
		};
		collectVisibilityStyles( innerBlocks );

		// Inject styles into the page.
		const styleId = `gatherpress-form-visibility-${ clientId }`;
		let styleElement = document.getElementById( styleId );

		if ( ! styleElement ) {
			styleElement = document.createElement( 'style' );
			styleElement.id = styleId;
			document.head.appendChild( styleElement );
		}

		styleElement.textContent = styles.join( '\n' );

		// Cleanup on unmount.
		return () => {
			if ( styleElement && styleElement.parentNode ) {
				styleElement.parentNode.removeChild( styleElement );
			}
		};
	}, [ formState, innerBlocks, clientId ] );

	const blockProps = useBlockProps();

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Preview Settings', 'gatherpress' ) }>
					<SelectControl
						label={ __( 'Form State Preview', 'gatherpress' ) }
						help={ __(
							'Preview how blocks appear in different form states. This setting is not saved.',
							'gatherpress',
						) }
						value={ formState }
						options={ [
							{
								label: __( 'Default (before submission)', 'gatherpress' ),
								value: 'default',
							},
							{
								label: __( 'Success (after submission)', 'gatherpress' ),
								value: 'success',
							},
						] }
						onChange={ setFormState }
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
