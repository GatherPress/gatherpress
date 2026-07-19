/**
 * WordPress dependencies
 */
import { getBlockType } from '@wordpress/blocks';
import { createHigherOrderComponent } from '@wordpress/compose';
import {
	store as blockEditorStore,
	useBlockEditingMode,
} from '@wordpress/block-editor';
import { useSelect } from '@wordpress/data';
import { useState, useEffect, useCallback } from '@wordpress/element';
import { addFilter, hasFilter } from '@wordpress/hooks';

/**
 * Block Guard, rebuilt on core's block editing mode.
 *
 * The original Block Guard (`block-guard.js`) was a toggle that hand-applied
 * `inert`, opacity, and a pair of document-wide MutationObservers to fake a
 * lock on a block's inner blocks. That second, independent lock is what
 * collides with core's own `contentOnly` pattern mode (issue #1817): the two
 * locks each strip the other's escape hatch.
 *
 * This prototype drops the toggle entirely and expresses the guard through the
 * one system core already uses for "safe" editing — block editing mode:
 *
 *   - **Sealed** (default): the block is `contentOnly`. Because GatherPress
 *     inner blocks are structural and carry no `role: 'content'`, contentOnly
 *     leaves nothing inside selectable or draggable, which is exactly the
 *     "don't let a stray click pull a piece out" behavior we want. The block
 *     itself stays selectable.
 *   - **Unsealed**: a deliberate gesture on the selected block — double-click,
 *     or Enter while it is focused — promotes it to `default`, giving full
 *     access to the inner blocks.
 *   - **Re-seal**: when selection leaves the block's subtree (click-out, or
 *     Escape which moves selection to the parent), it drops back to
 *     contentOnly.
 *
 * @todo Prototype scope: gated to the blocks in {@link PROTOTYPE_BLOCKS} while
 *       we validate the interaction and the contentOnly-pattern interplay from
 *       #1817. The legacy toggle still governs every other guarded block.
 */

/**
 * Blocks the editing-mode guard owns during the prototype. Everything else
 * with `supports.gatherpress.blockGuard` stays on the legacy toggle.
 */
const PROTOTYPE_BLOCKS = new Set( [ 'gatherpress/add-to-calendar' ] );

/**
 * Whether a block should be handled by this prototype guard.
 *
 * @param {string} name - The block type name.
 *
 * @return {boolean} True when the editing-mode guard owns this block.
 */
export function isEditingModeGuarded( name ) {
	return (
		PROTOTYPE_BLOCKS.has( name ) &&
		!! getBlockType( name )?.supports?.gatherpress?.blockGuard
	);
}

/**
 * ID of the injected stylesheet that hints the sealed state.
 */
const GUARD_STYLE_ID = 'gatherpress-block-guard-editing-mode-style';

/**
 * Inject the sealed-state affordance once. A sealed, selected block gets a
 * "click to edit" cursor and a soft outline so the two-step reveal is
 * discoverable now that there is no visible toggle. Idempotent via the id
 * guard, since the module can evaluate in more than one webpack chunk.
 *
 * @return {void}
 */
function injectGuardStyles() {
	if ( document.getElementById( GUARD_STYLE_ID ) ) {
		return;
	}

	const style = document.createElement( 'style' );
	style.id = GUARD_STYLE_ID;
	style.textContent = `
		[data-gatherpress-guard="sealed"] {
			cursor: pointer;
		}
		[data-gatherpress-guard="sealed"].is-selected,
		[data-gatherpress-guard="sealed"].is-hovered {
			outline: 1px dashed var(--wp-admin-theme-color, #3858e9);
			outline-offset: 2px;
		}
	`;
	document.head.appendChild( style );
}

/**
 * Resolve the document that holds the block canvas. In the iframed post and
 * site editors the blocks live inside the `editor-canvas` iframe; otherwise
 * they are in the main document.
 *
 * @return {Document} The document containing the rendered blocks.
 */
function getCanvasDocument() {
	const iframe = document.querySelector( 'iframe[name="editor-canvas"]' );

	if ( iframe?.contentDocument ) {
		return iframe.contentDocument;
	}

	return document;
}

/**
 * Find a block's wrapper element across the canvas document.
 *
 * @param {string} clientId - The block's client ID.
 *
 * @return {HTMLElement|null} The wrapper element, or null when not mounted.
 */
function getBlockElement( clientId ) {
	return getCanvasDocument().getElementById( `block-${ clientId }` );
}

/**
 * Higher-Order Component that guards a block through its editing mode.
 *
 * @param {Function} BlockEdit - The original BlockEdit component.
 *
 * @return {Function} The wrapped BlockEdit.
 */
const withEditingModeGuard = createHigherOrderComponent( ( BlockEdit ) => {
	return ( props ) => {
		const { name, clientId } = props;

		if ( ! isEditingModeGuarded( name ) ) {
			return <BlockEdit { ...props } />;
		}

		// Whether the user has deliberately entered this block.
		const [ unsealed, setUnsealed ] = useState( false );

		// Is selection currently anywhere within this block's subtree?
		const isActive = useSelect(
			( select ) => {
				const store = select( blockEditorStore );
				return (
					store.isBlockSelected( clientId ) ||
					store.hasSelectedInnerBlock( clientId, true )
				);
			},
			[ clientId ],
		);

		// Re-seal the moment selection leaves the block.
		useEffect( () => {
			if ( ! isActive && unsealed ) {
				setUnsealed( false );
			}
		}, [ isActive, unsealed ] );

		// Drive the actual protection: contentOnly seals the inner blocks,
		// default hands them over. Cleaned up automatically on unmount.
		useBlockEditingMode( unsealed ? 'default' : 'contentOnly' );

		const enter = useCallback( () => setUnsealed( true ), [] );

		// Bind the enter/exit gestures to the block wrapper. One element, no
		// document-wide observers — the wrapper receives focus when the block
		// is selected, so keydown lands here.
		useEffect( () => {
			injectGuardStyles();

			const element = getBlockElement( clientId );

			if ( ! element ) {
				return undefined;
			}

			element.dataset.gatherpressGuard = unsealed ? 'unsealed' : 'sealed';

			const onDoubleClick = () => {
				if ( ! unsealed ) {
					enter();
				}
			};

			const onKeyDown = ( event ) => {
				if ( 'Enter' === event.key && ! unsealed ) {
					event.preventDefault();
					enter();
				} else if ( 'Escape' === event.key && unsealed ) {
					setUnsealed( false );
				}
			};

			element.addEventListener( 'dblclick', onDoubleClick );
			element.addEventListener( 'keydown', onKeyDown );

			return () => {
				element.removeEventListener( 'dblclick', onDoubleClick );
				element.removeEventListener( 'keydown', onKeyDown );
				delete element.dataset.gatherpressGuard;
			};
		}, [ clientId, unsealed, enter ] );

		return <BlockEdit { ...props } />;
	};
}, 'withEditingModeGuard' );

/**
 * Register the HOC as a filter for the BlockEdit component.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/filters/block-filters/
 */
// The module can land in multiple webpack chunks; addFilter does not dedupe by
// namespace, so guard against a second registration.
if (
	false ===
	hasFilter( 'editor.BlockEdit', 'gatherpress/with-editing-mode-guard' )
) {
	addFilter(
		'editor.BlockEdit',
		'gatherpress/with-editing-mode-guard',
		withEditingModeGuard,
	);
}
