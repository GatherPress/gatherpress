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
 * Mock the Interactivity API with a namespace-merging store so every
 * module contributing to the `gatherpress` namespace (rsvp view,
 * modal-manager view, helpers) shares one registry, mirroring the
 * real runtime.
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
import { getNonce } from '@src/helpers/interactivity';
// Import the actual modules so their actions register on the shared store.
import '@src/blocks/rsvp/view';
import '@src/blocks/modal-manager/view';

/**
 * Waits long enough for the fire-and-forget sendRsvpApiRequest promise
 * chain and the 10ms closeModal timeout inside updateRsvp to settle.
 *
 * @return {Promise<void>} Resolves after the RSVP flow settles.
 */
function flushRsvpFlow() {
	return new Promise( ( resolve ) => {
		setTimeout( resolve, 30 );
	} );
}

/**
 * Regression coverage for #1719 — after a successful RSVP from the
 * `no_status` state, updateRsvp switches to the attending state's modal.
 * When the attending state markup is missing (malformed or partial block
 * content), it used to call `openModal( null, null )` and throw, which
 * surfaced a bogus "RSVP API request failed" alert even though the RSVP
 * was saved.
 */
describe( 'rsvp updateRsvp post-success modal switch', () => {
	let state;
	let actions;

	beforeEach( () => {
		( { state, actions } = store( 'gatherpress' ) );

		// Reset shared registry state between tests.
		delete state.posts;
		state.eventApiUrl = 'https://example.test/wp-json/gatherpress/v1';

		// The modal actions' own behavior is covered in the modal-manager
		// tests; here only the call routing matters.
		actions.openModal = jest.fn();
		actions.closeModal = jest.fn();

		getNonce.clearCache();

		window.alert = jest.fn();
		jest.spyOn( console, 'warn' ).mockImplementation( () => {} );

		global.fetch = jest.fn( ( url ) => {
			if ( url.endsWith( '/nonce' ) ) {
				return Promise.resolve( {
					json: () => Promise.resolve( { nonce: 'test-nonce' } ),
				} );
			}

			// POST /rsvp.
			return Promise.resolve( {
				status: 200,
				json: () =>
					Promise.resolve( {
						success: true,
						status: 'attending',
						guests: 0,
						anonymous: false,
					} ),
			} );
		} );
	} );

	afterEach( () => {
		jest.restoreAllMocks();
		delete global.fetch;
	} );

	/**
	 * Builds the RSVP block DOM and wires getElement/getContext to the
	 * `no_status` trigger button.
	 *
	 * @param {boolean} withAttendingState - Whether to include the attending
	 *                                     state's container in the markup.
	 * @return {Object} The trigger button and (possibly null) attending button.
	 */
	function setupDom( withAttendingState ) {
		const attendingMarkup = withAttendingState
			? `<div data-rsvp-status="attending">
					<button type="button" class="gatherpress-rsvp--trigger-update">Edit RSVP</button>
				</div>`
			: '';

		document.body.innerHTML = `
			<div class="wp-block-gatherpress-rsvp">
				<div data-rsvp-status="no_status">
					<button type="button" class="gatherpress-rsvp--trigger-update">Attend</button>
				</div>
				${ attendingMarkup }
			</div>
		`;

		const trigger = document.querySelector(
			'[data-rsvp-status="no_status"] button'
		);

		getElement.mockReturnValue( { ref: trigger } );
		getContext.mockReturnValue( { postId: 123 } );

		return {
			trigger,
			attendingButton: document.querySelector(
				'[data-rsvp-status="attending"] button'
			),
		};
	}

	it( 'switches to the attending modal when its markup is present', async () => {
		const { trigger, attendingButton } = setupDom( true );

		actions.updateRsvp( { preventDefault: jest.fn() } );
		await flushRsvpFlow();

		expect( actions.openModal ).toHaveBeenCalledWith(
			null,
			attendingButton
		);
		expect( actions.closeModal ).toHaveBeenCalledWith(
			null,
			trigger,
			false
		);
		expect( window.alert ).not.toHaveBeenCalled();
	} );

	it( 'fully closes the modal instead of throwing when the attending markup is missing (#1719)', async () => {
		const { trigger } = setupDom( false );

		actions.updateRsvp( { preventDefault: jest.fn() } );
		await flushRsvpFlow();

		expect( actions.openModal ).not.toHaveBeenCalled();
		expect( actions.closeModal ).toHaveBeenCalledWith(
			null,
			trigger,
			true
		);
		// The RSVP succeeded; no failure alert may fire.
		expect( window.alert ).not.toHaveBeenCalled();
	} );
} );
