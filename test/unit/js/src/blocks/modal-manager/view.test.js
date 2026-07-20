/**
 * External dependencies
 */
import { describe, expect, it, jest, beforeEach } from '@jest/globals';

/**
 * Mock the Interactivity API with a namespace-merging store so every
 * module contributing to the `gatherpress` namespace (modal-manager,
 * helpers) shares one registry, mirroring the real runtime.
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
	{ virtual: true }
);

/**
 * WordPress dependencies
 */
import { store } from '@wordpress/interactivity';

/**
 * Internal dependencies
 */
// Import the actual module so its actions register on the shared store.
import '@src/blocks/modal-manager/view';

/**
 * Regression coverage for #1719 — openModal must not throw when it is
 * called with neither an event nor an element (e.g. a `querySelector`
 * miss at the call site used to produce `openModal( null, null )` and
 * a "Cannot read properties of null (reading 'target')" TypeError).
 */
describe( 'modal-manager openModal', () => {
	let actions;

	beforeEach( () => {
		( { actions } = store( 'gatherpress' ) );
		document.body.innerHTML = '';
	} );

	it( 'bails without throwing when called with no event and no element (#1719)', () => {
		expect( () => actions.openModal( null, null ) ).not.toThrow();
	} );

	it( 'falls back to event.target when no element is given', () => {
		document.body.innerHTML = `
			<div class="wp-block-gatherpress-modal-manager">
				<button type="button">Open</button>
				<div class="wp-block-gatherpress-modal"></div>
			</div>
		`;

		const button = document.querySelector( 'button' );
		const event = { preventDefault: jest.fn(), target: button };

		actions.openModal( event );

		expect( event.preventDefault ).toHaveBeenCalled();
		expect(
			document
				.querySelector( '.wp-block-gatherpress-modal' )
				.classList.contains( 'gatherpress--is-visible' )
		).toBe( true );
	} );

	it( 'uses the explicit element when one is given', () => {
		document.body.innerHTML = `
			<div class="wp-block-gatherpress-modal-manager">
				<button type="button">Open</button>
				<div class="wp-block-gatherpress-modal"></div>
			</div>
		`;

		const button = document.querySelector( 'button' );

		actions.openModal( null, button );

		expect(
			document
				.querySelector( '.wp-block-gatherpress-modal' )
				.classList.contains( 'gatherpress--is-visible' )
		).toBe( true );
	} );

	it( 'openModalOnEnter opens the modal on Enter or Space key', () => {
		document.body.innerHTML = `
			<div class="wp-block-gatherpress-modal-manager">
				<button type="button">Open</button>
				<div class="wp-block-gatherpress-modal"></div>
			</div>
		`;

		const button = document.querySelector( 'button' );
		const enterEvent = { key: 'Enter', preventDefault: jest.fn(), target: button };
		const spaceEvent = { key: ' ', preventDefault: jest.fn(), target: button };
		const tabEvent = { key: 'Tab', preventDefault: jest.fn(), target: button };

		// Other keys should not open the modal.
		actions.openModalOnEnter( tabEvent );
		expect( tabEvent.preventDefault ).not.toHaveBeenCalled();
		expect(
			document
				.querySelector( '.wp-block-gatherpress-modal' )
				.classList.contains( 'gatherpress--is-visible' )
		).toBe( false );

		// Enter should open the modal.
		actions.openModalOnEnter( enterEvent );
		expect( enterEvent.preventDefault ).toHaveBeenCalled();
		expect(
			document
				.querySelector( '.wp-block-gatherpress-modal' )
				.classList.contains( 'gatherpress--is-visible' )
		).toBe( true );

		// Reset visibility.
		document.querySelector( '.wp-block-gatherpress-modal' ).classList.remove( 'gatherpress--is-visible' );

		// Space should open the modal.
		actions.openModalOnEnter( spaceEvent );
		expect( spaceEvent.preventDefault ).toHaveBeenCalled();
		expect(
			document
				.querySelector( '.wp-block-gatherpress-modal' )
				.classList.contains( 'gatherpress--is-visible' )
		).toBe( true );
	} );

	it( 'closeModalOnEnter closes the modal on Enter or Space key', () => {
		document.body.innerHTML = `
			<div class="wp-block-gatherpress-modal-manager">
				<button type="button">Close</button>
				<div class="wp-block-gatherpress-modal gatherpress--is-visible"></div>
			</div>
		`;

		const button = document.querySelector( 'button' );
		const enterEvent = { key: 'Enter', preventDefault: jest.fn(), target: button };
		const spaceEvent = { key: ' ', preventDefault: jest.fn(), target: button };
		const tabEvent = { key: 'Tab', preventDefault: jest.fn(), target: button };

		// Other keys should not close the modal.
		actions.closeModalOnEnter( tabEvent );
		expect( tabEvent.preventDefault ).not.toHaveBeenCalled();
		expect(
			document
				.querySelector( '.wp-block-gatherpress-modal' )
				.classList.contains( 'gatherpress--is-visible' )
		).toBe( true );

		// Enter should close the modal.
		actions.closeModalOnEnter( enterEvent );
		expect( enterEvent.preventDefault ).toHaveBeenCalled();
		expect(
			document
				.querySelector( '.wp-block-gatherpress-modal' )
				.classList.contains( 'gatherpress--is-visible' )
		).toBe( false );

		// Reset visibility.
		document.querySelector( '.wp-block-gatherpress-modal' ).classList.add( 'gatherpress--is-visible' );

		// Space should close the modal.
		actions.closeModalOnEnter( spaceEvent );
		expect( spaceEvent.preventDefault ).toHaveBeenCalled();
		expect(
			document
				.querySelector( '.wp-block-gatherpress-modal' )
				.classList.contains( 'gatherpress--is-visible' )
		).toBe( false );
	} );
} );
