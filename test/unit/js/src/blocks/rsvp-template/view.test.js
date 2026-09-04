/**
 * External dependencies
 */
import { beforeEach, describe, expect, it, jest } from '@jest/globals';

jest.mock(
	'@wordpress/interactivity',
	() => {
		const registries = {};

		return {
			store: ( name, config = {} ) => {
				if ( ! registries[ name ] ) {
					registries[ name ] = { state: {}, actions: {}, callbacks: {} };
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

describe( 'rsvp-template renderBlocks', () => {
	let callbacks;

	beforeEach( () => {
		const registry = store( 'gatherpress' );
		registry.state.eventApiUrl = 'https://example.test/wp-json/gatherpress/v1/event';
		registry.state.posts = { 7: { rsvpSelection: 'attending' } };
		callbacks = registry.callbacks;

		getContext.mockReturnValue( { postId: 7 } );
		getElement.mockReturnValue( {
			ref: {
				dataset: {
					blockTemplate: '{"blockName":"gatherpress/rsvp-template"}',
					blockSignature: 'a'.repeat( 64 ),
				},
				closest: () => ( { dataset: { limitEnabled: '1', limit: '8' } } ),
			},
		} );

		global.fetch = jest.fn( () =>
			Promise.resolve( { json: () => Promise.resolve( { success: false } ) } )
		);
	} );

	it( 'sends the template back with the signature it was emitted with', async () => {
		callbacks.renderBlocks();
		await Promise.resolve();

		expect( global.fetch ).toHaveBeenCalledTimes( 1 );

		const [ url, options ] = global.fetch.mock.calls[ 0 ];
		const body = JSON.parse( options.body );

		expect( url ).toBe( 'https://example.test/wp-json/gatherpress/v1/event/rsvp-status-html' );
		expect( body.block_data ).toBe( '{"blockName":"gatherpress/rsvp-template"}' );
		expect( body.block_signature ).toBe( 'a'.repeat( 64 ) );
		expect( body.post_id ).toBe( 7 );
		expect( body.status ).toBe( 'attending' );
	} );
} );
