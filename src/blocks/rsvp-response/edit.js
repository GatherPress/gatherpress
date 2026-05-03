/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
import { applyFilters } from '@wordpress/hooks';
import {
	BlockContextProvider,
	BlockControls,
	InnerBlocks,
	useBlockProps,
	InspectorControls,
	store as blockEditorStore,
} from '@wordpress/block-editor';
import {
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalNumberControl as NumberControl,
	PanelBody,
	ToggleControl,
	ToolbarButton,
	ToolbarGroup,
	Spinner,
} from '@wordpress/components';
import { useState, useEffect } from '@wordpress/element';
import { useDispatch, useSelect } from '@wordpress/data';
import { createBlock } from '@wordpress/blocks';
import apiFetch from '@wordpress/api-fetch';

/**
 * Internal dependencies
 */
import RsvpManager from './rsvp-manager';
import ATTENDEE_GRID_WITH_FILTER_TEMPLATE from './templates/attendee-grid-with-filter';
import PatternPicker, { PatternChooserModal } from '../../components/PatternPicker';
import { hasValidEventId, usePostTypeSupports, DISABLED_FIELD_OPACITY, getEventMeta, isRsvpEnabledForEvent } from '../../helpers/event';
import { getEditorDocument, isInFSETemplate } from '../../helpers/editor';
import { getFromSettings } from '../../helpers/editor-settings';
import { EVENT_REST_API } from '../../helpers/namespace';

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
 * Starter patterns offered by the RSVP Response block's pattern picker.
 *
 * Lets other plugins or themes register their own RSVP Response layouts
 * without forking the block. Each entry is shaped
 * `{ name, title, description, template }` — `template` is an `InnerBlocks`
 * tuple tree (`[ blockName, attributes, innerBlocks ]`).
 *
 * @since 1.0.0
 *
 * @param {Array} patterns Default array containing the bundled
 *                         "Attendee Grid with Filter" pattern.
 * @return {Array} Patterns shown in the picker modal, in display order.
 *
 * @example
 *   addFilter(
 *     'gatherpress.rsvpResponsePatterns',
 *     'my-plugin/extra-rsvp-pattern',
 *     ( patterns ) => [ ...patterns, {
 *       name: 'my-plugin/compact',
 *       title: __( 'Compact', 'my-plugin' ),
 *       description: __( '...', 'my-plugin' ),
 *       template: [ ... ],
 *     } ]
 *   );
 */
/**
 * Default template seeded into auto-loaded RSVP Response blocks.
 *
 * Fires only when the picker is suppressed (the canonical instance on a new
 * event post — see the post type's `template` arg setting `patternPicked` to
 * true). Lets a plugin or theme swap the layout that appears without the
 * user having to click through the picker. The picker itself is filterable
 * separately via `gatherpress.rsvpResponsePatterns`.
 *
 * @since 1.0.0
 *
 * @param {Array} template Default `InnerBlocks` tuple tree —
 *                         `[ blockName, attributes, innerBlocks ]` — that
 *                         matches the bundled "Attendee Grid with Filter"
 *                         pattern.
 * @return {Array} Tuple tree handed to `<InnerBlocks template={ ... } />`.
 */
const DEFAULT_TEMPLATE = applyFilters(
	'gatherpress.rsvpResponseDefaultTemplate',
	ATTENDEE_GRID_WITH_FILTER_TEMPLATE
);

const PATTERNS = applyFilters( 'gatherpress.rsvpResponsePatterns', [
	{
		name: 'gatherpress/attendee-grid-with-filter',
		title: __( 'Attendee Grid with Filter', 'gatherpress' ),
		description: __(
			'A status filter (Attending / Waiting List / Not Attending) above a three-column grid of attendee avatars.',
			'gatherpress'
		),
		template: ATTENDEE_GRID_WITH_FILTER_TEMPLATE,
	},
] );

/**
 * Fetch RSVP responses from the API.
 *
 * @param {number} postId The post ID for which to fetch RSVP responses.
 * @return {Promise<Object>} The RSVP responses data.
 */
async function fetchRsvpResponses( postId ) {
	return apiFetch( {
		path: `${ EVENT_REST_API }/rsvp-responses?post_id=${ postId }`,
	} );
}

/**
 * Edit component for the GatherPress RSVP Response block.
 *
 * This component handles the rendering and logic for the editor interface
 * of the GatherPress RSVP Response block. It fetches RSVP data, manages
 * block-specific state, and passes relevant context to child blocks.
 *
 * @param {Object}   root0               - The props object passed to the component.
 * @param {Object}   root0.attributes    - Block attributes containing configuration and data.
 * @param {Object}   root0.context       - Block context data containing postId and event info.
 * @param {Function} root0.setAttributes - Function to update block attributes.
 * @param {string}   root0.clientId      - The unique ID of this block instance, used to seed inner blocks when a pattern is picked.
 * @since 1.0.0
 *
 * @return {JSX.Element} The rendered edit interface for the block.
 */
const Edit = ( { attributes, setAttributes, context, clientId } ) => {
	const [ editMode, setEditMode ] = useState( false );
	const [ showEmptyRsvpBlock, setShowEmptyRsvpBlock ] = useState( false );
	const [ defaultStatus, setDefaultStatus ] = useState( 'attending' );
	const [ responses, setResponses ] = useState( null );
	const [ loading, setLoading ] = useState( true );
	const [ error, setError ] = useState( null );
	const [ isToolbarChooserOpen, setIsToolbarChooserOpen ] = useState( false );

	// Check if we're inside a query loop and if context is an RSVP-enabled post type.
	// `usePostTypeSupports` is reactive so the block re-renders the moment the
	// post-type definition resolves; the non-reactive variant would miss it
	// and leave the block permanently dimmed in Query Loops.
	const isDescendentOfQueryLoop = Number.isFinite( context?.queryId );
	const isEventContext = usePostTypeSupports( 'gatherpress-rsvp', context?.postType );
	// Editor-side check (no postType arg) for the inline edit toolbar — only
	// surface it when editing an RSVP-supporting post directly. Reactive so
	// the toolbar appears once supports load instead of staying hidden.
	const isEditingRsvpPost = usePostTypeSupports( 'gatherpress-rsvp' );
	const hasExplicitOverride = !! attributes?.postId;

	// Only use postId if context is an event or have an explicit override.
	const postId =
		( attributes?.postId || null ) ??
		( ( isDescendentOfQueryLoop || isEventContext ) ? context?.postId : null ) ??
		null;
	const { rsvpLimitEnabled, rsvpLimit, patternPicked } = attributes;
	const { replaceInnerBlocks } = useDispatch( blockEditorStore );

	// Only show the pattern picker on a brand-new block — once the user has
	// picked a pattern OR clicked "Start blank" (`patternPicked` flips true),
	// or if the block already carries inner blocks from a prior session, fall
	// through to the normal `<InnerBlocks />` flow. Reading inner-block count
	// via `useSelect` keeps existing posts (which never had `patternPicked`
	// set) from suddenly seeing the picker.
	const innerBlockCount = useSelect(
		( select ) => select( blockEditorStore ).getBlocks( clientId ).length,
		[ clientId ]
	);
	const showPatternPicker =
		! patternPicked && 0 === innerBlockCount;

	const handlePatternPick = ( pattern ) => {
		replaceInnerBlocks( clientId, templateToBlocks( pattern.template ) );
		setAttributes( { patternPicked: true } );
	};

	// Check if block has a valid event connection. An explicit override
	// (`attributes.postId`) is a third valid path alongside Query Loop and
	// event-supporting host: it targets a specific event post regardless of
	// the host's post type. Wrap in `useSelect` so the gate re-evaluates when
	// the override target's entity record loads.
	const isValidEvent = useSelect(
		( select ) =>
			( hasExplicitOverride || isDescendentOfQueryLoop || isEventContext ) &&
			hasValidEventId( select, postId, context?.postType ),
		[
			postId,
			context?.postType,
			hasExplicitOverride,
			isDescendentOfQueryLoop,
			isEventContext,
		]
	);

	const { enableRsvp } = useSelect(
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

	useEffect( () => {
		const editorDoc = getEditorDocument();

		const updateBlockVisibility = () => {
			const emptyBlocks = editorDoc.querySelectorAll(
				'.gatherpress-rsvp-response--no-responses',
			);
			const responseBlocks = editorDoc.querySelectorAll(
				'.gatherpress--rsvp-responses',
			);

			emptyBlocks.forEach( ( block ) => {
				block.style.setProperty(
					'display',
					showEmptyRsvpBlock ? 'block' : 'none',
					'important',
				);
			} );
			responseBlocks.forEach( ( block ) => {
				if ( showEmptyRsvpBlock ) {
					block.style.setProperty( 'display', 'none', 'important' );
				} else {
					block.style.removeProperty( 'display' );
				}
			} );
		};

		// Watch for DOM changes.
		const observer = new MutationObserver( updateBlockVisibility );

		observer.observe( editorDoc.body, {
			childList: true,
			subtree: true,
			attributes: true,
			attributeFilter: [ 'class' ],
		} );

		// Initial call.
		updateBlockVisibility();

		return () => observer.disconnect();
	}, [ showEmptyRsvpBlock, responses ] );

	// Fetch responses when postId changes.
	useEffect( () => {
		if ( ! postId ) {
			setResponses( null );
			setLoading( false );
			return;
		}

		setLoading( true );
		setError( null );

		fetchRsvpResponses( postId )
			.then( ( response ) => {
				setResponses( response.data );
				setLoading( false );
			} )
			.catch( ( err ) => {
				setError( err.message );
				setLoading( false );
			} );
	}, [ postId ] );

	const onEditClick = ( e ) => {
		e.preventDefault();
		setEditMode( ! editMode );
	};

	if ( loading ) {
		return (
			<div { ...blockProps }>
				<Spinner />
			</div>
		);
	}

	if ( error ) {
		return (
			<div { ...blockProps }>
				<p>{ __( 'Failed to load RSVP responses.', 'gatherpress' ) }</p>
			</div>
		);
	}

	return (
		<div { ...blockProps }>
			<BlockContextProvider
				value={ {
					'gatherpress/rsvpResponses': responses,
					'gatherpress/rsvpLimitEnabled': rsvpLimitEnabled,
					'gatherpress/rsvpLimit': rsvpLimit,
					postId,
				} }
			>
				<InspectorControls>
					<PanelBody>
						<ToggleControl
							label={ __( 'Show Empty RSVP Block', 'gatherpress' ) }
							checked={ showEmptyRsvpBlock }
							onChange={ ( value ) => setShowEmptyRsvpBlock( value ) }
							help={ __(
								'Toggle to show or hide the Empty RSVP block.',
								'gatherpress',
							) }
						/>
						<ToggleControl
							label={ __( 'Limit RSVP Display', 'gatherpress' ) }
							checked={ rsvpLimitEnabled }
							onChange={ () =>
								setAttributes( {
									rsvpLimitEnabled: ! rsvpLimitEnabled,
								} )
							}
							help={ __(
								'Enable to limit the number of RSVPs displayed in this block.',
								'gatherpress',
							) }
						/>
						{ rsvpLimitEnabled && (
							<NumberControl
								label={ __( 'RSVP Display Limit', 'gatherpress' ) }
								value={ rsvpLimit }
								onChange={ ( value ) =>
									setAttributes( {
										rsvpLimit: parseInt( value, 10 ) || 8,
									} )
								}
								min={ 1 }
								max={ 100 }
								help={ __(
									'Set the maximum number of RSVPs to display. Default is 8.',
									'gatherpress',
								) }
							/>
						) }
					</PanelBody>
				</InspectorControls>
				{ isEditingRsvpPost && (
					<BlockControls>
						<ToolbarGroup>
							<ToolbarButton
								label={ __( 'Edit', 'gatherpress' ) }
								text={
									editMode
										? __( 'Preview', 'gatherpress' )
										: __( 'Edit', 'gatherpress' )
								}
								onClick={ onEditClick }
							/>
						</ToolbarGroup>
					</BlockControls>
				) }
				{ ! editMode && ! showPatternPicker && (
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
				) }
				{ isToolbarChooserOpen && (
					<PatternChooserModal
						patterns={ PATTERNS }
						onPick={ handlePatternPick }
						onClose={ () => setIsToolbarChooserOpen( false ) }
					/>
				) }
				{ editMode && (
					<RsvpManager
						defaultStatus={ defaultStatus }
						setDefaultStatus={ setDefaultStatus }
					/>
				) }
				{ ! editMode && showPatternPicker && (
					<PatternPicker
						label={ __( 'RSVP Response', 'gatherpress' ) }
						icon="groups"
						instructions={ __(
							'Choose a pattern for the RSVP response.',
							'gatherpress'
						) }
						patterns={ PATTERNS }
						showStartBlank={ false }
						onPick={ handlePatternPick }
					/>
				) }
				{ ! editMode && ! showPatternPicker && (
					// Auto-seed the default pattern when the block is hooked
					// into the event template — the server-side filter on
					// `hooked_block_gatherpress/rsvp-response` flips
					// `patternPicked` to true at insert, so we land here with
					// no inner blocks and the picker correctly suppressed.
					// Manual inserts that pass through the picker arrive here
					// only after `replaceInnerBlocks` has populated the tree,
					// so the `template` prop is a no-op for them.
					patternPicked && 0 === innerBlockCount ? (
						<InnerBlocks template={ DEFAULT_TEMPLATE } />
					) : (
						<InnerBlocks />
					)
				) }
			</BlockContextProvider>
		</div>
	);
};

export default Edit;
