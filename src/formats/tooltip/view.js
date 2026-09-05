/**
 * GatherPress tooltip initialization and interaction handling.
 *
 * Sets CSS custom properties for tooltip colors, ensures keyboard focusability,
 * handles touch toggling, injects screen reader text, and persists across client-side navigation.
 *
 * @since 0.34.0
 * @since 0.36.0 Added keyboard focus, touch toggle, screen reader text, and client navigation support.
 */

/**
 * Marks the span holding the tooltip text for screen readers.
 *
 * @type {string}
 */
const NOTICE_CLASS = 'gatherpress-tooltip-notice';

/**
 * Initialize a single tooltip element.
 *
 * @param {HTMLElement|Object} tooltip The tooltip element.
 */
export function initTooltip( tooltip ) {
	if ( ! tooltip ) {
		return;
	}

	const tooltipText =
		'function' === typeof tooltip.getAttribute
			? tooltip.getAttribute( 'data-gatherpress-tooltip' )
			: tooltip.dataset?.gatherpressTooltip;

	if ( ! tooltipText ) {
		return;
	}

	// Make focusable if not already set.
	if (
		'function' === typeof tooltip.hasAttribute &&
		'function' === typeof tooltip.setAttribute &&
		! tooltip.hasAttribute( 'tabindex' )
	) {
		tooltip.setAttribute( 'tabindex', '0' );
	}

	// Set color custom properties if specified.
	const textColor = tooltip.dataset?.gatherpressTooltipTextColor;
	const bgColor = tooltip.dataset?.gatherpressTooltipBgColor;

	if ( textColor && tooltip.style?.setProperty ) {
		tooltip.style.setProperty(
			'--gatherpress-tooltip-text-color',
			textColor
		);
	}

	if ( bgColor && tooltip.style?.setProperty ) {
		tooltip.style.setProperty(
			'--gatherpress-tooltip-bg-color',
			bgColor
		);
	}

	// Ensure screen reader text element exists.
	if (
		'function' === typeof tooltip.querySelector &&
		'function' === typeof tooltip.appendChild &&
		'undefined' !== typeof document
	) {
		// Its own class so another feature's screen-reader text is never
		// mistaken for this one, and a direct child so a nested tooltip's
		// text is not either.
		const existingSrText = tooltip.querySelector(
			`:scope > .${ NOTICE_CLASS }`
		);
		if ( ! existingSrText ) {
			const srText = document.createElement( 'span' );
			srText.className = `screen-reader-text ${ NOTICE_CLASS }`;
			srText.textContent = ` (${ tooltipText })`;
			tooltip.appendChild( srText );
		}
	}
}

/**
 * Initialize all tooltips currently in the DOM.
 */
export function initTooltips() {
	if ( 'undefined' === typeof document || 'function' !== typeof document.querySelectorAll ) {
		return;
	}

	const tooltips = document.querySelectorAll(
		'.gatherpress-tooltip[data-gatherpress-tooltip]'
	);

	tooltips.forEach( ( tooltip ) => {
		initTooltip( tooltip );
	} );
}

/**
 * Close all active tooltips.
 */
export function closeAllTooltips() {
	if ( 'undefined' === typeof document || 'function' !== typeof document.querySelectorAll ) {
		return;
	}

	const activeTooltips = document.querySelectorAll(
		'.gatherpress-tooltip--is-active'
	);
	activeTooltips.forEach( ( el ) => {
		el.classList.remove( 'gatherpress-tooltip--is-active' );
	} );
}

/**
 * Handle document-level click to toggle active tooltip on touch/click or dismiss when clicking outside.
 *
 * @param {MouseEvent|TouchEvent} event The click or tap event.
 */
export function handleDocumentClick( event ) {
	const rawTarget = event?.target instanceof Node ? event.target : null;
	const target = rawTarget instanceof Element ? rawTarget : rawTarget?.parentElement;
	const tooltip = target?.closest
		? target.closest( '.gatherpress-tooltip[data-gatherpress-tooltip]' )
		: null;

	if ( tooltip ) {
		initTooltip( tooltip );
		const doc = tooltip.ownerDocument;
		const isCurrentlyActive =
			tooltip.classList.contains( 'gatherpress-tooltip--is-active' ) ||
			( doc?.activeElement === tooltip &&
				! tooltip.classList.contains( 'gatherpress-tooltip--is-dismissed' ) );

		closeAllTooltips();

		if ( ! isCurrentlyActive ) {
			tooltip.classList.remove( 'gatherpress-tooltip--is-dismissed' );
			tooltip.classList.add( 'gatherpress-tooltip--is-active' );
		} else {
			tooltip.classList.add( 'gatherpress-tooltip--is-dismissed' );
		}
	} else {
		closeAllTooltips();
	}
}

/**
 * Handle Escape key press to dismiss open tooltips.
 *
 * @param {KeyboardEvent} event The keydown event.
 */
export function handleDocumentKeyDown( event ) {
	if ( 'Escape' === event?.key ) {
		const doc =
			event?.target?.ownerDocument ||
			( 'undefined' !== typeof document ? document : null );
		const rawActive =
			doc?.activeElement instanceof Node
				? doc.activeElement
				: null;
		const activeEl = rawActive instanceof Element ? rawActive : rawActive?.parentElement;
		const tooltip = activeEl?.closest
			? activeEl.closest( '.gatherpress-tooltip[data-gatherpress-tooltip]' )
			: null;

		if ( tooltip ) {
			tooltip.classList.add( 'gatherpress-tooltip--is-dismissed' );
			tooltip.classList.remove( 'gatherpress-tooltip--is-active' );
		}
		closeAllTooltips();
	}
}

/**
 * Handle focusout to clear dismissed state once focus moves away.
 *
 * @param {FocusEvent} event The focusout event.
 */
export function handleFocusOut( event ) {
	const rawTarget = event?.target instanceof Node ? event.target : null;
	const target = rawTarget instanceof Element ? rawTarget : rawTarget?.parentElement;
	const tooltip = target?.closest
		? target.closest( '.gatherpress-tooltip[data-gatherpress-tooltip]' )
		: null;

	if ( tooltip ) {
		tooltip.classList.remove( 'gatherpress-tooltip--is-dismissed' );
		tooltip.classList.remove( 'gatherpress-tooltip--is-active' );
	}
}

/**
 * Handle mouseleave to clear dismissed state once pointer leaves, unless currently focused.
 *
 * @param {MouseEvent} event The mouseleave event.
 */
export function handleMouseLeave( event ) {
	const rawTarget = event?.target instanceof Node ? event.target : null;
	const target = rawTarget instanceof Element ? rawTarget : rawTarget?.parentElement;
	const tooltip = target?.closest
		? target.closest( '.gatherpress-tooltip[data-gatherpress-tooltip]' )
		: null;

	const doc = tooltip?.ownerDocument;
	if (
		tooltip &&
		( ! doc || doc.activeElement !== tooltip )
	) {
		tooltip.classList.remove( 'gatherpress-tooltip--is-dismissed' );
	}
}

/**
 * Handle focusin / mouseenter to lazily initialize dynamically inserted tooltips (client-side navigation).
 *
 * @param {FocusEvent|MouseEvent} event The event.
 */
export function handleLazyInit( event ) {
	const rawTarget = event?.target instanceof Node ? event.target : null;
	const target = rawTarget instanceof Element ? rawTarget : rawTarget?.parentElement;
	const tooltip = target?.closest
		? target.closest( '.gatherpress-tooltip[data-gatherpress-tooltip]' )
		: null;
	if ( tooltip ) {
		initTooltip( tooltip );
	}
}

/**
 * Bind global event listeners.
 */
export function bindTooltipEvents() {
	if ( 'undefined' === typeof document || 'function' !== typeof document.addEventListener ) {
		return;
	}

	document.addEventListener( 'click', handleDocumentClick );
	document.addEventListener( 'keydown', handleDocumentKeyDown );
	document.addEventListener( 'focusin', handleLazyInit, true );
	document.addEventListener( 'mouseenter', handleLazyInit, true );
	document.addEventListener( 'focusout', handleFocusOut, true );
	document.addEventListener( 'mouseleave', handleMouseLeave, true );

	if ( 'undefined' !== typeof MutationObserver && document.body ) {
		const observer = new MutationObserver( ( mutations ) => {
			let hasAddedNodes = false;
			for ( const mutation of mutations ) {
				if ( mutation.addedNodes && 0 < mutation.addedNodes.length ) {
					hasAddedNodes = true;
					break;
				}
			}
			if ( hasAddedNodes ) {
				initTooltips();
			}
		} );
		observer.observe( document.body, { childList: true, subtree: true } );
	}
}

// Initialize tooltips on DOMContentLoaded or immediately if already loaded.
if ( 'undefined' !== typeof document ) {
	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', () => {
			initTooltips();
			bindTooltipEvents();
		} );
	} else {
		initTooltips();
		bindTooltipEvents();
	}
}
