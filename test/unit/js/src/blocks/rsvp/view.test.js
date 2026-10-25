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
import { waitFor } from '@testing-library/react';

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
			withSyncEvent: jest.fn( ( fn ) => {
				fn.sync = true;
				return fn;
			} ),
		};
	},
	{ virtual: true },
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

// The real modal actions, kept before any test swaps in a spy.
const { openModal: realOpenModal, closeModal: realCloseModal } =
	store( 'gatherpress' ).actions;

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
			'[data-rsvp-status="no_status"] button',
		);

		getElement.mockReturnValue( { ref: trigger } );
		getContext.mockReturnValue( { postId: 123 } );

		return {
			trigger,
			attendingButton: document.querySelector(
				'[data-rsvp-status="attending"] button',
			),
		};
	}

	it( 'switches to the attending modal when its markup is present', async () => {
		const { trigger, attendingButton } = setupDom( true );

		actions.updateRsvp( { preventDefault: jest.fn() } );

		// closeModal is the last step of the flow, so waiting on it means the
		// whole chain has settled without racing a fixed sleep against it.
		await waitFor( () => expect( actions.closeModal ).toHaveBeenCalled() );

		expect( actions.openModal ).toHaveBeenCalledWith(
			null,
			attendingButton,
		);
		expect( actions.closeModal ).toHaveBeenCalledWith(
			null,
			trigger,
			false,
		);
		expect( window.alert ).not.toHaveBeenCalled();
	} );

	it( 'fully closes the modal instead of throwing when the attending markup is missing (#1719)', async () => {
		const { trigger } = setupDom( false );

		actions.updateRsvp( { preventDefault: jest.fn() } );

		// openModal is decided synchronously before closeModal is scheduled,
		// so once closeModal has fired the negative assertion below is settled.
		await waitFor( () => expect( actions.closeModal ).toHaveBeenCalled() );

		expect( actions.openModal ).not.toHaveBeenCalled();
		expect( actions.closeModal ).toHaveBeenCalledWith(
			null,
			trigger,
			true,
		);
		// The RSVP succeeded; no failure alert may fire.
		expect( window.alert ).not.toHaveBeenCalled();
	} );
} );

/**
 * Focus after an RSVP change (#2428). The `renderRsvpBlock` watch shows the
 * container of the new status some time after the request resolves, so the
 * post-success modal switch must wait for that, and must follow the status
 * the server returned rather than the one requested.
 */
describe( 'rsvp updateRsvp focus after the status changes (#2428)', () => {
	const STATUSES = [ 'no_status', 'attending', 'waiting_list', 'not_attending' ];
	// How long the simulated watch takes to show the new state. Longer than
	// the 10 ms the old code waited, so a fixed-delay switch loses the race.
	const WATCH_DELAY = 40;

	let state;
	let actions;
	let calls;

	/**
	 * Whether an element sits in a visible RSVP status container.
	 *
	 * @param {HTMLElement} el The element.
	 * @return {boolean} True when no ancestor carries the hidden class.
	 */
	const isShown = ( el ) => ! el.closest( '.gatherpress--is-hidden' );

	/**
	 * Mirrors what the `renderRsvpBlock` watch does with the status classes,
	 * including its fallback to no_status when the block has no container
	 * for the status (#1719).
	 *
	 * @param {string} status The status to show.
	 */
	const applyStatus = ( status ) => {
		const shown = document.querySelector( `[data-rsvp-status="${ status }"]` )
			? status
			: 'no_status';

		document.querySelectorAll( '[data-rsvp-status]' ).forEach( ( el ) => {
			el.classList.toggle(
				'gatherpress--is-hidden',
				el.dataset.rsvpStatus !== shown,
			);
		} );
	};

	/**
	 * Builds an RSVP block with one container per status, each with the
	 * update button its modal holds, and shows `current`.
	 *
	 * @param {string}   current The status shown on load.
	 * @param {string[]} omit    Statuses to leave out (partial block markup).
	 */
	const setupDom = ( current, omit = [] ) => {
		const containers = STATUSES.filter(
			( status ) => ! omit.includes( status ),
		).map( ( status ) => {
			// The no_status modal's button is Attend, the not_attending one
			// is Attending, and the others are Not Attending.
			const setStatus = {
				no_status: '',
				not_attending: 'attending',
			}[ status ] ?? 'not_attending';

			return `<div data-rsvp-status="${ status }">
				<button type="button" class="gatherpress-rsvp--trigger-update" data-set-status="${ setStatus }">${ status }</button>
			</div>`;
		} );

		document.body.innerHTML = `<div class="wp-block-gatherpress-rsvp">${ containers.join( '' ) }</div>`;

		applyStatus( current );
	};

	const button = ( status ) =>
		document.querySelector( `[data-rsvp-status="${ status }"] button` );

	/**
	 * Clicks the update button of `from`; the server answers `to`, and the
	 * simulated watch shows `to` after WATCH_DELAY (or never, if `show` is false).
	 *
	 * @param {string}  from The status whose button is pressed.
	 * @param {string}  to   The status the server returns.
	 * @param {boolean} show Whether the watch shows the new status.
	 * @return {HTMLElement} The pressed button.
	 */
	const respond = ( from, to, show = true ) => {
		global.fetch = jest.fn( ( url ) => {
			if ( url.endsWith( '/nonce' ) ) {
				return Promise.resolve( {
					json: () => Promise.resolve( { nonce: 'test-nonce' } ),
				} );
			}

			if ( show ) {
				setTimeout( () => applyStatus( to ), WATCH_DELAY );
			}

			return Promise.resolve( {
				status: 200,
				json: () =>
					Promise.resolve( {
						success: true,
						status: to,
						guests: 0,
						anonymous: false,
					} ),
			} );
		} );

		const trigger = button( from );

		getElement.mockReturnValue( { ref: trigger } );
		getContext.mockReturnValue( { postId: 321 } );
		actions.updateRsvp( { preventDefault: jest.fn() } );

		return trigger;
	};

	beforeEach( () => {
		( { state, actions } = store( 'gatherpress' ) );

		delete state.posts;
		state.eventApiUrl = 'https://example.test/wp-json/gatherpress/v1';

		// Record each modal call with whether its element was visible at
		// that moment: a focus() aimed at a hidden element is lost.
		calls = [];
		actions.openModal = jest.fn( ( event, el ) => {
			calls.push( { action: 'open', el, shown: isShown( el ) } );
		} );
		actions.closeModal = jest.fn( ( event, el, findActiveSibling ) => {
			calls.push( {
				action: 'close',
				el,
				findActiveSibling,
				shown: isShown( el ),
			} );
		} );

		getNonce.clearCache();

		window.alert = jest.fn();
		jest.spyOn( console, 'warn' ).mockImplementation( () => {} );
	} );

	afterEach( () => {
		jest.restoreAllMocks();
		delete global.fetch;
	} );

	it( 'opens the waiting list modal when Attend puts the person on the waiting list', async () => {
		setupDom( 'no_status' );
		const trigger = respond( 'no_status', 'waiting_list' );

		await waitFor( () => expect( actions.closeModal ).toHaveBeenCalled() );

		const open = calls.find( ( call ) => 'open' === call.action );

		expect( open?.el ).toBe( button( 'waiting_list' ) );
		expect( open?.shown ).toBe( true );
		expect( actions.closeModal ).toHaveBeenCalledWith( null, trigger, false );
	} );

	it( 'opens the attending modal only after the attending state is shown', async () => {
		setupDom( 'no_status' );
		respond( 'no_status', 'attending' );

		await waitFor( () => expect( actions.closeModal ).toHaveBeenCalled() );

		const open = calls.find( ( call ) => 'open' === call.action );

		expect( open?.el ).toBe( button( 'attending' ) );
		expect( open?.shown ).toBe( true );
	} );

	it( 'fully closes after Not Attending only once the old state is hidden', async () => {
		setupDom( 'attending' );
		const trigger = respond( 'attending', 'not_attending' );

		await waitFor( () => expect( actions.closeModal ).toHaveBeenCalled() );

		expect( actions.openModal ).not.toHaveBeenCalled();
		expect( actions.closeModal ).toHaveBeenCalledWith( null, trigger, true );
		// closeModal finds the visible sibling to focus only when the
		// pressed button's container is already hidden.
		expect( calls[ 0 ].shown ).toBe( false );
	} );

	it( 'waits for the no_status fallback when the block has no container for the new status', async () => {
		// Someone who declined presses Attend on a full event, and the block
		// markup has no waiting_list container (#1719 partial markup).
		setupDom( 'not_attending', [ 'waiting_list' ] );
		const trigger = respond( 'not_attending', 'waiting_list' );

		await waitFor( () => expect( actions.closeModal ).toHaveBeenCalled() );

		expect( actions.openModal ).not.toHaveBeenCalled();
		expect( actions.closeModal ).toHaveBeenCalledWith( null, trigger, true );
		// The watch has hidden not_attending and shown no_status, so
		// closeModal moves focus to the visible no_status trigger.
		expect( calls[ 0 ].shown ).toBe( false );
		expect( isShown( button( 'no_status' ) ) ).toBe( true );
	} );

	it( 'fully closes the modal when the block has neither the new status nor a no_status container', async () => {
		// Partial markup with nothing to wait for: whenStatusShown() gets no
		// container and gives up at once, so updateRsvp() closes the modal.
		setupDom( 'not_attending', [ 'attending', 'no_status' ] );
		const trigger = respond( 'not_attending', 'attending', false );

		expect( actions.closeModal ).not.toHaveBeenCalled();

		await waitFor( () => expect( actions.closeModal ).toHaveBeenCalled() );

		expect( actions.openModal ).not.toHaveBeenCalled();
		expect( actions.closeModal ).toHaveBeenCalledWith( null, trigger, true );
		expect( window.alert ).not.toHaveBeenCalled();
	} );

	it( 'still closes the modal when the new state never shows', async () => {
		setupDom( 'no_status' );
		const trigger = respond( 'no_status', 'attending', false );

		await waitFor(
			() => expect( actions.closeModal ).toHaveBeenCalled(),
			{ timeout: 3000 },
		);

		expect( actions.openModal ).not.toHaveBeenCalled();
		expect( actions.closeModal ).toHaveBeenCalledWith( null, trigger, true );
		expect( window.alert ).not.toHaveBeenCalled();
	} );
} );

/**
 * Where focus lands after an RSVP change, with the real modal actions (#2428).
 * Each status container holds a modal manager, as on the front end. jsdom has
 * no layout, so it cannot show focus dropping to the page body; these tests
 * pin which control receives focus instead.
 */
describe( 'rsvp updateRsvp focus target with the real modal actions (#2428)', () => {
	// The first control in each status's modal, which openModal focuses.
	const FIRST_CONTROL = {
		no_status: '<button type="button" class="gatherpress-rsvp--trigger-update" data-set-status="">Attend</button>',
		attending: '<input type="number" class="guests" /><button type="button" class="gatherpress-rsvp--trigger-update" data-set-status="not_attending">Not Attending</button>',
		waiting_list: '<input type="checkbox" class="anonymous" /><button type="button" class="gatherpress-rsvp--trigger-update" data-set-status="not_attending">Not Attending</button>',
		not_attending: '<button type="button" class="gatherpress-rsvp--trigger-update" data-set-status="attending">Attending</button>',
	};

	let state;
	let actions;

	const container = ( status ) =>
		document.querySelector( `[data-rsvp-status="${ status }"]` );
	const modal = ( status ) =>
		container( status ).querySelector( '.wp-block-gatherpress-modal' );

	/**
	 * Builds the RSVP block with a modal manager per status, shows `current`
	 * and opens its modal with focus on its update button.
	 *
	 * @param {string} current The status shown on load.
	 * @return {HTMLElement} The focused update button.
	 */
	const setupDom = ( current ) => {
		document.body.innerHTML = `<div class="wp-block-gatherpress-rsvp">${ Object.keys(
			FIRST_CONTROL,
		)
			.map(
				( status ) => `<div data-rsvp-status="${ status }" class="${
					status === current ? '' : 'gatherpress--is-hidden'
				}">
					<div class="wp-block-gatherpress-modal-manager">
						<div class="gatherpress-modal--trigger-open"><button type="button">Edit RSVP</button></div>
						<dialog class="wp-block-gatherpress-modal"${ status === current ? ' open' : '' }>
							<div class="wp-block-gatherpress-modal-content">${ FIRST_CONTROL[ status ] }</div>
						</dialog>
					</div>
				</div>`,
			)
			.join( '' ) }</div>`;

		const trigger = modal( current ).querySelector(
			'.gatherpress-rsvp--trigger-update',
		);

		trigger.focus();

		return trigger;
	};

	/**
	 * Presses `trigger`; the server answers `to`, and the simulated
	 * renderRsvpBlock watch shows `to` 40 ms later.
	 *
	 * @param {HTMLElement} trigger The pressed update button.
	 * @param {string}      to      The status the server returns.
	 */
	const respond = ( trigger, to ) => {
		global.fetch = jest.fn( ( url ) => {
			if ( url.endsWith( '/nonce' ) ) {
				return Promise.resolve( {
					json: () => Promise.resolve( { nonce: 'test-nonce' } ),
				} );
			}

			setTimeout( () => {
				document.querySelectorAll( '[data-rsvp-status]' ).forEach( ( el ) => {
					el.classList.toggle(
						'gatherpress--is-hidden',
						el.dataset.rsvpStatus !== to,
					);
				} );
			}, 40 );

			return Promise.resolve( {
				status: 200,
				json: () =>
					Promise.resolve( {
						success: true,
						status: to,
						guests: 0,
						anonymous: false,
					} ),
			} );
		} );

		getElement.mockReturnValue( { ref: trigger } );
		getContext.mockReturnValue( { postId: 654 } );
		actions.updateRsvp( { preventDefault: jest.fn() } );
	};

	beforeEach( () => {
		( { state, actions } = store( 'gatherpress' ) );

		delete state.posts;
		state.eventApiUrl = 'https://example.test/wp-json/gatherpress/v1';

		actions.openModal = realOpenModal;
		actions.closeModal = realCloseModal;

		getNonce.clearCache();

		window.alert = jest.fn();
		jest.spyOn( console, 'warn' ).mockImplementation( () => {} );
	} );

	afterEach( () => {
		jest.restoreAllMocks();
		delete global.fetch;
		document.body.innerHTML = '';
	} );

	it( 'focuses the first control of the waiting list modal when Attend lands on the waiting list', async () => {
		respond( setupDom( 'no_status' ), 'waiting_list' );

		// The starting modal closes on its own timer, just after focus moves.
		await waitFor( () => {
			expect( document.activeElement ).toBe(
				modal( 'waiting_list' ).querySelector( '.anonymous' ),
			);
			expect( modal( 'no_status' ).open ).toBe( false );
		} );

		expect( modal( 'waiting_list' ).open ).toBe( true );
	} );

	it( 'focuses the number of guests in the attending modal after Attend', async () => {
		respond( setupDom( 'no_status' ), 'attending' );

		// The starting modal closes on its own timer, just after focus moves.
		await waitFor( () => {
			expect( document.activeElement ).toBe(
				modal( 'attending' ).querySelector( '.guests' ),
			);
			expect( modal( 'no_status' ).open ).toBe( false );
		} );
	} );

	it( 'focuses the Edit RSVP trigger of the not attending state after Not Attending', async () => {
		respond( setupDom( 'attending' ), 'not_attending' );

		await waitFor( () =>
			expect( document.activeElement ).toBe(
				container( 'not_attending' ).querySelector(
					'.gatherpress-modal--trigger-open button',
				),
			),
		);
	} );
} );

describe( 'rsvp updateRsvp without JavaScript (#2039)', () => {
	it( 'is synchronous, so preventDefault() keeps the no-JavaScript form from submitting', () => {
		expect( store( 'gatherpress' ).actions.updateRsvp.sync ).toBe( true );
	} );
} );
