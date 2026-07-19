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
 * accidentally editing inside them — with a light two-state interaction and
 * nothing else:
 *
 *   - **Sealed** (default): the block's wrapper gets core's own
 *     `has-block-overlay` class, whose stylesheet rule
 *     (`.has-block-overlay .block-editor-block-list__block { pointer-events:
 *     none }`) makes every inner block non-interactive in the canvas. A click
 *     anywhere on the block therefore falls through to the block itself — it
 *     selects the whole block, and its inner pieces can't be grabbed or dragged
 *     out by accident. The wrapper keeps its own pointer events, so the block
 *     still selects and drags normally. Once selected, it carries a soft tint
 *     so it reads as a protected unit.
 *   - **A deliberate second action** — clicking the already-selected block
 *     again, or pressing Enter / Space while it is selected — unseals it,
 *     handing over full editing of the inner blocks.
 *   - **Re-seal**: clicking out (selection leaves the block's subtree) puts the
 *     guard back on.
 *
 * The seal is only that one class on the canvas wrapper — it never touches
 * block editing mode or the block's saved attributes — so **List View is
 * completely untouched** and stays the deliberate way in for anyone who needs
 * it. The block is not locked: it stays fully editable, it just asks for one
 * intentional click before you edit inside.
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
 * list because the state lives in component state, not the editor store.
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
 * Guarded blocks are sealed until the user deliberately enters them, so an
 * unknown or not-yet-mounted block reports sealed.
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
 * Soft tint applied to a sealed, selected block so it reads as a protected
 * unit, in the spirit of the tint core gives template parts and synced
 * patterns.
 */
const SEALED_STYLE = {
	cursor: 'pointer',
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
 * Higher-Order Component that seals a block's inner blocks behind one
 * deliberate click.
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

		// Guard on by default; a deliberate action turns it off.
		const [ sealed, setSealed ] = useState( true );

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

		const isActive = isSelf || isInner;

		// Has selection been inside the block since it was unsealed? This is
		// what lets "select the parent again" re-arm the guard without the
		// unsealing click itself instantly re-sealing (at that moment the block
		// is selected but nothing inner has been touched yet).
		const enteredInner = useRef( false );

		// Re-seal on either exit route: clicking away from the block entirely,
		// or coming back up to the block itself after editing inside it (a
		// click on the block, Escape, or the breadcrumb all land here).
		useEffect( () => {
			// Selection left the block entirely — re-arm the guard.
			if ( ! isActive ) {
				if ( ! sealed ) {
					setSealed( true );
				}

				enteredInner.current = false;
				return;
			}

			// An inner block is selected. Reaching one is always deliberate —
			// either by unsealing first, or straight from List View, which the
			// guard never blocks — so honor it and make sure we are unsealed.
			if ( isInner ) {
				if ( sealed ) {
					setSealed( false );
				}

				enteredInner.current = true;
				return;
			}

			// Back on the block itself after having been inside it: re-arm.
			if ( isSelf && ! sealed && enteredInner.current ) {
				setSealed( true );
				enteredInner.current = false;
			}
		}, [ sealed, isActive, isInner, isSelf ] );

		// Publish the sealed state so descendants can react to it (the venue map
		// drops its resize handles while its parent venue is sealed), and clear
		// the entry when the block goes away.
		useEffect( () => {
			publishSealedState( clientId, sealed );
		}, [ clientId, sealed ] );

		// The guarded state is conveyed only by a tint, which says nothing to
		// assistive technology. Describe the block while it is sealed, and
		// announce the moment it opens up. Only the unseal is announced —
		// re-sealing happens whenever focus moves away, and narrating that
		// every time would be noise.
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

		useEffect( () => {
			return () => {
				sealedStates.delete( clientId );
				sealedListeners.delete( clientId );
			};
		}, [ clientId ] );

		// Was the block already selected when this gesture began? By the time
		// `click` fires, core has selected the block and re-rendered, so
		// reading selection there reports true even on the very first click and
		// would let one click straight in. Capture it on mousedown instead.
		const wasSelectedOnMouseDown = useRef( false );
		const isActiveRef = useRef( isActive );

		useEffect( () => {
			isActiveRef.current = isActive;
		}, [ isActive ] );

		// The mousedown listener is attached natively in the capture phase
		// rather than passed through `wrapperProps`: core relies on its own
		// mousedown handling on this wrapper to select the block, and passing
		// `onMouseDown` in wrapperProps replaces it, which stops the block from
		// being selectable at all. Capture phase also means this runs before
		// core selects, so the ref still holds the pre-click state. It only
		// writes a ref — no re-render — so the drag gesture is untouched.
		useEffect( () => {
			const element = getCanvasDocument().getElementById(
				`block-${ clientId }`,
			);

			if ( ! element ) {
				return undefined;
			}

			const handleMouseDown = () => {
				wasSelectedOnMouseDown.current = isActiveRef.current;
			};

			element.addEventListener( 'mousedown', handleMouseDown, true );

			return () => {
				element.removeEventListener(
					'mousedown',
					handleMouseDown,
					true,
				);
			};
		}, [ clientId ] );

		// A click on a block that was *already* selected is the deliberate
		// "let me in" action, so the first click only selects and the second
		// unseals. Listening on `click` rather than `mousedown` keeps dragging
		// intact: a real drag ends in dragend and never fires click.
		const onClick = ( event ) => {
			if ( sealed && wasSelectedOnMouseDown.current ) {
				setSealed( false );
			}

			wrapperProps?.onClick?.( event );
		};

		// Keyboard equivalent: Enter or Space on the selected, sealed block.
		const onKeyDown = ( event ) => {
			if (
				sealed &&
				isActive &&
				( 'Enter' === event.key || ' ' === event.key )
			) {
				event.preventDefault();
				event.stopPropagation();
				setSealed( false );
				return;
			}

			wrapperProps?.onKeyDown?.( event );
		};

		const className = [ props.className, sealed && OVERLAY_CLASS ]
			.filter( Boolean )
			.join( ' ' );

		return (
			<BlockListBlock
				{ ...props }
				className={ className }
				wrapperProps={ {
					...wrapperProps,
					onClick,
					onKeyDown,
					'aria-describedby': sealed
						? [ wrapperProps?.[ 'aria-describedby' ], HINT_ID ]
							.filter( Boolean )
							.join( ' ' )
						: wrapperProps?.[ 'aria-describedby' ],
					// Only tint once the block is selected, so the guard reads
					// as "you've got this, it's protected" rather than shouting
					// for attention on every unselected block.
					style:
						sealed && isActive
							? { ...wrapperProps?.style, ...SEALED_STYLE }
							: wrapperProps?.style,
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
