/**
 * External dependencies
 */
import { beforeEach, describe, expect, it, jest } from '@jest/globals';

/**
 * Mock the Interactivity API with a namespace-merging store so every
 * module contributing to the `gatherpress` namespace shares one registry,
 * mirroring the real runtime. `withRecurrenceId()` reads that same namespace
 * from `src/helpers/interactivity`, so it sees what this file sets.
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
import '@src/blocks/rsvp-form/view';

/**
 * The open RSVP form is the anonymous write path, and the occurrence reaches
 * the server only because `handleRsvpFormSubmit()` spreads it into the request
 * body. Nothing in the PHP suite can see that spread: drop it and every open
 * RSVP submitted from an occurrence page lands series-wide, while the REST
 * layer behaves exactly as designed.
 */
describe( 'rsvp-form view handleRsvpFormSubmit', () => {
	let state;
	let actions;
	let callbacks;

	beforeEach( () => {
		jest.clearAllMocks();

		( { state, actions, callbacks } = store( 'gatherpress' ) );

		state.eventApiUrl = 'https://example.test/wp-json/gatherpress/v1';
		state.rsvpForm = { isSubmitting: false };
		state.posts = {};

		document.body.innerHTML = `
			<form id="rsvp">
				<input name="author" value="Ada Lovelace" />
				<input name="email" value="ada@example.test" />
				<button class="gatherpress-submit-button" type="submit"></button>
			</form>
		`;

		getContext.mockReturnValue( { postId: 42 } );
		getElement.mockReturnValue( { ref: document.getElementById( 'rsvp' ) } );

		// URL-aware, because the submit path makes two requests: the nonce
		// fetch `getNonce()` performs, then the RSVP POST this test reads.
		global.fetch = jest.fn( ( url ) =>
			Promise.resolve( {
				status: 200,
				json: () =>
					Promise.resolve(
						url.endsWith( '/nonce' )
							? { nonce: 'test-nonce' }
							: { success: true }
					),
			} )
		);
	} );

	/**
	 * Submit the form through the registered action.
	 *
	 * @return {Promise<Object>} The decoded body of the RSVP request.
	 */
	const submit = async () => {
		await actions.handleRsvpFormSubmit( { preventDefault: jest.fn() } );

		const call = global.fetch.mock.calls.find( ( [ url ] ) =>
			url.endsWith( '/rsvp-form' )
		);

		expect( call ).toBeDefined();

		return JSON.parse( call[ 1 ].body );
	};

	it( 'sends the occurrence identifier the row is rendering', async () => {
		getContext.mockReturnValue( {
			postId: 42,
			recurrenceId: '20260917T180000',
		} );

		await expect( submit() ).resolves.toMatchObject( {
			comment_post_ID: 42,
			email: 'ada@example.test',
			recurrence_id: '20260917T180000',
		} );
	} );

	it( 'omits the occurrence identifier for a row with no occurrence', async () => {
		const body = await submit();

		expect( body ).not.toHaveProperty( 'recurrence_id' );
		expect( body ).toMatchObject( { comment_post_ID: 42 } );
	} );

	it( 'merges the returned counts into the row own state slice', async () => {
		const payload = {
			success: true,
			responses: {
				attending: { count: 3 },
				waiting_list: { count: 2 },
				not_attending: { count: 1 },
			},
		};

		global.fetch = jest.fn( ( url ) =>
			Promise.resolve( {
				status: 200,
				json: () =>
					Promise.resolve(
						url.endsWith( '/nonce' )
							? { nonce: 'test-nonce' }
							: payload
					),
			} )
		);

		getContext.mockReturnValue( {
			postId: 42,
			recurrenceId: '20260917T180000',
		} );

		await actions.handleRsvpFormSubmit( { preventDefault: jest.fn() } );

		// Scoped to the occurrence the form was rendered for. Written under the
		// bare post ID, an open RSVP on one date of a series would move the
		// counts shown against every other date.
		expect( state.posts[ '42:20260917T180000' ].eventResponses ).toEqual( {
			attending: 3,
			waitingList: 2,
			notAttending: 1,
		} );
		expect( state.posts[ 42 ] ).toBeUndefined();
	} );

	it( 'falls back to zeroed counts when the response carries none', async () => {
		global.fetch = jest.fn( ( url ) =>
			Promise.resolve( {
				status: 200,
				json: () =>
					Promise.resolve(
						url.endsWith( '/nonce' )
							? { nonce: 'test-nonce' }
							: { success: true, responses: {} }
					),
			} )
		);

		await actions.handleRsvpFormSubmit( { preventDefault: jest.fn() } );

		expect( state.posts[ 42 ].eventResponses ).toEqual( {
			attending: 0,
			waitingList: 0,
			notAttending: 0,
		} );
	} );

	it( 'initializes the row own state slice on load', () => {
		getContext.mockReturnValue( {
			postId: 42,
			recurrenceId: '20260917T180000',
		} );

		callbacks.initRsvpForm();

		expect( Object.keys( state.posts ) ).toEqual( [ '42:20260917T180000' ] );
		expect( state.rsvpForm.isSubmitting ).toBe( false );
	} );

	it( 'initializes an ordinary event under its bare post ID', () => {
		callbacks.initRsvpForm();

		expect( Object.keys( state.posts ) ).toEqual( [ '42' ] );
	} );

	it( 'writes no counts and initializes nothing for a form with no post', async () => {
		const payload = {
			success: true,
			responses: { attending: { count: 3 } },
		};

		global.fetch = jest.fn( ( url ) =>
			Promise.resolve( {
				status: 200,
				json: () =>
					Promise.resolve(
						url.endsWith( '/nonce' )
							? { nonce: 'test-nonce' }
							: payload
					),
			} )
		);

		getContext.mockReturnValue( {} );

		await actions.handleRsvpFormSubmit( { preventDefault: jest.fn() } );
		callbacks.initRsvpForm();

		expect( state.posts ).toEqual( {} );
	} );

	it( 'keeps the occurrence identifier out of the custom-field passthrough', async () => {
		getContext.mockReturnValue( {
			postId: 42,
			recurrenceId: '20260917T180000',
		} );

		const body = await submit();

		// The loop over remaining FormData entries must not overwrite it, and
		// nothing in the form can forge one, because the value comes from the
		// block context the server rendered this row with.
		expect( body.recurrence_id ).toBe( '20260917T180000' );
	} );
} );
