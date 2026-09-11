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
 * The one series post every row on the page belongs to.
 *
 * A single post rendered many times is the whole point of the feature, so the
 * post ID deliberately cannot distinguish one row from the next.
 */
const SERIES_POST_ID = 42;

/**
 * Three occurrences of that one series, in the order the archive renders them.
 */
const OCCURRENCES = [ '20260823T180000', '20260829T180000', '20260830T180000' ];

/**
 * Three ordinary, unrelated events, used as the non-recurring control group.
 */
const PLAIN_POST_IDS = [ 11, 12, 13 ];

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
 * Builds one RSVP block row, as the server renders it for a single loop
 * iteration: every status container present, all but the user's own hidden.
 *
 * @param {string} status The RSVP status the server rendered this row with.
 *
 * @return {string} The row markup.
 */
function rowMarkup( status ) {
	const details = JSON.stringify( { status, guests: 0, anonymous: 0 } );

	return `
		<div class="wp-block-gatherpress-rsvp" data-user-details='${ details }'>
			<div data-rsvp-status="no_status">
				<a href="#" class="gatherpress-rsvp--trigger-update" data-set-status="attending">RSVP</a>
			</div>
			<div class="gatherpress--is-hidden" data-rsvp-status="attending">
				<a href="#" class="gatherpress-rsvp--trigger-update" data-set-status="not_attending">Attending</a>
			</div>
			<div class="gatherpress--is-hidden" data-rsvp-status="not_attending">
				<a href="#" class="gatherpress-rsvp--trigger-update" data-set-status="attending">Not attending</a>
			</div>
		</div>
	`;
}

/**
 * Renders a page of RSVP rows and returns the row elements.
 *
 * @param {number} count Number of rows to render.
 *
 * @return {HTMLElement[]} The rendered row elements, in document order.
 */
function renderPage( count ) {
	document.body.innerHTML = Array.from( { length: count }, () =>
		rowMarkup( 'no_status' )
	).join( '' );

	return Array.from(
		document.querySelectorAll( '.wp-block-gatherpress-rsvp' )
	);
}

/**
 * Reads the RSVP status a row is currently showing the visitor.
 *
 * This is the thing a human sees, and what the defect shows up in.
 *
 * @param {HTMLElement} row A rendered RSVP block row.
 *
 * @return {string} The `data-rsvp-status` of the row's one visible container.
 */
function visibleStatus( row ) {
	const visible = Array.from(
		row.querySelectorAll( '[data-rsvp-status]' )
	).filter( ( node ) => ! node.classList.contains( 'gatherpress--is-hidden' ) );

	return visible.map( ( node ) => node.dataset.rsvpStatus ).join( '+' );
}

/**
 * Runs the block's own watch callback for every row, exactly as the
 * Interactivity runtime re-runs `callbacks.renderRsvpBlock` on each element
 * carrying the directive whenever the shared store changes.
 *
 * @param {Function}      renderRsvpBlock The registered callback.
 * @param {HTMLElement[]} rows            The rendered rows.
 * @param {Object[]}      contexts        One block context per row.
 *
 * @return {string[]} The per-row visible-status vector after the pass.
 */
function renderAllRows( renderRsvpBlock, rows, contexts ) {
	rows.forEach( ( row, index ) => {
		getElement.mockReturnValue( { ref: row } );
		getContext.mockReturnValue( contexts[ index ] );

		renderRsvpBlock();
	} );

	return rows.map( visibleStatus );
}

/**
 * Regression coverage for the client half of the per-occurrence RSVP defect.
 *
 * A human found it by hand: on an archive or Query Loop where one recurring
 * series renders many occurrence rows, RSVPing to one occurrence visually
 * applied the RSVP to every row of that series. The server renders each row
 * correctly; the client store keys on the post ID alone, so all of a series'
 * rows share one entry in `state.posts` and collapse to a single status the
 * moment anything writes to it.
 *
 * Every assertion here is on the whole per-row vector rather than on one row:
 * "row 2 shows attending" passes just as well when
 * all three rows show attending, which is the defect. The right answer and the
 * collapsed answer only differ across the vector.
 */
describe( 'RSVP state across many occurrence rows of one series', () => {
	let state;
	let actions;
	let callbacks;
	let requestBodies;

	beforeEach( () => {
		( { state, actions, callbacks } = store( 'gatherpress' ) );

		getNonce.clearCache();

		state.eventApiUrl = 'https://example.test/wp-json/gatherpress/v1';
		delete state.posts;

		requestBodies = [];

		window.alert = jest.fn();
		jest.spyOn( console, 'warn' ).mockImplementation( () => {} );

		global.fetch = jest.fn( ( url, options ) => {
			if ( url.endsWith( '/nonce' ) ) {
				return Promise.resolve( {
					json: () => Promise.resolve( { nonce: 'test-nonce' } ),
				} );
			}

			const body = JSON.parse( options.body );

			requestBodies.push( body );

			// Echo the submitted guest count and anonymity back, as the real
			// route does. The merge below writes the response, not the request,
			// so a fixed payload would silently undo what the visitor sent.
			return Promise.resolve( {
				status: 200,
				json: () =>
					Promise.resolve( {
						success: true,
						status: 'attending',
						guests: body.guests ?? 0,
						anonymous: body.anonymous ?? 0,
						responses: {
							attending: { count: 1 },
							waiting_list: { count: 0 },
							not_attending: { count: 0 },
						},
					} ),
			} );
		} );
	} );

	afterEach( () => {
		jest.restoreAllMocks();
		delete global.fetch;
		document.body.innerHTML = '';
	} );

	/**
	 * Clicks the "RSVP" trigger inside one row and lets the request settle.
	 *
	 * @param {HTMLElement} row     The row being RSVPd to.
	 * @param {Object}      context That row's block context.
	 *
	 * @return {Promise<void>} Resolves once the RSVP flow has settled.
	 */
	async function rsvpTo( row, context ) {
		const trigger = row.querySelector(
			'[data-rsvp-status="no_status"] .gatherpress-rsvp--trigger-update'
		);

		getElement.mockReturnValue( { ref: trigger } );
		getContext.mockReturnValue( context );

		actions.updateRsvp( { preventDefault: jest.fn() } );

		await flushRsvpFlow();
	}

	it( 'keeps every other occurrence of the series unchanged when one is RSVPd to', async () => {
		const rows = renderPage( OCCURRENCES.length );
		const contexts = OCCURRENCES.map( ( recurrenceId ) => ( {
			postId: SERIES_POST_ID,
			recurrenceId,
		} ) );

		expect( renderAllRows( callbacks.renderRsvpBlock, rows, contexts ) ).toEqual( [
			'no_status',
			'no_status',
			'no_status',
		] );

		await rsvpTo( rows[ 1 ], contexts[ 1 ] );

		expect( renderAllRows( callbacks.renderRsvpBlock, rows, contexts ) ).toEqual( [
			'no_status',
			'attending',
			'no_status',
		] );
	} );

	it( 'gives each occurrence of the series its own entry in the store', async () => {
		const rows = renderPage( OCCURRENCES.length );
		const contexts = OCCURRENCES.map( ( recurrenceId ) => ( {
			postId: SERIES_POST_ID,
			recurrenceId,
		} ) );

		renderAllRows( callbacks.renderRsvpBlock, rows, contexts );

		await rsvpTo( rows[ 1 ], contexts[ 1 ] );

		expect(
			OCCURRENCES.map(
				( recurrenceId ) =>
					state.posts[ `${ SERIES_POST_ID }:${ recurrenceId }` ]
						?.currentUser?.status ?? 'missing'
			)
		).toEqual( [ 'no_status', 'attending', 'no_status' ] );
	} );

	it( 'sends the RSVPd row occurrence with the request, not the page occurrence', async () => {
		const rows = renderPage( OCCURRENCES.length );
		const contexts = OCCURRENCES.map( ( recurrenceId ) => ( {
			postId: SERIES_POST_ID,
			recurrenceId,
		} ) );

		renderAllRows( callbacks.renderRsvpBlock, rows, contexts );

		await rsvpTo( rows[ 1 ], contexts[ 1 ] );

		expect( requestBodies ).toHaveLength( 1 );
		expect( requestBodies[ 0 ].post_id ).toBe( SERIES_POST_ID );
		expect( requestBodies[ 0 ].recurrence_id ).toBe( OCCURRENCES[ 1 ] );
	} );

	it( 'sends the guests and anonymity held in the RSVPd row own slice', async () => {
		const rows = renderPage( OCCURRENCES.length );
		const contexts = OCCURRENCES.map( ( recurrenceId ) => ( {
			postId: SERIES_POST_ID,
			recurrenceId,
		} ) );

		// The visitor is already attending the second occurrence, with three
		// guests, anonymously. Only that row's slice carries those values, so a
		// read keyed on the bare post ID finds nothing, `initPostContext` seeds
		// a fresh slice, and the toggle silently zeroes both. The visitor is
		// removed from a date they never touched and their guests vanish.
		state.posts = {};
		state.posts[ `${ SERIES_POST_ID }:${ OCCURRENCES[ 1 ] }` ] = {
			currentUser: { status: 'attending', guests: 3, anonymous: 1 },
		};

		const trigger = rows[ 1 ].querySelector(
			'[data-rsvp-status="attending"] .gatherpress-rsvp--trigger-update'
		);

		getElement.mockReturnValue( { ref: trigger } );
		getContext.mockReturnValue( contexts[ 1 ] );

		// The post-success branch this trigger takes closes the modal outright,
		// and the modal manager it reaches for lives outside a bare row. Its own
		// suite covers that; here only the request body matters, so the two
		// modal actions are stood in for and put back.
		const { openModal, closeModal } = actions;

		actions.openModal = jest.fn();
		actions.closeModal = jest.fn();

		try {
			actions.updateRsvp( { preventDefault: jest.fn() } );

			await flushRsvpFlow();
		} finally {
			actions.openModal = openModal;
			actions.closeModal = closeModal;
		}

		expect( requestBodies ).toHaveLength( 1 );
		expect( requestBodies[ 0 ] ).toMatchObject( {
			status: 'not_attending',
			guests: 3,
			anonymous: 1,
			recurrence_id: OCCURRENCES[ 1 ],
		} );
	} );

	it( 'leaves ordinary non-recurring events keyed and requested exactly as before', async () => {
		const rows = renderPage( PLAIN_POST_IDS.length );
		const contexts = PLAIN_POST_IDS.map( ( postId ) => ( { postId } ) );

		expect( renderAllRows( callbacks.renderRsvpBlock, rows, contexts ) ).toEqual( [
			'no_status',
			'no_status',
			'no_status',
		] );

		await rsvpTo( rows[ 1 ], contexts[ 1 ] );

		expect( renderAllRows( callbacks.renderRsvpBlock, rows, contexts ) ).toEqual( [
			'no_status',
			'attending',
			'no_status',
		] );

		// The state key stays the bare post ID and the request body stays
		// byte-identical to what a site with no recurring events has always sent.
		expect( Object.keys( state.posts ) ).toEqual(
			PLAIN_POST_IDS.map( String )
		);
		expect( requestBodies[ 0 ] ).not.toHaveProperty( 'recurrence_id' );
	} );

	it( 'scopes a guest-count change to the row it was made on', async () => {
		const contexts = OCCURRENCES.map( ( recurrenceId ) => ( {
			postId: SERIES_POST_ID,
			recurrenceId,
		} ) );
		const inputs = contexts.map( () => {
			const input = document.createElement( 'input' );

			input.value = '0';
			document.body.append( input );

			return input;
		} );

		inputs[ 1 ].value = '3';

		// Guests are only editable once attending, and `sendRsvpApiRequest`
		// refuses to send a `no_status` change, so the fixture starts from the
		// state the control is actually reachable in.
		state.posts = {};
		contexts.forEach( ( context ) => {
			state.posts[ `${ SERIES_POST_ID }:${ context.recurrenceId }` ] = {
				currentUser: {
					status: 'attending',
					guests: 0,
					anonymous: 0,
				},
			};
		} );

		getElement.mockReturnValue( { ref: inputs[ 1 ] } );
		getContext.mockReturnValue( contexts[ 1 ] );
		actions.updateGuestCount();

		await flushRsvpFlow();

		// Read every row back through the callback that renders the number,
		// so the vector is what a visitor would count on screen.
		const displays = contexts.map( ( context ) => {
			const output = document.createElement( 'span' );

			output.dataset.guestSingular = '%d guest';
			output.dataset.guestPlural = '%d guests';

			getElement.mockReturnValue( { ref: output } );
			getContext.mockReturnValue( context );
			callbacks.updateGuestCountDisplay();

			return output.textContent;
		} );

		expect( displays ).toEqual( [ '', '3 guests', '' ] );
		expect( requestBodies[ 0 ].recurrence_id ).toBe( OCCURRENCES[ 1 ] );
	} );

	it( 'scopes an anonymity change to the row it was made on', async () => {
		const contexts = OCCURRENCES.map( ( recurrenceId ) => ( {
			postId: SERIES_POST_ID,
			recurrenceId,
		} ) );
		const boxes = contexts.map( () => {
			const box = document.createElement( 'input' );

			box.type = 'checkbox';
			document.body.append( box );

			return box;
		} );

		boxes[ 1 ].checked = true;

		getElement.mockReturnValue( { ref: boxes[ 1 ] } );
		getContext.mockReturnValue( contexts[ 1 ] );
		actions.updateAnonymous();

		await flushRsvpFlow();

		// Every checkbox is unchecked again before the watch pass, so the
		// vector reports what the store says rather than what the DOM kept.
		boxes.forEach( ( box ) => {
			box.checked = false;
		} );

		const checked = contexts.map( ( context, index ) => {
			getElement.mockReturnValue( { ref: boxes[ index ] } );
			getContext.mockReturnValue( context );
			callbacks.monitorAnonymousStatus();

			return boxes[ index ].checked;
		} );

		expect( checked ).toEqual( [ false, true, false ] );
	} );

	it( 'restores each row guest input from that row own slice', () => {
		const contexts = OCCURRENCES.map( ( recurrenceId ) => ( {
			postId: SERIES_POST_ID,
			recurrenceId,
		} ) );

		state.posts = {};
		contexts.forEach( ( context, index ) => {
			state.posts[ `${ SERIES_POST_ID }:${ context.recurrenceId }` ] = {
				currentUser: { status: 'attending', guests: index, anonymous: 0 },
			};
		} );

		const values = contexts.map( ( context ) => {
			const input = document.createElement( 'input' );

			getElement.mockReturnValue( { ref: input } );
			getContext.mockReturnValue( context );
			callbacks.setGuestCount();

			return input.value;
		} );

		expect( values ).toEqual( [ '0', '1', '2' ] );
	} );

	it( 'declines to key a guest count off a row that carries no post', () => {
		const output = document.createElement( 'span' );

		output.dataset.guestSingular = '%d guest';
		output.dataset.guestPlural = '%d guests';

		state.posts = {};

		getElement.mockReturnValue( { ref: output } );
		getContext.mockReturnValue( {} );
		callbacks.updateGuestCountDisplay();

		// `getPostKey( 0, undefined )` is `0`, which `initPostContext` refuses,
		// so a context-less block leaves the store untouched rather than
		// creating one slice every such block would then share.
		expect( state.posts ).toEqual( {} );
		expect( output.textContent ).toBe( '' );
	} );

	it( 'still renders a status block whose row carries no post', () => {
		const row = renderPage( 1 )[ 0 ];

		state.posts = {};

		getElement.mockReturnValue( { ref: row } );
		getContext.mockReturnValue( {} );
		callbacks.renderRsvpBlock();

		// The server-rendered `data-user-details` is what the block falls back
		// to, so a context-less block still shows the visitor their own status.
		expect( visibleStatus( row ) ).toBe( 'no_status' );
	} );

	it( 'renders the singular guest label and hides an empty count', () => {
		const context = {
			postId: SERIES_POST_ID,
			recurrenceId: OCCURRENCES[ 0 ],
		};
		const output = document.createElement( 'span' );

		output.dataset.guestSingular = '%d guest';
		output.dataset.guestPlural = '%d guests';

		state.posts = {
			[ `${ SERIES_POST_ID }:${ OCCURRENCES[ 0 ] }` ]: {
				currentUser: { status: 'attending', guests: 1, anonymous: 0 },
			},
		};

		getElement.mockReturnValue( { ref: output } );
		getContext.mockReturnValue( context );
		callbacks.updateGuestCountDisplay();

		expect( output.textContent ).toBe( '1 guest' );
		expect(
			output.classList.contains( 'gatherpress--is-hidden' )
		).toBe( false );

		state.posts[
			`${ SERIES_POST_ID }:${ OCCURRENCES[ 0 ] }`
		].currentUser.guests = 0;
		callbacks.updateGuestCountDisplay();

		expect( output.textContent ).toBe( '' );
		expect( output.classList.contains( 'gatherpress--is-hidden' ) ).toBe(
			true
		);
	} );
} );
