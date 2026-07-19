/**
 * WordPress dependencies
 */
import { getBlockType } from '@wordpress/blocks';
import { createHigherOrderComponent } from '@wordpress/compose';
import { store as blockEditorStore } from '@wordpress/block-editor';
import { useSelect } from '@wordpress/data';
import { useState, useEffect, useRef } from '@wordpress/element';
import { addFilter, hasFilter } from '@wordpress/hooks';
import { speak } from '@wordpress/a11y';
import { __ } from '@wordpress/i18n';

/**
 * Block Guard, rethought as a canvas-only seal.
 *
 * The original Block Guard was a sidebar toggle that hand-applied `inert`,
 * opacity, and document-wide MutationObservers to fake a lock. It was
 * obtrusive, it fought core's `contentOnly` pattern mode (#1817), and it still
 * let a stray click grab and drag an inner block out of place.
 *
 * The rethink keeps the real goal — stop people from breaking these blocks by
 * accidentally editing inside them — with no toggle and no state:
 *
 *   - **Sealed** by default. The wrapper gets core's own `has-block-overlay`
 *     class, whose stylesheet rule
 *     (`.has-block-overlay .block-editor-block-list__block { pointer-events:
 *     none }`) makes every inner block non-interactive in the canvas. A click
 *     lands on the block itself and selects it rather than grabbing a piece
 *     out of it, and because the contents stay sealed, dragging then moves
 *     the whole block.
 *   - **Open** on a deliberate double-click, or Enter / Space while the block
 *     is selected. Only then do the contents become interactive, so you can
 *     edit them and drag inner blocks around.
 *   - **Re-sealed** when selection leaves the block.
 *
 * Selection deliberately does not open the block. If it did, the press after
 * selecting would land inside and drag an inner block out — so the block could
 * never be dragged once selected, which is the damage this guard exists to
 * prevent.
 *
 * A move remounts the block and re-arms the guard, which is fine: opening it
 * again is a double-click. Earlier versions tracked the open state through a
 * mousedown/click pairing that a remount could strand, leaving the block
 * sealed with no way back in — a double-click has no such pairing to lose.
 *
 * It never touches block editing mode or the block's saved attributes, so
 * **List View is completely untouched** and the block is never actually
 * locked — it just asks for one intentional click before you edit inside.
 *
 * Applies to every block declaring `supports.gatherpress.blockGuard` in its
 * `block.json`.
 *
 * @example
 * // In a block's `block.json`:
 * {
 *   "supports": {
 *     "gatherpress": {
 *       "blockGuard": true
 *     }
 *   }
 * }
 */

/**
 * Core's own class for a block whose inner blocks are sealed off in the canvas.
 * Its stylesheet rule ships in the editor content styles, so it is already
 * present in the canvas document — we only have to add the class.
 */
const OVERLAY_CLASS = 'has-block-overlay';

/**
 * ID of the visually hidden element describing the guarded state. Guarded
 * blocks point `aria-describedby` at it while sealed, so assistive technology
 * announces how to get in — the tint alone conveys nothing non-visually.
 */
const HINT_ID = 'gatherpress-block-guard-hint';

/**
 * Ensure the shared screen-reader hint exists in the canvas document.
 *
 * @param {Document} doc - The canvas document.
 *
 * @return {void}
 */
function ensureGuardHint( doc ) {
	if ( ! doc || doc.getElementById( HINT_ID ) ) {
		return;
	}

	const hint = doc.createElement( 'span' );
	hint.id = HINT_ID;
	hint.className = 'screen-reader-text';
	hint.textContent = __(
		'Protected block. Press Enter to edit the blocks inside it.',
		'gatherpress'
	);
	doc.body.appendChild( hint );
}

/**
 * Whether a block opts into Block Guard.
 *
 * @param {string} name - The block type name.
 *
 * @return {boolean} True when the block declares the `blockGuard` support.
 */
export function isBlockGuarded( name ) {
	return !! getBlockType( name )?.supports?.gatherpress?.blockGuard;
}

/**
 * Sealed state per block instance, so other blocks can react to whether an
 * ancestor is currently guarded (the venue map hides its resize handles while
 * its parent venue is sealed). Keyed by clientId, with a small subscription
 * list because the seal is derived per block, not held in the editor store.
 */
const sealedStates = new Map();
const sealedListeners = new Map();

/**
 * Publish a block's sealed state to any subscribers.
 *
 * @param {string}  clientId - The block's client ID.
 * @param {boolean} sealed   - Whether the guard is on.
 *
 * @return {void}
 */
export function publishSealedState( clientId, sealed ) {
	sealedStates.set( clientId, sealed );
	sealedListeners.get( clientId )?.forEach( ( listener ) => listener( sealed ) );
}

/**
 * Subscribe to whether a given block is currently sealed.
 *
 * Guarded blocks are sealed until they are selected, so an unknown or
 * not-yet-mounted block reports sealed.
 *
 * @param {string} clientId - The block's client ID.
 *
 * @return {boolean} True while the block's guard is on.
 */
export function useIsBlockSealed( clientId ) {
	const [ sealed, setSealed ] = useState(
		() => sealedStates.get( clientId ) ?? true
	);

	useEffect( () => {
		if ( ! clientId ) {
			setSealed( true );
			return undefined;
		}

		if ( ! sealedListeners.has( clientId ) ) {
			sealedListeners.set( clientId, new Set() );
		}

		const listeners = sealedListeners.get( clientId );
		listeners.add( setSealed );
		setSealed( sealedStates.get( clientId ) ?? true );

		return () => {
			listeners.delete( setSealed );
		};
	}, [ clientId ] );

	return sealed;
}

/**
 * Tint applied while a guarded block is selected, so it reads as a protected
 * unit you have hold of — in the spirit of the tint core gives template parts
 * and synced patterns.
 */
const GUARD_TINT = {
	backgroundColor: 'rgba(30, 58, 233, 0.04)',
	boxShadow: 'inset 0 0 0 1px rgba(30, 58, 233, 0.24)',
};

/**
 * The document that holds the block canvas. In the iframed post and site
 * editors the blocks live inside the `editor-canvas` iframe; otherwise they
 * are in the main document.
 *
 * @return {Document} The document containing the rendered blocks.
 */
export function getCanvasDocument() {
	const iframe = document.querySelector( 'iframe[name="editor-canvas"]' );

	return iframe?.contentDocument || document;
}

/**
 * Higher-Order Component that seals a block's inner blocks until the block is
 * selected.
 *
 * @param {Function} BlockListBlock - The original BlockListBlock component.
 *
 * @return {Function} The wrapped BlockListBlock.
 */
export const withBlockGuard = createHigherOrderComponent( ( BlockListBlock ) => {
	return ( props ) => {
		const { name, clientId, wrapperProps } = props;

		if ( ! isBlockGuarded( name ) ) {
			return <BlockListBlock { ...props } />;
		}

		// Where selection sits relative to this block: on the block itself, or
		// on one of its inner blocks.
		const { isSelf, isInner } = useSelect(
			( select ) => {
				const store = select( blockEditorStore );
				return {
					isSelf: store.isBlockSelected( clientId ),
					isInner: store.hasSelectedInnerBlock( clientId, true ),
				};
			},
			[ clientId ],
		);

		// Entering is a deliberate gesture, kept separate from selection.
		//
		// Selection must NOT open the block: if it did, the press after
		// selecting would land inside and drag an inner block out — so the
		// whole block could never be dragged once selected, which is the exact
		// damage the guard exists to prevent. So a single click selects the
		// block and it stays sealed (a drag then moves the whole block), and
		// only a double-click — or Enter/Space on the selected block — opens
		// it. Selecting something inside (via List View, say) counts as being
		// in.
		const [ entered, setEntered ] = useState( false );
		const sealed = ! entered && ! isInner;

		// Leaving the block re-arms the guard.
		useEffect( () => {
			if ( ! isSelf && ! isInner && entered ) {
				setEntered( false );
			}
		}, [ isSelf, isInner, entered ] );

		const onDoubleClick = ( event ) => {
			setEntered( true );
			wrapperProps?.onDoubleClick?.( event );
		};

		const onKeyDown = ( event ) => {
			if (
				sealed &&
				isSelf &&
				( 'Enter' === event.key || ' ' === event.key )
			) {
				event.preventDefault();
				event.stopPropagation();
				setEntered( true );
				return;
			}

			wrapperProps?.onKeyDown?.( event );
		};

		// Publish for descendants (the venue map drops its resize handles
		// while its parent venue is sealed).
		useEffect( () => {
			publishSealedState( clientId, sealed );
		}, [ clientId, sealed ] );

		useEffect( () => {
			return () => {
				sealedListeners.delete( clientId );
			};
		}, [ clientId ] );

		// The guarded state is conveyed visually by a tint, which says nothing
		// to assistive technology; describe it while sealed and announce when
		// it opens.
		useEffect( () => {
			ensureGuardHint( getCanvasDocument() );
		}, [] );

		const wasSealed = useRef( sealed );

		useEffect( () => {
			if ( wasSealed.current && ! sealed ) {
				speak(
					__( 'Block unlocked. You can edit its contents.', 'gatherpress' ),
					'polite'
				);
			}

			wasSealed.current = sealed;
		}, [ sealed ] );

		const className = [ props.className, sealed && OVERLAY_CLASS ]
			.filter( Boolean )
			.join( ' ' );

		return (
			<BlockListBlock
				{ ...props }
				className={ className }
				wrapperProps={ {
					...wrapperProps,
					onDoubleClick,
					onKeyDown,
					// A sealed block gets a pointer cursor to show it is the
					// thing a click will land on. Selecting it tints it, so the
					// block reads as a protected unit you have hold of — the
					// tint appears on click and clears when you click away.
					style: {
						...wrapperProps?.style,
						...( sealed ? { cursor: 'pointer' } : {} ),
						...( isSelf ? GUARD_TINT : {} ),
					},
					'aria-describedby': sealed
						? [ wrapperProps?.[ 'aria-describedby' ], HINT_ID ]
							.filter( Boolean )
							.join( ' ' )
						: wrapperProps?.[ 'aria-describedby' ],
				} }
			/>
		);
	};
}, 'withBlockGuard' );

/**
 * Register the HOC as a filter for the BlockListBlock component.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/filters/block-filters/
 */
// The module can land in multiple webpack chunks; addFilter does not dedupe by
// namespace, so guard against a second registration.
if (
	false === hasFilter( 'editor.BlockListBlock', 'gatherpress/with-block-guard' )
) {
	addFilter(
		'editor.BlockListBlock',
		'gatherpress/with-block-guard',
		withBlockGuard,
	);
}
