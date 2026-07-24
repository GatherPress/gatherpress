/**
 * External dependencies
 */
import { describe, expect, it, jest } from '@jest/globals';

/**
 * Mock the commands store so the registered command config can be captured
 * without a live store.
 */
jest.mock(
	'@wordpress/commands',
	() => ( {
		store: 'core/commands',
	} ),
	{ virtual: true }
);

/**
 * Mock the data-store dispatch so registerCommand calls land on a spy. The spy
 * is created inside the factory (jest.mock is hoisted above any const) and
 * re-exported as `__registerCommand` so the tests can assert on it.
 */
jest.mock(
	'@wordpress/data',
	() => {
		const registerCommand = jest.fn();
		return {
			dispatch: jest.fn( () => ( { registerCommand } ) ),
			__registerCommand: registerCommand,
		};
	},
	{ virtual: true }
);

/**
 * WordPress dependencies
 */
// eslint-disable-next-line import/named -- `__registerCommand` only exists on the virtual mock above.
import { __registerCommand as registerCommand } from '@wordpress/data';

/**
 * Internal dependencies
 */
import { getAddVenueCommand } from '@src/commands';

describe( 'add new venue command', () => {
	it( 'registers a single command on import', () => {
		// The module self-registers once at import time; getAddVenueCommand()
		// in the other tests only builds config and never dispatches, so this
		// count stays at 1 for the whole suite without any reset.
		expect( registerCommand ).toHaveBeenCalledTimes( 1 );
		expect( registerCommand ).toHaveBeenCalledWith(
			expect.objectContaining( { name: 'gatherpress/add-new-venue' } )
		);
	} );

	it( 'is a view command so the palette styles it like core "Go to" entries', () => {
		const command = getAddVenueCommand();

		// `view` is what gives the palette-supplied arrow icon and "View"
		// badge, matching the core-generated "Go to: Events > Add New Event".
		expect( command.category ).toBe( 'view' );
		expect( command.name ).toBe( 'gatherpress/add-new-venue' );
	} );

	it( 'labels itself "Go to: Events > Add New Venue"', () => {
		const command = getAddVenueCommand();

		expect( command.label ).toBe( 'Go to: Events > Add New Venue' );
		// searchLabel matches the label so it is found by the same text.
		expect( command.searchLabel ).toBe( command.label );
	} );

	it( 'closes the palette and navigates to the new venue screen', () => {
		const command = getAddVenueCommand();
		const close = jest.fn();

		command.callback( { close } );

		expect( close ).toHaveBeenCalled();

		// jsdom implements no navigation and document.location is
		// non-configurable, so the href assignment logs "Not implemented:
		// navigation" rather than navigating. Declaring the error covers the
		// callback body without suppressing it — the error is itself the
		// evidence the navigation was attempted.
		expect( console ).toHaveErrored();
	} );
} );
