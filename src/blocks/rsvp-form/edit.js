/**
 * WordPress dependencies.
 */
import {
	useBlockProps,
	InnerBlocks,
	InspectorControls,
	store as blockEditorStore,
} from '@wordpress/block-editor';
import { PanelBody, SelectControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useState, useEffect, useCallback } from '@wordpress/element';
import { useSelect, useDispatch } from '@wordpress/data';
import { getBlockTypes } from '@wordpress/blocks';

/**
 * Internal dependencies.
 */
import TEMPLATE from './template';

const Edit = ( { clientId } ) => {
	const [ formState, setFormState ] = useState( 'default' );
	const { replaceInnerBlocks } = useDispatch( blockEditorStore );

	// Calculate allowed blocks - all blocks except gatherpress/rsvp-form.
	const allowedBlocks = getBlockTypes()
		.map( ( blockType ) => blockType.name )
		.filter( ( name ) => 'gatherpress/rsvp-form' !== name );

	// Get max attendance limit from post meta to control guest count field visibility.
	const maxAttendanceLimit = useSelect(
		( select ) => {
			const meta = select( 'core/editor' ).getEditedPostAttribute( 'meta' );
			return meta?.gatherpress_max_guest_limit;
		},
	);

	// Get anonymous RSVP setting from post meta to control anonymous checkbox visibility.
	const enableAnonymousRsvp = useSelect(
		( select ) => {
			const meta = select( 'core/editor' ).getEditedPostAttribute( 'meta' );
			// Convert meta value to boolean.
			return Boolean( meta?.gatherpress_enable_anonymous_rsvp );
		},
	);

	// Get all inner blocks and track their visibility attributes specifically.
	const { innerBlocks, visibilityAttributes } = useSelect( ( select ) => {
		const { getBlock } = select( 'core/block-editor' );
		const block = getBlock( clientId );

		if ( ! block ) {
			return { innerBlocks: [], visibilityAttributes: {} };
		}

		// Recursively collect all visibility attributes to trigger updates.
		const collectVisibilityAttributes = ( blocks, attrs = {} ) => {
			blocks.forEach( ( childBlock ) => {
				if ( childBlock.attributes?.gatherpressRsvpFormVisibility ) {
					attrs[ childBlock.clientId ] = childBlock.attributes.gatherpressRsvpFormVisibility;
				}
				if ( childBlock.innerBlocks?.length ) {
					collectVisibilityAttributes( childBlock.innerBlocks, attrs );
				}
			} );
			return attrs;
		};

		return {
			innerBlocks: block.innerBlocks,
			visibilityAttributes: collectVisibilityAttributes( block.innerBlocks ),
		};
	}, [ clientId ] );

	/**
	 * Apply conditional visibility class to form fields based on event settings.
	 *
	 * @param {Array} blocks Array of blocks to process.
	 * @return {Array} Processed blocks with conditional classes applied.
	 */
	const applyFormFieldVisibility = useCallback( ( blocks ) => {
		return blocks.map( ( block ) => {
			// Check if this is a form-field block that needs conditional visibility.
			if ( 'gatherpress/form-field' === block.name ) {
				const fieldName = block.attributes?.fieldName;
				let shouldDisable = false;

				// Determine if the field should be disabled based on its field name.
				if ( 'gatherpress_rsvp_guests' === fieldName ) {
					shouldDisable = 0 === parseInt( maxAttendanceLimit, 10 );
				} else if ( 'gatherpress_rsvp_anonymous' === fieldName ) {
					shouldDisable = ! enableAnonymousRsvp;
				}

				// Only process fields that have conditional visibility.
				if ( 'gatherpress_rsvp_guests' === fieldName || 'gatherpress_rsvp_anonymous' === fieldName ) {
					const newAttributes = { ...block.attributes };

					if ( shouldDisable ) {
						newAttributes[ 'data-gatherpress-no-render' ] = 'true';
					} else {
						delete newAttributes[ 'data-gatherpress-no-render' ];
					}

					return {
						...block,
						attributes: newAttributes,
					};
				}
			}

			// Recursively process inner blocks.
			if ( block.innerBlocks && 0 < block.innerBlocks.length ) {
				return {
					...block,
					innerBlocks: applyFormFieldVisibility( block.innerBlocks ),
				};
			}

			return block;
		} );
	}, [ maxAttendanceLimit, enableAnonymousRsvp ] );

	// Generate CSS for visibility based on form state.
	useEffect( () => {
		const styles = [];
		const collectVisibilityStyles = ( blocks, depth = 0 ) => {
			blocks.forEach( ( block ) => {
				if ( block.attributes?.gatherpressRsvpFormVisibility ) {
					const visibility = block.attributes.gatherpressRsvpFormVisibility;
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
	}, [ formState, innerBlocks, visibilityAttributes, clientId ] );

	// Apply form field visibility when event settings change.
	useEffect( () => {
		if ( innerBlocks && 0 < innerBlocks.length ) {
			const updatedBlocks = applyFormFieldVisibility( innerBlocks );

			// Only update if there are actual changes.
			const hasChanges = JSON.stringify( updatedBlocks ) !== JSON.stringify( innerBlocks );
			if ( hasChanges ) {
				replaceInnerBlocks( clientId, updatedBlocks );
			}
		}
	}, [ maxAttendanceLimit, enableAnonymousRsvp, clientId, replaceInnerBlocks, applyFormFieldVisibility, innerBlocks ] );

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
				<InnerBlocks
					template={ TEMPLATE }
					allowedBlocks={ allowedBlocks }
				/>
			</div>
		</>
	);
};

export default Edit;
