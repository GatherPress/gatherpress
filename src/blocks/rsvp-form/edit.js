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
import { useState, useEffect, useCallback } from '@wordpress/element';
import { useSelect } from '@wordpress/data';
import { getBlockTypes } from '@wordpress/blocks';

/**
 * Internal dependencies.
 */
import TEMPLATE from './template';
import { hasValidEventId, DISABLED_FIELD_OPACITY, getEventMeta } from '../../helpers/event';
import { isInFSETemplate, getEditorDocument } from '../../helpers/editor';
import { shouldHideBlock } from './visibility';

const Edit = ( { attributes, clientId, context } ) => {
	const [ formState, setFormState ] = useState( 'default' );
	// Normalize empty strings to null so fallback to context.postId works correctly.
	const postId = ( attributes?.postId || null ) ?? context?.postId ?? null;

	// Calculate allowed blocks - all blocks except gatherpress/rsvp-form.
	const allowedBlocks = getBlockTypes()
		.map( ( blockType ) => blockType.name )
		.filter( ( name ) => 'gatherpress/rsvp-form' !== name );

	// Get event data - either from override postId or current post.
	const { maxGuestLimit: maxAttendanceLimit, enableAnonymousRsvp } = useSelect(
		( select ) => getEventMeta( select, postId, attributes ),
		[ postId, attributes ]
	);

	// Check if block has a valid event connection.
	const isValidEvent = hasValidEventId( postId );

	// Get all inner blocks.
	const innerBlocks = useSelect( ( select ) => {
		const { getBlock } = select( 'core/block-editor' );
		const block = getBlock( clientId );
		return block?.innerBlocks || [];
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
				if ( 'gatherpress_rsvp_form_guests' === fieldName ) {
					shouldDisable = 0 === parseInt( maxAttendanceLimit, 10 );
				} else if ( 'gatherpress_rsvp_form_anonymous' === fieldName ) {
					shouldDisable = ! enableAnonymousRsvp;
				}

				// Only process fields that have conditional visibility.
				if ( 'gatherpress_rsvp_form_guests' === fieldName || 'gatherpress_rsvp_form_anonymous' === fieldName ) {
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

	/**
	 * Recursively collect visibility styles from blocks with metadata.
	 *
	 * @param {Array} blocks The blocks array.
	 * @return {Array} Array of CSS rules.
	 */
	const collectVisibilityStyles = useCallback( ( blocks ) => {
		const styles = [];

		blocks.forEach( ( block ) => {
			const visibility = block.attributes?.metadata?.gatherpressRsvpFormVisibility;

			if ( visibility && shouldHideBlock( visibility, formState ) ) {
				const selector = `#block-${ block.clientId }`;
				styles.push( `${ selector } { display: none !important; }` );
			}

			// Recursively process inner blocks.
			if ( block.innerBlocks && 0 < block.innerBlocks.length ) {
				styles.push( ...collectVisibilityStyles( block.innerBlocks ) );
			}
		} );

		return styles;
	}, [ formState ] );

	// Generate CSS for visibility based on form state.
	useEffect( () => {
		const styles = collectVisibilityStyles( innerBlocks );
		const editorDoc = getEditorDocument();

		// Inject styles into the correct document (iframe in FSE, main document otherwise).
		const styleId = `gatherpress-form-visibility-${ clientId }`;
		let styleElement = editorDoc.getElementById( styleId );

		if ( ! styleElement ) {
			styleElement = editorDoc.createElement( 'style' );
			styleElement.id = styleId;
			editorDoc.head.appendChild( styleElement );
		}

		styleElement.textContent = styles.join( '\n' );

		// Cleanup on unmount.
		return () => {
			styleElement?.remove();
		};
	}, [ formState, innerBlocks, clientId, collectVisibilityStyles ] );

	// Apply form field visibility via CSS when event settings change.
	useEffect( () => {
		const editorDoc = getEditorDocument();
		const styleId = `gatherpress-rsvp-form-visibility-${ clientId }`;
		let styleElement = editorDoc.getElementById( styleId );

		if ( ! styleElement ) {
			styleElement = editorDoc.createElement( 'style' );
			styleElement.id = styleId;
			editorDoc.head.appendChild( styleElement );
		}

		const styles = [];

		// Hide guest count field if max attendance limit is 0.
		if ( 0 === parseInt( maxAttendanceLimit, 10 ) ) {
			styles.push( `#block-${ clientId } .gatherpress-rsvp-field-guests { opacity: ${ DISABLED_FIELD_OPACITY }; }` );
		}

		// Hide anonymous field if anonymous RSVP is disabled.
		if ( ! enableAnonymousRsvp ) {
			styles.push( `#block-${ clientId } .gatherpress-rsvp-field-anonymous { opacity: ${ DISABLED_FIELD_OPACITY }; }` );
		}

		styleElement.textContent = styles.join( '\n' );

		// Cleanup on unmount.
		return () => {
			styleElement?.remove();
		};
	}, [ maxAttendanceLimit, enableAnonymousRsvp, clientId ] );

	const blockProps = useBlockProps( {
		style: {
			opacity: ( isInFSETemplate() || isValidEvent ) ? 1 : DISABLED_FIELD_OPACITY,
		},
	} );

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
							{
								label: __( 'Past (event has ended)', 'gatherpress' ),
								value: 'past',
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
