/**
 * WordPress dependencies
 */
import {
	BlockControls,
	InnerBlocks,
	InspectorControls,
	useBlockProps,
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
import { useEffect, useCallback, useState } from '@wordpress/element';
import { useDispatch, useSelect } from '@wordpress/data';
import { createBlock, parse, serialize } from '@wordpress/blocks';

/**
 * Internal dependencies
 */
import RSVP_BUTTON_WITH_MODAL_TEMPLATES from './templates/rsvp-button-with-modal';
import PatternPicker, { PatternChooserModal } from '../../components/PatternPicker';
import { hasValidEventId, DISABLED_FIELD_OPACITY, getEventMeta, usePostTypeSupports, isRsvpEnabledForEvent } from '../../helpers/event';
import { isInFSETemplate, getEditorDocument } from '../../helpers/editor';
import { getFromSettings } from '../../helpers/editor-settings';
import { parseSerializedInnerBlocks } from './helpers';

/**
 * Starter patterns offered by the RSVP block's pattern picker.
 *
 * Unlike single-template blocks, RSVP carries five inner-block templates
 * (one per status) that get serialized into the `serializedInnerBlocks`
 * attribute. A pattern entry therefore exposes both `template` (the
 * `no_status` tree, used by the modal's `<BlockPreview>` thumbnail and as
 * the initially-active inner blocks on insert) and `statusTemplates` (a map
 * of status → template tuple tree, used by `handlePatternPick` to seed all
 * five statuses at once).
 *
 * Filterable via `gatherpress.rsvpPatterns` so other plugins or themes can
 * register their own RSVP layouts. Each entry is shaped
 * `{ name, title, description, template, statusTemplates }`.
 *
 * @since 0.33.0
 *
 * @param {Array} patterns Default array containing the bundled "RSVP Button
 *                         with Modal" pattern.
 * @return {Array} Patterns shown in the picker modal, in display order.
 *
 * @example
 *   addFilter(
 *     'gatherpress.rsvpPatterns',
 *     'my-plugin/extra-rsvp-pattern',
 *     ( patterns ) => [ ...patterns, {
 *       name: 'my-plugin/text-rsvp',
 *       title: __( 'Text-only RSVP', 'my-plugin' ),
 *       description: __( '...', 'my-plugin' ),
 *       template: [],
 *       statusTemplates: {
 *         no_status: [],
 *         attending: [],
 *         waiting_list: [],
 *         not_attending: [],
 *         past: [],
 *       },
 *     } ]
 *   );
 */
const PATTERNS = applyFilters( 'gatherpress.rsvpPatterns', [
	{
		name: 'gatherpress/rsvp-button-with-modal',
		title: __( 'RSVP Button with Modal', 'gatherpress' ),
		description: __(
			'An RSVP button that opens a modal — five inner-block layouts, one per RSVP status (no response, attending, waiting list, not attending, past).',
			'gatherpress'
		),
		template: RSVP_BUTTON_WITH_MODAL_TEMPLATES.no_status,
		statusTemplates: RSVP_BUTTON_WITH_MODAL_TEMPLATES,
	},
] );

/**
 * Default per-status template bundle seeded into auto-loaded RSVP blocks.
 *
 * Fires only when the picker is suppressed (the canonical instance on a new
 * event post — `patternPicked: true` set in the post type `template` arg).
 * Lets a plugin or theme swap the bundle that auto-loads without the user
 * clicking through the picker. The picker itself is filterable separately
 * via `gatherpress.rsvpPatterns`.
 *
 * @since 0.33.0
 *
 * @param {Object<string, Array>} bundle Default per-status template map
 *                                       matching the bundled "RSVP Button
 *                                       with Modal" pattern.
 * @return {Object<string, Array>} Map handed to the auto-seed flow.
 */
const DEFAULT_STATUS_TEMPLATES = applyFilters(
	'gatherpress.rsvpDefaultStatusTemplates',
	RSVP_BUTTON_WITH_MODAL_TEMPLATES
);

/**
 * Helper function to convert a template to blocks.
 *
 * @param {Array} template The block template structure.
 *
 * @return {Array} Array of blocks created from the template.
 */
function templateToBlocks( template ) {
	return template.map( ( [ name, attributes, innerBlocks ] ) => {
		return createBlock(
			name,
			attributes,
			templateToBlocks( innerBlocks || [] ),
		);
	} );
}

/**
 * Edit component for the GatherPress RSVP block.
 *
 * @param {Object}   props               The props passed to the component.
 * @param {Object}   props.attributes    Block attributes.
 * @param {Function} props.setAttributes Function to update block attributes.
 * @param {string}   props.clientId      The unique ID of the block instance.
 * @param {Object}   props.context       Block context data containing postId and event info.
 *
 * @since 0.33.0
 *
 * @return {JSX.Element} The rendered edit interface for the RSVP block.
 */
const Edit = ( { attributes, setAttributes, clientId, context } ) => {
	const {
		serializedInnerBlocks = '{}',
		selectedStatus,
		patternPicked,
	} = attributes;
	const { replaceInnerBlocks } = useDispatch( blockEditorStore );
	const [ isToolbarChooserOpen, setIsToolbarChooserOpen ] = useState( false );

	// Show the pattern picker on a brand-new block — no statuses populated yet
	// AND the user hasn't already committed to a pattern. Existing posts (with
	// hydrated `serializedInnerBlocks`) and auto-included instances (which
	// land with `patternPicked: true`) bypass the picker.
	const hasAnyStatusSerialized =
		0 < Object.keys( parseSerializedInnerBlocks( serializedInnerBlocks ) ).length;
	const showPatternPicker = ! patternPicked && ! hasAnyStatusSerialized;

	const handlePatternPick = ( pattern ) => {
		// RSVP patterns carry a per-status `statusTemplates` map (alongside
		// the `template` used by the modal preview). Serialize every status
		// up front so all five inspector tabs are pre-populated, then
		// replaceInnerBlocks for the active status so the canvas shows
		// content immediately.
		const bundle = pattern.statusTemplates || {
			[ selectedStatus ]: pattern.template,
		};
		const serialized = Object.fromEntries(
			Object.entries( bundle ).map( ( [ status, template ] ) => [
				status,
				serialize( templateToBlocks( template ) ),
			] )
		);

		setAttributes( {
			serializedInnerBlocks: JSON.stringify( serialized ),
			patternPicked: true,
		} );

		const activeTemplate =
			bundle[ selectedStatus ] || pattern.template;
		replaceInnerBlocks( clientId, templateToBlocks( activeTemplate ) );
	};

	// Check if we're inside a query loop and if context is an RSVP-enabled post type.
	// `usePostTypeSupports` is reactive so the block re-renders the moment the
	// post-type definition resolves — non-reactive checks miss this and leave
	// the block permanently dimmed in Query Loops.
	const isDescendentOfQueryLoop = Number.isFinite( context?.queryId );
	const isEventContext = usePostTypeSupports( 'gatherpress-rsvp', context?.postType );
	const hasExplicitOverride = !! attributes?.postId;

	// Only use postId if context is an event or have an explicit override.
	const postId =
		( attributes?.postId || null ) ??
		( ( isDescendentOfQueryLoop || isEventContext ) ? context?.postId : null ) ??
		null;

	// Check if block has a valid event connection. An explicit override
	// (`attributes.postId`) is a third valid path alongside Query Loop and
	// event-supporting host: it targets a specific event post regardless of
	// the host's post type. Wrap in `useSelect` so the gate re-evaluates when
	// the override target's entity record loads — `hasValidEventId` reads
	// `getEntityRecord` / `getEntityRecords`, which only emit subscription
	// updates when called via the `useSelect` callback's `select`.
	//
	// Inside a Query Loop the host's `context.postType` is the iterated
	// post type (e.g. `production`). If that post type doesn't declare
	// `gatherpress-rsvp` support, the block is in a context where there's
	// no RSVP to render — gate on `isEventContext` so the loop-iterated
	// case dims with the rest, instead of staying bright on every
	// production card just because the post exists (#1608 follow-on).
	const isValidEvent = useSelect(
		( select ) =>
			( hasExplicitOverride || ( isDescendentOfQueryLoop && isEventContext ) || isEventContext ) &&
			hasValidEventId( select, postId, context?.postType ),
		[
			postId,
			context?.postType,
			hasExplicitOverride,
			isDescendentOfQueryLoop,
			isEventContext,
		]
	);

	// Get the current inner blocks
	const innerBlocks = useSelect(
		( select ) => select( blockEditorStore ).getBlocks( clientId ),
		[ clientId ],
	);

	// Get event data - either from override postId or current post.
	const { maxGuestLimit: maxNumberOfGuests, enableRsvp, enableAnonymousRsvp } = useSelect(
		( select ) => getEventMeta( select, postId, attributes ),
		[ postId, attributes ]
	);

	const rsvpMode = getFromSettings( 'rsvpMode' ) ?? 'all_on';

	const blockProps = useBlockProps( {
		style: {
			opacity:
				showPatternPicker ||
				isInFSETemplate() ||
				( isValidEvent && isRsvpEnabledForEvent( rsvpMode, enableRsvp ) )
					? 1
					: DISABLED_FIELD_OPACITY,
		},
	} );
	/**
	 * Apply conditional visibility class to form fields based on event settings.
	 *
	 * @param {Array} blocks Array of blocks to process.
	 *
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
					shouldDisable = 0 === parseInt( maxNumberOfGuests, 10 );
				} else if ( 'gatherpress_rsvp_anonymous' === fieldName ) {
					// enableAnonymousRsvp is now a boolean from the useSelect conversion.
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
	}, [ maxNumberOfGuests, enableAnonymousRsvp ] );

	// Save the provided inner blocks to the serializedInnerBlocks attribute
	const saveInnerBlocks = useCallback(
		( state, newState, blocks ) => {
			const currentSerializedBlocks =
				parseSerializedInnerBlocks( serializedInnerBlocks );

			// Encode the serialized content for safe use in HTML attributes
			const sanitizedSerialized = serialize( blocks );

			const updatedBlocks = {
				...currentSerializedBlocks,
				[ state ]: sanitizedSerialized,
			};

			delete updatedBlocks[ newState ];

			setAttributes( {
				serializedInnerBlocks: JSON.stringify( updatedBlocks ),
			} );
		},
		[ serializedInnerBlocks, setAttributes ],
	);

	// Load inner blocks for a given state
	const loadInnerBlocksForState = useCallback(
		( state ) => {
			const savedBlocks =
				parseSerializedInnerBlocks( serializedInnerBlocks )[ state ];
			if ( savedBlocks && 0 < savedBlocks.length ) {
				replaceInnerBlocks( clientId, parse( savedBlocks, {} ) );
			}
		},
		[ clientId, replaceInnerBlocks, serializedInnerBlocks ],
	);

	// Handle status change: save current inner blocks and load new ones
	const handleStatusChange = ( newStatus ) => {
		loadInnerBlocksForState( newStatus ); // Load blocks for the new state
		setAttributes( {
			selectedStatus: newStatus,
		} ); // Update the state
		saveInnerBlocks( selectedStatus, newStatus, innerBlocks ); // Save current inner blocks before switching state
	};

	// Hydrate inner blocks for all statuses if not set. Skipped while the
	// pattern picker is showing — auto-seeding before the user picks would
	// commit them to the default layout silently.
	useEffect( () => {
		if ( showPatternPicker ) {
			return;
		}

		const hydrateInnerBlocks = () => {
			const currentSerializedBlocks =
				parseSerializedInnerBlocks( serializedInnerBlocks );

			const updatedBlocks = Object.keys( DEFAULT_STATUS_TEMPLATES ).reduce(
				( updatedSerializedBlocks, templateKey ) => {
					if ( currentSerializedBlocks[ templateKey ] ) {
						updatedSerializedBlocks[ templateKey ] =
							currentSerializedBlocks[ templateKey ];

						return updatedSerializedBlocks;
					}

					if ( templateKey !== selectedStatus ) {
						const blocks = templateToBlocks(
							DEFAULT_STATUS_TEMPLATES[ templateKey ]
						);

						updatedSerializedBlocks[ templateKey ] =
							serialize( blocks );
					}

					return updatedSerializedBlocks;
				},
				{ ...currentSerializedBlocks },
			);

			setAttributes( {
				serializedInnerBlocks: JSON.stringify( updatedBlocks ),
			} );
		};

		// Adding a setTimeout with 0ms delay pushes execution to the end of the event queue,
		// ensuring WordPress has properly initialized the post state before we attempt to
		// hydrate inner blocks. This prevents false "new post" detection that could interfere
		// with block initialization.
		setTimeout( () => {
			hydrateInnerBlocks();
		}, 0 );
	}, [ serializedInnerBlocks, setAttributes, selectedStatus, showPatternPicker ] );

	// Apply form field visibility via CSS when event settings change.
	useEffect( () => {
		const editorDoc = getEditorDocument();
		const styleId = `gatherpress-rsvp-visibility-${ clientId }`;
		let styleElement = editorDoc.getElementById( styleId );

		if ( ! styleElement ) {
			styleElement = editorDoc.createElement( 'style' );
			styleElement.id = styleId;
			editorDoc.head.appendChild( styleElement );
		}

		const styles = [];

		// Hide guest count field if max attendance limit is 0.
		if ( 0 === parseInt( maxNumberOfGuests, 10 ) ) {
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
	}, [ maxNumberOfGuests, enableAnonymousRsvp, clientId ] );

	return (
		<>
			{ ! showPatternPicker && (
				<>
					<BlockControls>
						<ToolbarGroup>
							<ToolbarButton
								text={ __( 'Choose pattern', 'gatherpress' ) }
								onClick={ () =>
									setIsToolbarChooserOpen( true )
								}
							/>
						</ToolbarGroup>
					</BlockControls>
					<InspectorControls>
						<PanelBody title={ __( 'RSVP Block Settings', 'gatherpress' ) }>
							<p>
								{ __(
									'Select an RSVP status to edit how this block appears for users with that status.',
									'gatherpress',
								) }
							</p>
							<SelectControl
								__next40pxDefaultSize
								label={ __( 'Edit Block Status', 'gatherpress' ) }
								value={ selectedStatus }
								options={ [
									{
										label: __(
											'No Response (Default)',
											'gatherpress',
										),
										value: 'no_status',
									},
									{
										label: __( 'Attending', 'gatherpress' ),
										value: 'attending',
									},
									{
										label: __( 'Waiting List', 'gatherpress' ),
										value: 'waiting_list',
									},
									{
										label: __( 'Not Attending', 'gatherpress' ),
										value: 'not_attending',
									},
									{
										label: __( 'Past Event', 'gatherpress' ),
										value: 'past',
									},
								] }
								onChange={ handleStatusChange }
							/>
						</PanelBody>
					</InspectorControls>
				</>
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
						label={ __( 'RSVP', 'gatherpress' ) }
						icon="insert"
						instructions={ __(
							'Choose a pattern for the RSVP block.',
							'gatherpress'
						) }
						patterns={ PATTERNS }
						showStartBlank={ false }
						onPick={ handlePatternPick }
					/>
				) }
				{ ! showPatternPicker && (
					<InnerBlocks
						template={ DEFAULT_STATUS_TEMPLATES[ selectedStatus ] }
					/>
				) }
			</div>
		</>
	);
};

export default Edit;
