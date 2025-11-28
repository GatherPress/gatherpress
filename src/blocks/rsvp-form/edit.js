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
import { hasValidEventId } from '../../helpers/event';
import { isInFSETemplate, getEditorDocument } from '../../helpers/editor';
import { shouldHideBlock } from './visibility';

const Edit = ( { attributes, clientId } ) => {
	const [ formState, setFormState ] = useState( 'default' );
	const { replaceInnerBlocks } = useDispatch( blockEditorStore );
	const { postId } = attributes;

	// Calculate allowed blocks - all blocks except gatherpress/rsvp-form.
	const allowedBlocks = getBlockTypes()
		.map( ( blockType ) => blockType.name )
		.filter( ( name ) => 'gatherpress/rsvp-form' !== name );

	// Get event data - either from override postId or current post.
	const { maxAttendanceLimit, enableAnonymousRsvp } = useSelect(
		( select ) => {
			let maxLimit;
			let enableAnonymous;

			// Check if we have a postId override.
			if ( postId ) {
				// Fetch from specific post via core data store.
				const post = select( 'core' ).getEntityRecord( 'postType', 'gatherpress_event', postId );
				maxLimit = post?.meta?.gatherpress_max_guest_limit;
				enableAnonymous = Boolean( post?.meta?.gatherpress_enable_anonymous_rsvp );
			} else {
				// Check if current post is an event.
				const currentPostType = select( 'core/editor' )?.getCurrentPostType();
				const isCurrentPostEvent = 'gatherpress_event' === currentPostType;

				if ( isCurrentPostEvent ) {
					const meta = select( 'core/editor' ).getEditedPostAttribute( 'meta' );
					maxLimit = meta?.gatherpress_max_guest_limit;
					enableAnonymous = Boolean( meta?.gatherpress_enable_anonymous_rsvp );
				}
			}

			return {
				maxAttendanceLimit: maxLimit,
				enableAnonymousRsvp: enableAnonymous,
			};
		},
		[ postId ]
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
			if ( styleElement && styleElement.parentNode ) {
				styleElement.parentNode.removeChild( styleElement );
			}
		};
	}, [ formState, innerBlocks, clientId, collectVisibilityStyles ] );

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

	const blockProps = useBlockProps( {
		style: {
			opacity: ( isInFSETemplate() || isValidEvent ) ? 1 : 0.3,
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
