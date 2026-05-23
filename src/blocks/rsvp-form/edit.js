/**
 * WordPress dependencies
 */
import {
	BlockControls,
	useBlockProps,
	InnerBlocks,
	InspectorControls,
	store as blockEditorStore,
} from '@wordpress/block-editor';
import {
	PanelBody,
	SelectControl,
	ToolbarButton,
	ToolbarGroup,
} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { applyFilters } from '@wordpress/hooks';
import { useState, useEffect, useCallback } from '@wordpress/element';
import { useDispatch, useSelect } from '@wordpress/data';
import { createBlock, getBlockTypes } from '@wordpress/blocks';

/**
 * Internal dependencies
 */
import STANDARD_RSVP_FORM_TEMPLATE from './templates/standard-rsvp-form';
import PatternPicker, { PatternChooserModal } from '../../components/PatternPicker';
import { hasValidEventId, DISABLED_FIELD_OPACITY, getEventMeta, isRsvpEnabledForEvent, isOpenRsvpEnabled } from '../../helpers/event';
import { isInFSETemplate, getEditorDocument } from '../../helpers/editor';
import { getFromSettings } from '../../helpers/editor-settings';
import { shouldHideBlock } from './visibility';

/**
 * Recursively turn an InnerBlocks-shape `[ name, attrs, inner ]` template
 * tuple tree into instantiated block objects, ready for `replaceInnerBlocks`.
 *
 * @param {Array} template Tuples in `[ blockName, attributes, innerBlocks ]` form.
 * @return {Array} Created block instances.
 */
function templateToBlocks( template ) {
	return template.map( ( [ name, attributes, innerBlocks ] ) =>
		createBlock(
			name,
			attributes,
			templateToBlocks( innerBlocks || [] )
		)
	);
}

/**
 * Default template seeded into auto-loaded RSVP Form blocks.
 *
 * Fires only when the picker is suppressed (`patternPicked` already true on
 * insert — e.g. a future post type template seeding the block). Lets a
 * plugin or theme swap the layout that appears without the user clicking
 * through the picker. The picker itself is filterable separately via
 * `gatherpress.rsvpFormPatterns`.
 *
 * @since 1.0.0
 *
 * @param {Array} template Default `InnerBlocks` tuple tree —
 *                         `[ blockName, attributes, innerBlocks ]` — that
 *                         matches the bundled "Standard RSVP Form" pattern.
 * @return {Array} Tuple tree handed to `<InnerBlocks template={ ... } />`.
 */
const DEFAULT_TEMPLATE = applyFilters(
	'gatherpress.rsvpFormDefaultTemplate',
	STANDARD_RSVP_FORM_TEMPLATE
);

/**
 * Starter patterns offered by the RSVP Form block's pattern picker.
 *
 * Lets other plugins or themes register their own RSVP Form layouts without
 * forking the block. Each entry is shaped
 * `{ name, title, description, template }` — `template` is an `InnerBlocks`
 * tuple tree (`[ blockName, attributes, innerBlocks ]`).
 *
 * @since 1.0.0
 *
 * @param {Array} patterns Default array containing the bundled
 *                         "Standard RSVP Form" pattern.
 * @return {Array} Patterns shown in the picker modal, in display order.
 *
 * @example
 *   addFilter(
 *     'gatherpress.rsvpFormPatterns',
 *     'my-plugin/extra-rsvp-form',
 *     ( patterns ) => [ ...patterns, {
 *       name: 'my-plugin/minimal',
 *       title: __( 'Minimal', 'my-plugin' ),
 *       description: __( '...', 'my-plugin' ),
 *       template: [ ... ],
 *     } ]
 *   );
 */
const PATTERNS = applyFilters( 'gatherpress.rsvpFormPatterns', [
	{
		name: 'gatherpress/standard-rsvp-form',
		title: __( 'Standard RSVP Form', 'gatherpress' ),
		description: __(
			'Name + email + guest count + anonymous opt-in + email-updates opt-in, plus success and past-event message groups.',
			'gatherpress'
		),
		template: STANDARD_RSVP_FORM_TEMPLATE,
	},
] );

const Edit = ( { attributes, setAttributes, clientId, context } ) => {
	const [ formState, setFormState ] = useState( 'default' );
	const [ isToolbarChooserOpen, setIsToolbarChooserOpen ] = useState( false );
	const { patternPicked } = attributes;
	const { replaceInnerBlocks } = useDispatch( blockEditorStore );
	// Normalize empty strings to null so fallback to context.postId works correctly.
	const postId = ( attributes?.postId || null ) ?? context?.postId ?? null;

	// Calculate allowed blocks - all blocks except gatherpress/rsvp-form.
	const allowedBlocks = getBlockTypes()
		.map( ( blockType ) => blockType.name )
		.filter( ( name ) => 'gatherpress/rsvp-form' !== name );

	// Get event data - either from override postId or current post.
	const { maxGuestLimit: maxAttendanceLimit, enableRsvp, enableAnonymousRsvp } = useSelect(
		( select ) => getEventMeta( select, postId, attributes ),
		[ postId, attributes ]
	);

	// Read per-event open RSVP setting (integer 0/1; undefined/null defaults to enabled).
	const enableOpenRsvpPerEvent = useSelect( ( select ) => {
		const meta = select( 'core/editor' )?.getEditedPostAttribute( 'meta' );
		const rawValue = meta?.gatherpress_enable_open_rsvp;
		return rawValue === undefined || null === rawValue ? true : 0 !== rawValue;
	}, [] );

	// Check if block has a valid event connection. Wrap in `useSelect` so the
	// gate re-evaluates when the override target's entity record loads.
	const isValidEvent = useSelect(
		( select ) => hasValidEventId( select, postId ),
		[ postId ]
	);

	// Get all inner blocks.
	const innerBlocks = useSelect( ( select ) => {
		const { getBlock } = select( 'core/block-editor' );
		const block = getBlock( clientId );
		return block?.innerBlocks || [];
	}, [ clientId ] );

	// Show the pattern picker on a brand-new block. Once the user picks a
	// pattern (`patternPicked` flips true) or the block already carries inner
	// blocks from a prior session, fall through to the normal `<InnerBlocks />`
	// flow. Reading inner-block count keeps existing posts (which never had
	// `patternPicked` set) from suddenly seeing the picker.
	const showPatternPicker = ! patternPicked && 0 === innerBlocks.length;

	const handlePatternPick = ( pattern ) => {
		replaceInnerBlocks( clientId, templateToBlocks( pattern.template ) );
		setAttributes( { patternPicked: true } );
	};

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

	const rsvpMode = getFromSettings( 'rsvpMode' ) ?? 'all_on';
	const enableOpenRsvp = getFromSettings( 'enableOpenRsvp' ) ?? true;

	const blockProps = useBlockProps( {
		style: {
			opacity:
				showPatternPicker ||
				isInFSETemplate() ||
				( isValidEvent &&
					isRsvpEnabledForEvent( rsvpMode, enableRsvp ) &&
					isOpenRsvpEnabled( enableOpenRsvp ) &&
					enableOpenRsvpPerEvent )
					? 1
					: DISABLED_FIELD_OPACITY,
		},
	} );

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Preview Settings', 'gatherpress' ) }>
					<SelectControl
						__next40pxDefaultSize
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
			{ ! showPatternPicker && (
				<BlockControls>
					<ToolbarGroup>
						<ToolbarButton
							text={ __( 'Choose pattern', 'gatherpress' ) }
							onClick={ () => setIsToolbarChooserOpen( true ) }
						/>
					</ToolbarGroup>
				</BlockControls>
			) }
			{ isToolbarChooserOpen && (
				<PatternChooserModal
					patterns={ PATTERNS }
					onPick={ handlePatternPick }
					onClose={ () => setIsToolbarChooserOpen( false ) }
				/>
			) }
			<div { ...blockProps }>
				{ showPatternPicker && (
					<PatternPicker
						label={ __( 'RSVP Form', 'gatherpress' ) }
						icon="forms"
						instructions={ __(
							'Choose a pattern for the RSVP form.',
							'gatherpress'
						) }
						patterns={ PATTERNS }
						showStartBlank={ false }
						onPick={ handlePatternPick }
					/>
				) }
				{ ! showPatternPicker &&
					( patternPicked && 0 === innerBlocks.length ? (
						<InnerBlocks
							template={ DEFAULT_TEMPLATE }
							allowedBlocks={ allowedBlocks }
						/>
					) : (
						<InnerBlocks allowedBlocks={ allowedBlocks } />
					) ) }
			</div>
		</>
	);
};

export default Edit;
