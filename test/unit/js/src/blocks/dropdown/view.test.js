/**
 * External dependencies
 */
import { afterEach, beforeEach, describe, expect, it, jest } from '@jest/globals';

/**
 * Mock the Interactivity API with a namespace-merging store so every
 * module contributing to the `gatherpress` namespace shares one registry,
 * mirroring the real runtime.
 */
jest.mock(
	'@wordpress/interactivity',
	() => {
		const registries = {};

		return {
			store: ( name, config = {} ) => {
				if ( ! registries[ name ] ) {
					registries[ name ] = {
						state: {},
						actions: {},
						callbacks: {},
					};
				}

				const registry = registries[ name ];

				Object.assign( registry.state, config.state );
				Object.assign( registry.actions, config.actions );
				Object.assign( registry.callbacks, config.callbacks );

				return registry;
			},
			getElement: jest.fn(),
			getContext: jest.fn(),
		};
	},
	{ virtual: true },
);

/**
 * WordPress dependencies
 */
import { store, getElement } from '@wordpress/interactivity';

/**
 * Internal dependencies
 */
import '@src/blocks/dropdown/view';

/**
 * Builds a click-mode dropdown between two outside links.
 *
 * @return {Object} The trigger, the panel, the item links and the outside links.
 */
function setupDom() {
	document.body.innerHTML = `
		<a href="#before" id="before">Before</a>
		<div class="wp-block-gatherpress-dropdown">
			<a href="#" class="wp-block-gatherpress-dropdown__trigger" role="button" aria-controls="dd" aria-expanded="false" tabindex="0">Add to calendar</a>
			<div class="wp-block-gatherpress-dropdown__menu" id="dd">
				<div class="wp-block-gatherpress-dropdown-item"><a href="#google">Google Calendar</a></div>
				<div class="wp-block-gatherpress-dropdown-item"><a href="#ical">iCal</a></div>
			</div>
		</div>
		<a href="#after" id="after">After</a>
	`;

	const trigger = document.querySelector( '.wp-block-gatherpress-dropdown__trigger' );

	getElement.mockReturnValue( { ref: trigger } );

	return {
		trigger,
		menu: document.querySelector( '.wp-block-gatherpress-dropdown__menu' ),
		items: [ ...document.querySelectorAll( '.wp-block-gatherpress-dropdown-item a' ) ],
		before: document.getElementById( 'before' ),
		after: document.getElementById( 'after' ),
	};
}

/**
 * Moves focus the way the browser does, firing focusout with a related target.
 *
 * @param {HTMLElement} from The element losing focus.
 * @param {HTMLElement} to   The element gaining focus.
 *
 * @return {void}
 */
function moveFocus( from, to ) {
	from.dispatchEvent(
		new FocusEvent( 'focusout', { bubbles: true, relatedTarget: to } ),
	);
	to.focus();
}

describe( 'dropdown toggleDropdown as a disclosure', () => {
	let actions;
	let offsetParent;

	beforeEach( () => {
		( { actions } = store( 'gatherpress' ) );

		// jsdom has no layout, so every element reports a null offsetParent
		// and looks hidden. Report a parent so visibility checks treat the
		// open panel's links as visible, like a browser.
		offsetParent = Object.getOwnPropertyDescriptor(
			HTMLElement.prototype,
			'offsetParent',
		);
		Object.defineProperty( HTMLElement.prototype, 'offsetParent', {
			configurable: true,
			get() {
				return this.parentNode;
			},
		} );
	} );

	afterEach( () => {
		// Close any open panel so its document listeners go away.
		const trigger = document.querySelector(
			'.wp-block-gatherpress-dropdown__trigger',
		);

		if ( trigger?.cleanupCloseHandlers ) {
			trigger.cleanupCloseHandlers();
		}

		if ( trigger?.cleanupFocusTrap ) {
			trigger.cleanupFocusTrap();
		}

		Object.defineProperty( HTMLElement.prototype, 'offsetParent', offsetParent );
	} );

	it( 'opens the panel and keeps focus on the trigger', () => {
		const { trigger, menu } = setupDom();

		actions.toggleDropdown( new Event( 'click' ) );

		expect( menu.classList.contains( 'gatherpress--is-visible' ) ).toBe( true );
		expect( trigger.getAttribute( 'aria-expanded' ) ).toBe( 'true' );
		expect( trigger.ownerDocument.activeElement ).toBe( trigger );
	} );

	it( 'lets Tab move on from the last item instead of wrapping to the trigger', () => {
		const { items } = setupDom();

		actions.toggleDropdown();
		items[ 1 ].focus();

		const tab = new KeyboardEvent( 'keydown', {
			key: 'Tab',
			bubbles: true,
			cancelable: true,
		} );

		document.dispatchEvent( tab );

		expect( tab.defaultPrevented ).toBe( false );
		expect( document.activeElement ).toBe( items[ 1 ] );
	} );

	it( 'lets Shift+Tab move back from the trigger', () => {
		const { trigger } = setupDom();

		actions.toggleDropdown();

		const shiftTab = new KeyboardEvent( 'keydown', {
			key: 'Tab',
			shiftKey: true,
			bubbles: true,
			cancelable: true,
		} );

		document.dispatchEvent( shiftTab );

		expect( shiftTab.defaultPrevented ).toBe( false );
		expect( document.activeElement ).toBe( trigger );
	} );

	it( 'closes the panel when focus leaves the dropdown, and leaves focus where it went', () => {
		const { trigger, menu, items, after } = setupDom();

		actions.toggleDropdown();
		moveFocus( trigger, items[ 0 ] );
		moveFocus( items[ 0 ], items[ 1 ] );

		expect( menu.classList.contains( 'gatherpress--is-visible' ) ).toBe( true );

		moveFocus( items[ 1 ], after );

		expect( menu.classList.contains( 'gatherpress--is-visible' ) ).toBe( false );
		expect( trigger.getAttribute( 'aria-expanded' ) ).toBe( 'false' );
		expect( document.activeElement ).toBe( after );
	} );

	it( 'closes the panel when Shift+Tab leaves from the trigger', () => {
		const { trigger, menu, before } = setupDom();

		actions.toggleDropdown();
		moveFocus( trigger, before );

		expect( menu.classList.contains( 'gatherpress--is-visible' ) ).toBe( false );
		expect( document.activeElement ).toBe( before );
	} );

	it( 'closes without moving focus when Tab leaves the page', () => {
		jest.useFakeTimers();

		const { trigger, menu, items } = setupDom();

		actions.toggleDropdown();
		moveFocus( trigger, items[ 0 ] );

		// Tab past the last element of the page: no related target, and
		// focus goes to the browser, so nothing in the page has it.
		items[ 0 ].dispatchEvent(
			new FocusEvent( 'focusout', { bubbles: true, relatedTarget: null } ),
		);
		items[ 0 ].blur();
		jest.runOnlyPendingTimers();

		expect( menu.classList.contains( 'gatherpress--is-visible' ) ).toBe( false );
		expect( trigger.getAttribute( 'aria-expanded' ) ).toBe( 'false' );
		expect( document.activeElement ).not.toBe( trigger );

		jest.useRealTimers();
	} );

	it( 'leaves a pointer press on a non-focusable spot to the outside-click handler', () => {
		jest.useFakeTimers();

		const { trigger, menu, items } = setupDom();

		actions.toggleDropdown();
		moveFocus( trigger, items[ 0 ] );

		document.body.dispatchEvent( new Event( 'pointerdown', { bubbles: true } ) );
		items[ 0 ].dispatchEvent(
			new FocusEvent( 'focusout', { bubbles: true, relatedTarget: null } ),
		);
		items[ 0 ].blur();
		jest.runOnlyPendingTimers();

		expect( menu.classList.contains( 'gatherpress--is-visible' ) ).toBe( true );

		document.body.click();

		expect( menu.classList.contains( 'gatherpress--is-visible' ) ).toBe( false );
		expect( document.activeElement ).toBe( trigger );

		jest.useRealTimers();
	} );

	it( 'keeps focus on a focusable element clicked outside the dropdown', () => {
		const { trigger, menu, items, after } = setupDom();

		actions.toggleDropdown();
		moveFocus( trigger, items[ 0 ] );

		// A mouse press moves focus before the click event.
		document.body.dispatchEvent( new Event( 'pointerdown', { bubbles: true } ) );
		moveFocus( items[ 0 ], after );
		after.click();

		expect( menu.classList.contains( 'gatherpress--is-visible' ) ).toBe( false );
		expect( document.activeElement ).toBe( after );
	} );

	it( 'stays open when focus goes nowhere, such as the window losing focus', () => {
		const { trigger, menu } = setupDom();

		actions.toggleDropdown();
		trigger.dispatchEvent(
			new FocusEvent( 'focusout', { bubbles: true, relatedTarget: null } ),
		);

		expect( menu.classList.contains( 'gatherpress--is-visible' ) ).toBe( true );
	} );

	it( 'closes on Escape from an item and returns focus to the trigger', () => {
		const { trigger, menu, items } = setupDom();

		actions.toggleDropdown();
		items[ 1 ].focus();
		items[ 1 ].dispatchEvent(
			new KeyboardEvent( 'keydown', { key: 'Escape', bubbles: true, cancelable: true } ),
		);

		expect( menu.classList.contains( 'gatherpress--is-visible' ) ).toBe( false );
		expect( trigger.getAttribute( 'aria-expanded' ) ).toBe( 'false' );
		expect( document.activeElement ).toBe( trigger );
	} );

	it( 'closes on a second click and returns focus to the trigger', () => {
		const { trigger, menu, items } = setupDom();

		actions.toggleDropdown();
		items[ 0 ].focus();
		actions.toggleDropdown();

		expect( menu.classList.contains( 'gatherpress--is-visible' ) ).toBe( false );
		expect( document.activeElement ).toBe( trigger );
	} );

	it( 'drops the focus-out handler of an earlier opening', () => {
		const { trigger, menu, after } = setupDom();

		actions.toggleDropdown();
		actions.toggleDropdown();
		actions.toggleDropdown();

		const removed = jest.spyOn( trigger.parentElement, 'removeEventListener' );

		moveFocus( trigger, after );

		expect( menu.classList.contains( 'gatherpress--is-visible' ) ).toBe( false );
		expect( removed ).toHaveBeenCalledTimes( 1 );
	} );

	it( 'does nothing without a panel', () => {
		const { trigger } = setupDom();

		trigger.parentElement
			.querySelector( '.wp-block-gatherpress-dropdown__menu' )
			.remove();

		actions.toggleDropdown();

		expect( trigger.getAttribute( 'aria-expanded' ) ).toBe( 'false' );
	} );
} );

describe( 'dropdown hover dismissal', () => {
	let actions;

	beforeEach( () => {
		( { actions } = store( 'gatherpress' ) );
	} );

	afterEach( () => {
		document
			.querySelector( '.wp-block-gatherpress-dropdown__trigger' )
			?.cleanupCloseHandlers?.();
	} );

	/**
	 * Reports whether the pointer is over the dropdown.
	 *
	 * @since TBD
	 *
	 * @param {HTMLElement} wrapper The dropdown block.
	 * @param {boolean}     hovered Whether the pointer is over it.
	 *
	 * @return {void}
	 */
	function hover( wrapper, hovered ) {
		jest.spyOn( wrapper, 'matches' ).mockImplementation(
			( selector ) => ':hover' === selector && hovered,
		);
	}

	it( 'hides a hovered panel after a second click until the pointer leaves', () => {
		const { trigger } = setupDom();
		const wrapper = trigger.parentElement;

		hover( wrapper, true );
		actions.toggleDropdown();
		actions.toggleDropdown();

		expect( wrapper.classList.contains( 'gatherpress--hover-dismissed' ) ).toBe( true );

		wrapper.dispatchEvent( new MouseEvent( 'mouseleave' ) );

		expect( wrapper.classList.contains( 'gatherpress--hover-dismissed' ) ).toBe( false );
	} );

	it( 'leaves hover alone when the panel closes without the pointer over it', () => {
		const { trigger } = setupDom();
		const wrapper = trigger.parentElement;

		hover( wrapper, false );
		actions.toggleDropdown();
		actions.toggleDropdown();

		expect( wrapper.classList.contains( 'gatherpress--hover-dismissed' ) ).toBe( false );
	} );

	it( 'hides a panel shown only by hover on Escape', () => {
		const { trigger } = setupDom();
		const wrapper = trigger.parentElement;

		hover( wrapper, true );
		actions.dismissHoverOnEscape( new KeyboardEvent( 'keydown', { key: 'Enter' } ) );

		expect( wrapper.classList.contains( 'gatherpress--hover-dismissed' ) ).toBe( false );

		actions.dismissHoverOnEscape( new KeyboardEvent( 'keydown', { key: 'Escape' } ) );

		expect( wrapper.classList.contains( 'gatherpress--hover-dismissed' ) ).toBe( true );
	} );

	it( 'marks a hovered panel expanded, and collapsed again when the pointer leaves', () => {
		const { trigger } = setupDom();
		const wrapper = trigger.parentElement;

		getElement.mockReturnValue( { ref: wrapper } );
		actions.syncHoverExpanded( new MouseEvent( 'mouseenter' ) );

		expect( trigger.getAttribute( 'aria-expanded' ) ).toBe( 'true' );

		actions.syncHoverExpanded( new MouseEvent( 'mouseleave' ) );

		expect( trigger.getAttribute( 'aria-expanded' ) ).toBe( 'false' );
	} );

	it( 'keeps a panel opened by click expanded after the pointer leaves', () => {
		const { trigger } = setupDom();
		const wrapper = trigger.parentElement;

		hover( wrapper, true );
		actions.toggleDropdown();
		getElement.mockReturnValue( { ref: wrapper } );
		actions.syncHoverExpanded( new MouseEvent( 'mouseleave' ) );

		expect( trigger.getAttribute( 'aria-expanded' ) ).toBe( 'true' );
	} );

	it( 'keeps a dismissed panel collapsed while the pointer stays over it', () => {
		const { trigger } = setupDom();
		const wrapper = trigger.parentElement;

		hover( wrapper, true );
		actions.dismissHoverOnEscape( new KeyboardEvent( 'keydown', { key: 'Escape' } ) );

		expect( trigger.getAttribute( 'aria-expanded' ) ).toBe( 'false' );

		getElement.mockReturnValue( { ref: wrapper } );
		actions.syncHoverExpanded( new MouseEvent( 'mouseenter' ) );

		expect( trigger.getAttribute( 'aria-expanded' ) ).toBe( 'false' );
	} );

	it( 'leaves the expanded state alone on Escape when the pointer is elsewhere', () => {
		const { trigger } = setupDom();

		hover( trigger.parentElement, false );
		trigger.setAttribute( 'aria-expanded', 'true' );
		actions.dismissHoverOnEscape( new KeyboardEvent( 'keydown', { key: 'Escape' } ) );

		expect( trigger.getAttribute( 'aria-expanded' ) ).toBe( 'true' );
	} );

	it( 'does nothing on hover without a panel', () => {
		const { trigger, menu } = setupDom();

		menu.remove();
		getElement.mockReturnValue( { ref: trigger.parentElement } );
		actions.syncHoverExpanded( new MouseEvent( 'mouseenter' ) );

		expect( trigger.getAttribute( 'aria-expanded' ) ).toBe( 'false' );
	} );

	it( 'hides a hovered select-mode panel after an item is chosen', () => {
		document.body.innerHTML = `
			<div class="wp-block-gatherpress-dropdown" data-dropdown-mode="select">
				<a href="#" class="wp-block-gatherpress-dropdown__trigger" aria-expanded="true">Attending</a>
				<div class="wp-block-gatherpress-dropdown__menu gatherpress--is-visible">
					<div class="wp-block-gatherpress-dropdown-item"><a href="#" class="gatherpress--is-disabled">Attending</a></div>
					<div class="wp-block-gatherpress-dropdown-item"><a href="#">Waiting List</a></div>
				</div>
			</div>
		`;

		const wrapper = document.querySelector( '.wp-block-gatherpress-dropdown' );
		const trigger = wrapper.querySelector( '.wp-block-gatherpress-dropdown__trigger' );
		const item = wrapper.querySelectorAll( '.wp-block-gatherpress-dropdown-item a' )[ 1 ];

		hover( wrapper, true );
		getElement.mockReturnValue( { ref: item } );
		actions.linkHandler( new MouseEvent( 'click' ) );

		expect( trigger.getAttribute( 'aria-expanded' ) ).toBe( 'false' );
		expect( wrapper.classList.contains( 'gatherpress--hover-dismissed' ) ).toBe( true );
	} );

	it( 'waits for one pointer leave however often the panel is dismissed', () => {
		const { trigger } = setupDom();
		const wrapper = trigger.parentElement;
		const added = jest.spyOn( wrapper, 'addEventListener' );

		hover( wrapper, true );
		actions.dismissHoverOnEscape( new KeyboardEvent( 'keydown', { key: 'Escape' } ) );
		actions.dismissHoverOnEscape( new KeyboardEvent( 'keydown', { key: 'Escape' } ) );

		expect( added.mock.calls.filter( ( [ type ] ) => 'mouseleave' === type ) ).toHaveLength( 1 );
	} );
} );
