/**
 * External dependencies
 */
import { beforeEach, describe, expect, it, jest } from '@jest/globals';

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
	{ virtual: true }
);

/**
 * WordPress dependencies
 */
import { store, getElement, getContext } from '@wordpress/interactivity';

/**
 * Internal dependencies
 */
import '@src/blocks/rsvp-response/view';

/**
 * Regression coverage for #2102. When the RSVP response filter collapses to a
 * single selectable option the trigger is disabled, but the disabled state was
 * never exposed to assistive technology. The trigger keeps `role="button"` and
 * `aria-expanded`, so without `aria-disabled` a screen reader announces an
 * operable collapsed button that cannot be operated.
 */
describe( 'rsvp-response processRsvpDropdown trigger disabled state', () => {
	let state;
	let callbacks;

	beforeEach( () => {
		( { state, callbacks } = store( 'gatherpress' ) );

		// Reset shared registry state between tests.
		delete state.posts;
	} );

	/**
	 * Builds the RSVP response dropdown markup in select mode.
	 *
	 * @param {Object} counts Response counts keyed as the block emits them.
	 * @return {Object} The trigger element and the item anchors.
	 */
	function setupDom( counts ) {
		const countsJson = JSON.stringify( counts );

		document.body.innerHTML = `
			<div class="wp-block-gatherpress-rsvp-response" data-counts='${ countsJson }'>
				<div class="wp-block-gatherpress-dropdown" data-dropdown-mode="select">
					<a class="wp-block-gatherpress-dropdown__trigger" href="#" role="button" aria-expanded="false" tabindex="0">Attending (%d)</a>
					<div class="wp-block-gatherpress-dropdown__menu">
						<div class="wp-block-gatherpress-dropdown-item gatherpress--is-attending"><a href="#" data-status="attending">Attending (%d)</a></div>
						<div class="wp-block-gatherpress-dropdown-item gatherpress--is-waiting-list"><a href="#" data-status="waiting_list">Waiting List (%d)</a></div>
						<div class="wp-block-gatherpress-dropdown-item gatherpress--is-not-attending"><a href="#" data-status="not_attending">Not Attending (%d)</a></div>
					</div>
				</div>
			</div>
		`;

		return {
			trigger: document.querySelector(
				'.wp-block-gatherpress-dropdown__trigger'
			),
			items: Array.from(
				document.querySelectorAll(
					'.wp-block-gatherpress-dropdown-item a'
				)
			),
		};
	}

	/**
	 * Runs the callback once per dropdown item, as the runtime does.
	 *
	 * @param {HTMLElement[]} items The item anchors to process.
	 */
	function processItems( items ) {
		getContext.mockReturnValue( { postId: 123 } );

		items.forEach( ( item ) => {
			getElement.mockReturnValue( { ref: item } );
			callbacks.processRsvpDropdown();
		} );
	}

	it( 'marks the trigger aria-disabled when attending is the only option', () => {
		const { trigger, items } = setupDom( {
			attending: 2,
			waiting_list: 0,
			not_attending: 0,
		} );

		processItems( items );

		expect(
			trigger.classList.contains( 'gatherpress--is-disabled' )
		).toBe( true );
		expect( trigger.getAttribute( 'aria-disabled' ) ).toBe( 'true' );
	} );

	it( 'leaves the trigger operable when another option has responses', () => {
		const { trigger, items } = setupDom( {
			attending: 2,
			waiting_list: 1,
			not_attending: 0,
		} );

		processItems( items );

		expect(
			trigger.classList.contains( 'gatherpress--is-disabled' )
		).toBe( false );
		expect( trigger.hasAttribute( 'aria-disabled' ) ).toBe( false );
	} );
} );
