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
import '@src/blocks/rsvp-template/view';

/**
 * The occurrence a page is rendering reaches the server only because
 * `renderBlocks()` spreads it into the `rsvp-status-html` request body. Nothing
 * in the PHP suite can see that spread: drop it and every RSVP roster rendered
 * from an occurrence page silently falls back to the whole series, with the
 * REST layer behaving exactly as designed.
 */
describe( 'rsvp-template view renderBlocks', () => {
	let state;
	let callbacks;
	let responseElement;

	beforeEach( () => {
		jest.clearAllMocks();

		( { state, callbacks } = store( 'gatherpress' ) );

		state.eventApiUrl = 'https://example.test/wp-json/gatherpress/v1';
		state.posts = {};

		document.body.innerHTML = `
			<div class="wp-block-gatherpress-rsvp-response"
				data-limit-enabled="1" data-limit="8">
				<div class="wrapper">
					<div id="template" data-block-template="[]"></div>
				</div>
			</div>
		`;

		responseElement = document.querySelector(
			'.wp-block-gatherpress-rsvp-response'
		);

		getContext.mockReturnValue( { postId: 42 } );
		getElement.mockReturnValue( {
			ref: document.getElementById( 'template' ),
		} );

		global.fetch = jest.fn( () =>
			Promise.resolve( {
				json: () => Promise.resolve( { success: false } ),
			} )
		);
	} );

	/**
	 * Read the JSON body of the fetch the callback issued.
	 *
	 * @return {Object} The decoded request body.
	 */
	const requestBody = () =>
		JSON.parse( global.fetch.mock.calls[ 0 ][ 1 ].body );

	it( 'sends the occurrence identifier the row is rendering', async () => {
		getContext.mockReturnValue( {
			postId: 42,
			recurrenceId: '20260917T180000',
		} );

		await callbacks.renderBlocks();

		expect( requestBody() ).toMatchObject( {
			post_id: 42,
			recurrence_id: '20260917T180000',
		} );
	} );

	it( 'omits the occurrence identifier for a row with no occurrence', async () => {
		await callbacks.renderBlocks();

		expect( requestBody() ).not.toHaveProperty( 'recurrence_id' );
		expect( requestBody() ).toMatchObject( { post_id: 42 } );
	} );

	it( 'reads the row own filter selection when rendering the response list', async () => {
		global.fetch = jest.fn( () =>
			Promise.resolve( {
				json: () =>
					Promise.resolve( {
						success: true,
						content: '<div data-id="1">Ada</div>',
						responses: { attending: { count: 0 } },
					} ),
			} )
		);

		state.posts = {
			'42:20260917T180000': { rsvpSelection: 'waiting_list' },
		};

		getContext.mockReturnValue( {
			postId: 42,
			recurrenceId: '20260917T180000',
		} );

		await callbacks.renderBlocks();
		await Promise.resolve();

		expect( requestBody().status ).toBe( 'waiting_list' );
	} );

	it( 'defaults to the attending filter for a row with no selection', async () => {
		global.fetch = jest.fn( () =>
			Promise.resolve( {
				json: () =>
					Promise.resolve( {
						success: true,
						content: '<div data-id="1">Ada</div>',
						responses: { attending: { count: 0 } },
					} ),
			} )
		);

		await callbacks.renderBlocks();
		await Promise.resolve();

		expect( requestBody().status ).toBe( 'attending' );
	} );

	it( 'still sends the limit fields it has always sent', async () => {
		getContext.mockReturnValue( {
			postId: 42,
			recurrenceId: '20260917T180000',
		} );

		await callbacks.renderBlocks();

		expect( requestBody() ).toMatchObject( {
			limit_enabled: true,
			limit: 8,
		} );
		expect( responseElement.dataset.limit ).toBe( '8' );
	} );
} );
