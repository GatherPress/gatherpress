/**
 * External dependencies
 */
import {
	describe,
	expect,
	it,
	jest,
	beforeEach,
	afterEach,
} from '@jest/globals';

/**
 * Internal dependencies
 */
import {
	initTooltip,
	initTooltips,
	closeAllTooltips,
	handleDocumentClick,
	handleDocumentKeyDown,
	handleFocusOut,
	handleMouseLeave,
	handleLazyInit,
	bindTooltipEvents,
} from '@src/formats/tooltip/view';

describe( 'Tooltip view', () => {
	let originalReadyState;
	let originalQuerySelectorAll;
	let originalAddEventListener;
	let originalMutationObserver;

	beforeEach( () => {
		jest.clearAllMocks();

		originalReadyState = Object.getOwnPropertyDescriptor(
			document,
			'readyState'
		);
		originalQuerySelectorAll = document.querySelectorAll;
		originalAddEventListener = document.addEventListener;
		originalMutationObserver = global.MutationObserver;

		document.body.innerHTML = '';
	} );

	afterEach( () => {
		if ( originalReadyState ) {
			Object.defineProperty(
				document,
				'readyState',
				originalReadyState
			);
		}
		document.querySelectorAll = originalQuerySelectorAll;
		document.addEventListener = originalAddEventListener;
		global.MutationObserver = originalMutationObserver;
		document.body.innerHTML = '';
	} );

	describe( 'initTooltip function', () => {
		it( 'returns early when tooltip is null or undefined', () => {
			expect( () => initTooltip( null ) ).not.toThrow();
			expect( () => initTooltip( undefined ) ).not.toThrow();
		} );

		it( 'returns early when data-gatherpress-tooltip attribute is missing', () => {
			const el = document.createElement( 'span' );
			initTooltip( el );
			expect( el.hasAttribute( 'tabindex' ) ).toBe( false );
		} );

		it( 'sets tabindex="0" if not present', () => {
			const el = document.createElement( 'span' );
			el.setAttribute( 'data-gatherpress-tooltip', 'Help info' );
			initTooltip( el );

			expect( el.getAttribute( 'tabindex' ) ).toBe( '0' );
		} );

		it( 'preserves existing tabindex attribute', () => {
			const el = document.createElement( 'span' );
			el.setAttribute( 'data-gatherpress-tooltip', 'Help info' );
			el.setAttribute( 'tabindex', '1' );
			initTooltip( el );

			expect( el.getAttribute( 'tabindex' ) ).toBe( '1' );
		} );

		it( 'sets text color CSS property when data attribute exists', () => {
			const el = document.createElement( 'span' );
			el.setAttribute( 'data-gatherpress-tooltip', 'Help info' );
			el.dataset.gatherpressTooltipTextColor = '#ff0000';
			initTooltip( el );

			expect(
				el.style.getPropertyValue( '--gatherpress-tooltip-text-color' )
			).toBe( '#ff0000' );
		} );

		it( 'sets background color CSS property when data attribute exists', () => {
			const el = document.createElement( 'span' );
			el.setAttribute( 'data-gatherpress-tooltip', 'Help info' );
			el.dataset.gatherpressTooltipBgColor = '#00ff00';
			initTooltip( el );

			expect(
				el.style.getPropertyValue( '--gatherpress-tooltip-bg-color' )
			).toBe( '#00ff00' );
		} );

		it( 'injects .screen-reader-text span with tooltip text', () => {
			const el = document.createElement( 'span' );
			el.setAttribute( 'data-gatherpress-tooltip', 'Accessible note' );
			initTooltip( el );

			const srSpan = el.querySelector( '.screen-reader-text' );
			expect( srSpan ).not.toBeNull();
			expect( srSpan.textContent ).toBe( ' (Accessible note)' );
			expect( srSpan.className ).toBe(
				'screen-reader-text gatherpress-tooltip-notice'
			);
		} );

		it( 'does not inject duplicate .screen-reader-text span if already present', () => {
			const el = document.createElement( 'span' );
			el.setAttribute( 'data-gatherpress-tooltip', 'Accessible note' );
			const existing = document.createElement( 'span' );
			existing.className =
				'screen-reader-text gatherpress-tooltip-notice';
			existing.textContent = ' (Accessible note)';
			el.appendChild( existing );

			initTooltip( el );

			expect( el.querySelectorAll( '.screen-reader-text' ).length ).toBe( 1 );
		} );

		it( 'does not borrow a nested tooltip\'s text', () => {
			const el = document.createElement( 'span' );
			el.className = 'gatherpress-tooltip';
			el.setAttribute( 'data-gatherpress-tooltip', 'Outer note' );

			const inner = document.createElement( 'span' );
			inner.className = 'gatherpress-tooltip';
			inner.setAttribute( 'data-gatherpress-tooltip', 'Inner note' );
			const innerSr = document.createElement( 'span' );
			innerSr.className = 'screen-reader-text gatherpress-tooltip-notice';
			innerSr.textContent = ' (Inner note)';
			inner.appendChild( innerSr );
			el.appendChild( inner );

			initTooltip( el );

			const own = el.querySelector( ':scope > .gatherpress-tooltip-notice' );
			expect( own ).not.toBeNull();
			expect( own.textContent ).toBe( ' (Outer note)' );
		} );

		it( 'still speaks when another feature left screen-reader text behind', () => {
			const el = document.createElement( 'span' );
			el.setAttribute( 'data-gatherpress-tooltip', 'Accessible note' );

			// Not ours. Without a marker class this would read as an existing
			// tooltip and the note would never be announced.
			const other = document.createElement( 'span' );
			other.className = 'screen-reader-text gatherpress-new-tab-notice';
			other.textContent = '(opens in a new tab)';
			el.appendChild( other );

			initTooltip( el );

			const srSpan = el.querySelector( '.gatherpress-tooltip-notice' );
			expect( srSpan ).not.toBeNull();
			expect( srSpan.textContent ).toBe( ' (Accessible note)' );
		} );
	} );

	describe( 'initTooltips function', () => {
		it( 'queries and initializes all matching tooltip elements', () => {
			const el1 = document.createElement( 'span' );
			el1.className = 'gatherpress-tooltip';
			el1.setAttribute( 'data-gatherpress-tooltip', 'First' );
			el1.dataset.gatherpressTooltipTextColor = '#111';

			const el2 = document.createElement( 'span' );
			el2.className = 'gatherpress-tooltip';
			el2.setAttribute( 'data-gatherpress-tooltip', 'Second' );
			el2.dataset.gatherpressTooltipBgColor = '#222';

			document.body.appendChild( el1 );
			document.body.appendChild( el2 );

			initTooltips();

			expect( el1.getAttribute( 'tabindex' ) ).toBe( '0' );
			expect(
				el1.style.getPropertyValue( '--gatherpress-tooltip-text-color' )
			).toBe( '#111' );
			expect( el2.getAttribute( 'tabindex' ) ).toBe( '0' );
			expect(
				el2.style.getPropertyValue( '--gatherpress-tooltip-bg-color' )
			).toBe( '#222' );
		} );

		it( 'handles environment where querySelectorAll is not a function', () => {
			document.querySelectorAll = null;
			expect( () => initTooltips() ).not.toThrow();
		} );
	} );

	describe( 'closeAllTooltips function', () => {
		it( 'removes gatherpress-tooltip--is-active from all active tooltips', () => {
			const el1 = document.createElement( 'span' );
			el1.className = 'gatherpress-tooltip gatherpress-tooltip--is-active';
			const el2 = document.createElement( 'span' );
			el2.className = 'gatherpress-tooltip gatherpress-tooltip--is-active';

			document.body.appendChild( el1 );
			document.body.appendChild( el2 );

			closeAllTooltips();

			expect(
				el1.classList.contains( 'gatherpress-tooltip--is-active' )
			).toBe( false );
			expect(
				el2.classList.contains( 'gatherpress-tooltip--is-active' )
			).toBe( false );
		} );

		it( 'handles environment where querySelectorAll is not a function', () => {
			document.querySelectorAll = null;
			expect( () => closeAllTooltips() ).not.toThrow();
		} );
	} );

	describe( 'handleDocumentClick function', () => {
		it( 'toggles active class on clicked tooltip and initializes it', () => {
			const el = document.createElement( 'span' );
			el.className = 'gatherpress-tooltip';
			el.setAttribute( 'data-gatherpress-tooltip', 'Click info' );
			document.body.appendChild( el );

			handleDocumentClick( { target: el } );
			expect(
				el.classList.contains( 'gatherpress-tooltip--is-active' )
			).toBe( true );
			expect( el.getAttribute( 'tabindex' ) ).toBe( '0' );

			handleDocumentClick( { target: el } );
			expect(
				el.classList.contains( 'gatherpress-tooltip--is-active' )
			).toBe( false );
		} );

		it( 'closes other active tooltips when a different tooltip is clicked', () => {
			const el1 = document.createElement( 'span' );
			el1.className = 'gatherpress-tooltip gatherpress-tooltip--is-active';
			el1.setAttribute( 'data-gatherpress-tooltip', 'First' );

			const el2 = document.createElement( 'span' );
			el2.className = 'gatherpress-tooltip';
			el2.setAttribute( 'data-gatherpress-tooltip', 'Second' );

			document.body.appendChild( el1 );
			document.body.appendChild( el2 );

			handleDocumentClick( { target: el2 } );

			expect(
				el1.classList.contains( 'gatherpress-tooltip--is-active' )
			).toBe( false );
			expect(
				el2.classList.contains( 'gatherpress-tooltip--is-active' )
			).toBe( true );
		} );

		it( 'closes all active tooltips when clicking outside', () => {
			const el = document.createElement( 'span' );
			el.className = 'gatherpress-tooltip gatherpress-tooltip--is-active';
			el.setAttribute( 'data-gatherpress-tooltip', 'First' );
			const outside = document.createElement( 'div' );

			document.body.appendChild( el );
			document.body.appendChild( outside );

			handleDocumentClick( { target: outside } );

			expect(
				el.classList.contains( 'gatherpress-tooltip--is-active' )
			).toBe( false );
		} );
	} );

	describe( 'handleDocumentKeyDown function', () => {
		it( 'closes active tooltips when Escape key is pressed', () => {
			const el = document.createElement( 'span' );
			el.className = 'gatherpress-tooltip gatherpress-tooltip--is-active';
			document.body.appendChild( el );

			handleDocumentKeyDown( { key: 'Escape' } );

			expect(
				el.classList.contains( 'gatherpress-tooltip--is-active' )
			).toBe( false );
		} );

		it( 'marks focused tooltip as dismissed when Escape key is pressed', () => {
			const el = document.createElement( 'span' );
			el.className = 'gatherpress-tooltip gatherpress-tooltip--is-active';
			el.setAttribute( 'data-gatherpress-tooltip', 'Dismiss me' );
			el.setAttribute( 'tabindex', '0' );
			document.body.appendChild( el );
			el.focus();

			handleDocumentKeyDown( { key: 'Escape' } );

			expect(
				el.classList.contains( 'gatherpress-tooltip--is-dismissed' )
			).toBe( true );
			expect(
				el.classList.contains( 'gatherpress-tooltip--is-active' )
			).toBe( false );
		} );

		it( 'does nothing when other keys are pressed', () => {
			const el = document.createElement( 'span' );
			el.className = 'gatherpress-tooltip gatherpress-tooltip--is-active';
			document.body.appendChild( el );

			handleDocumentKeyDown( { key: 'Enter' } );

			expect(
				el.classList.contains( 'gatherpress-tooltip--is-active' )
			).toBe( true );
		} );
	} );

	describe( 'handleFocusOut function', () => {
		it( 'removes dismissed and active classes when focus leaves tooltip', () => {
			const el = document.createElement( 'span' );
			el.className =
				'gatherpress-tooltip gatherpress-tooltip--is-dismissed gatherpress-tooltip--is-active';
			el.setAttribute( 'data-gatherpress-tooltip', 'Focus out test' );
			document.body.appendChild( el );

			handleFocusOut( { target: el } );

			expect(
				el.classList.contains( 'gatherpress-tooltip--is-dismissed' )
			).toBe( false );
			expect(
				el.classList.contains( 'gatherpress-tooltip--is-active' )
			).toBe( false );
		} );

		it( 'does nothing when event target is not inside a tooltip', () => {
			const outside = document.createElement( 'div' );
			document.body.appendChild( outside );

			expect( () => handleFocusOut( { target: outside } ) ).not.toThrow();
		} );
	} );

	describe( 'handleMouseLeave function', () => {
		it( 'removes dismissed class when pointer leaves and tooltip is not activeElement', () => {
			const el = document.createElement( 'span' );
			el.className =
				'gatherpress-tooltip gatherpress-tooltip--is-dismissed';
			el.setAttribute( 'data-gatherpress-tooltip', 'Mouse leave test' );
			document.body.appendChild( el );

			handleMouseLeave( { target: el } );

			expect(
				el.classList.contains( 'gatherpress-tooltip--is-dismissed' )
			).toBe( false );
		} );

		it( 'keeps dismissed class if tooltip is still focused when mouse leaves', () => {
			const el = document.createElement( 'span' );
			el.className =
				'gatherpress-tooltip gatherpress-tooltip--is-dismissed';
			el.setAttribute( 'data-gatherpress-tooltip', 'Focused leave' );
			el.setAttribute( 'tabindex', '0' );
			document.body.appendChild( el );
			el.focus();

			handleMouseLeave( { target: el } );

			expect(
				el.classList.contains( 'gatherpress-tooltip--is-dismissed' )
			).toBe( true );
		} );

		it( 'does nothing when event target is not inside a tooltip', () => {
			const outside = document.createElement( 'div' );
			document.body.appendChild( outside );

			expect( () => handleMouseLeave( { target: outside } ) ).not.toThrow();
		} );
	} );

	describe( 'handleLazyInit function', () => {
		it( 'lazily initializes tooltip on focus or mouseenter', () => {
			const el = document.createElement( 'span' );
			el.className = 'gatherpress-tooltip';
			el.setAttribute( 'data-gatherpress-tooltip', 'Lazy note' );
			document.body.appendChild( el );

			handleLazyInit( { target: el } );

			expect( el.getAttribute( 'tabindex' ) ).toBe( '0' );
			expect( el.querySelector( '.screen-reader-text' ) ).not.toBeNull();
		} );

		it( 'does nothing when event target is not inside a tooltip', () => {
			const outside = document.createElement( 'div' );
			document.body.appendChild( outside );

			expect( () => handleLazyInit( { target: outside } ) ).not.toThrow();
		} );
	} );

	describe( 'bindTooltipEvents function', () => {
		it( 'binds event listeners to document and initializes MutationObserver', () => {
			let observerCallback;
			const mockObserve = jest.fn();
			global.MutationObserver = jest.fn( ( callback ) => {
				observerCallback = callback;
				return { observe: mockObserve };
			} );

			const mockAddEventListener = jest.fn();
			document.addEventListener = mockAddEventListener;

			bindTooltipEvents();

			expect( mockAddEventListener ).toHaveBeenCalledWith(
				'click',
				expect.any( Function )
			);
			expect( mockAddEventListener ).toHaveBeenCalledWith(
				'keydown',
				expect.any( Function )
			);
			expect( mockAddEventListener ).toHaveBeenCalledWith(
				'focusin',
				expect.any( Function ),
				true
			);
			expect( mockAddEventListener ).toHaveBeenCalledWith(
				'mouseenter',
				expect.any( Function ),
				true
			);
			expect( mockAddEventListener ).toHaveBeenCalledWith(
				'focusout',
				expect.any( Function ),
				true
			);
			expect( mockAddEventListener ).toHaveBeenCalledWith(
				'mouseleave',
				expect.any( Function ),
				true
			);

			expect( mockObserve ).toHaveBeenCalledWith( document.body, {
				childList: true,
				subtree: true,
			} );

			// Trigger observer callback with mutations.
			const el = document.createElement( 'span' );
			el.className = 'gatherpress-tooltip';
			el.setAttribute( 'data-gatherpress-tooltip', 'Observed' );
			document.body.appendChild( el );

			observerCallback( [ { addedNodes: [ el ] } ] );
			expect( el.getAttribute( 'tabindex' ) ).toBe( '0' );

			// Trigger observer callback without addedNodes.
			observerCallback( [ { addedNodes: [] } ] );
		} );

		it( 'handles environment where addEventListener is not a function', () => {
			document.addEventListener = null;
			expect( () => bindTooltipEvents() ).not.toThrow();
		} );
	} );

	describe( 'initialization timing', () => {
		it( 'calls initTooltips immediately when document is complete', async () => {
			jest.resetModules();
			const mockQuerySelectorAll = jest.fn( () => [] );
			document.querySelectorAll = mockQuerySelectorAll;

			Object.defineProperty( document, 'readyState', {
				value: 'complete',
				configurable: true,
			} );

			await import( '@src/formats/tooltip/view' );

			expect( mockQuerySelectorAll ).toHaveBeenCalledWith(
				'.gatherpress-tooltip[data-gatherpress-tooltip]'
			);
		} );

		it( 'adds DOMContentLoaded listener when document is loading and executes callback', async () => {
			jest.resetModules();
			let domLoadedCallback;
			const mockAddEventListener = jest.fn( ( event, cb ) => {
				if ( 'DOMContentLoaded' === event ) {
					domLoadedCallback = cb;
				}
			} );
			document.addEventListener = mockAddEventListener;
			document.querySelectorAll = jest.fn( () => [] );

			Object.defineProperty( document, 'readyState', {
				value: 'loading',
				configurable: true,
			} );

			await import( '@src/formats/tooltip/view' );

			expect( mockAddEventListener ).toHaveBeenCalledWith(
				'DOMContentLoaded',
				expect.any( Function )
			);

			// Call the DOMContentLoaded callback to cover line 193-194.
			domLoadedCallback();
		} );
	} );
} );
