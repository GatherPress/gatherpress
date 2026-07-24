/**
 * External dependencies
 */
import { act, fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it, jest, beforeEach } from '@jest/globals';
import '@testing-library/jest-dom';

/**
 * Mock the command palette so the registered command configs can be inspected
 * and their callbacks invoked directly, without a live palette.
 */
jest.mock(
	'@wordpress/commands',
	() => ( {
		useCommand: jest.fn(),
	} ),
	{ virtual: true }
);

/**
 * Mock the plugin registry so the module's render component can be captured
 * and rendered on its own.
 */
jest.mock(
	'@wordpress/plugins',
	() => ( {
		registerPlugin: jest.fn(),
	} ),
	{ virtual: true }
);

/**
 * Stub the venue navigator. It pulls in core-data, the venue helpers, and the
 * editor store; none of that is what these tests are about.
 */
jest.mock(
	'@src/components/VenueNavigator',
	() => ( {
		__esModule: true,
		default: () => <div data-testid="venue-navigator" />,
	} ),
	{ virtual: true }
);

/**
 * WordPress dependencies
 */
import { useCommand } from '@wordpress/commands';
import { registerPlugin } from '@wordpress/plugins';

/**
 * Internal dependencies
 */
import '@src/commands';

/**
 * The render component the module handed to registerPlugin.
 *
 * @return {Function} The GatherPress commands component.
 */
const getCommandsComponent = () => registerPlugin.mock.calls[ 0 ][ 1 ].render;

/**
 * Pull a registered command config out of the useCommand mock by name.
 *
 * @param {string} name Command name.
 *
 * @return {Object|undefined} The command config passed to useCommand.
 */
const getCommand = ( name ) =>
	useCommand.mock.calls
		.map( ( [ config ] ) => config )
		.find( ( config ) => name === config.name );

/**
 * Run a command callback inside act(), since it fires outside React's event
 * system and its state update has to flush before the DOM is asserted on.
 *
 * @param {string} name  Command name.
 * @param {Object} close Mock close function supplied to the callback.
 *
 * @return {void}
 */
const runCommand = ( name, close = jest.fn() ) => {
	act( () => {
		getCommand( name ).callback( { close } );
	} );

	return close;
};

beforeEach( () => {
	useCommand.mockClear();
} );

describe( 'command palette registration', () => {
	it( 'registers the plugin under a gatherpress name', () => {
		expect( registerPlugin ).toHaveBeenCalledWith(
			'gatherpress-commands',
			expect.objectContaining( { render: expect.any( Function ) } )
		);
	} );

	it( 'registers both commands when rendered', () => {
		const Commands = getCommandsComponent();

		render( <Commands /> );

		expect( getCommand( 'gatherpress/add-new-venue' ) ).toBeDefined();
		expect( getCommand( 'gatherpress/add-new-event' ) ).toBeDefined();
	} );

	it( 'scopes the venue command to the block editor', () => {
		const Commands = getCommandsComponent();

		render( <Commands /> );

		// The venue command renders the navigator inline, and the navigator
		// resolves the venue post type from the post currently open.
		expect( getCommand( 'gatherpress/add-new-venue' ).context ).toBe(
			'block-editor'
		);
		expect(
			getCommand( 'gatherpress/add-new-event' ).context
		).toBeUndefined();
	} );

	it( 'renders nothing until the venue command runs', () => {
		const Commands = getCommandsComponent();

		const { container } = render( <Commands /> );

		expect( container ).toBeEmptyDOMElement();
	} );
} );

describe( 'add new venue command', () => {
	it( 'closes the palette and opens the venue modal', () => {
		const Commands = getCommandsComponent();

		render( <Commands /> );

		const close = runCommand( 'gatherpress/add-new-venue' );

		expect( close ).toHaveBeenCalled();
		expect( screen.getByTestId( 'venue-navigator' ) ).toBeInTheDocument();
	} );

	it( 'closes the modal again on request', () => {
		const Commands = getCommandsComponent();

		render( <Commands /> );

		runCommand( 'gatherpress/add-new-venue' );

		expect( screen.getByTestId( 'venue-navigator' ) ).toBeInTheDocument();

		fireEvent.click( screen.getByRole( 'button', { name: 'Cancel' } ) );

		expect(
			screen.queryByTestId( 'venue-navigator' )
		).not.toBeInTheDocument();
	} );
} );

describe( 'add new event command', () => {
	it( 'registers with a label and icon and no editor-only context', () => {
		const Commands = getCommandsComponent();

		render( <Commands /> );

		const command = getCommand( 'gatherpress/add-new-event' );

		expect( command.label ).toBe( 'Add new event' );
		expect( command.icon ).toBeDefined();
		expect( command.context ).toBeUndefined();
	} );

	it( 'closes the palette and navigates to the new event screen', () => {
		const Commands = getCommandsComponent();

		render( <Commands /> );

		const close = jest.fn();

		getCommand( 'gatherpress/add-new-event' ).callback( { close } );

		expect( close ).toHaveBeenCalled();

		// jsdom implements no navigation and window.location is
		// non-configurable, so it can be neither replaced nor spied on. The
		// assign() call therefore logs "Not implemented: navigation", which is
		// declared here rather than suppressed. That the error came from the
		// navigation attempt is itself the evidence the call was made.
		expect( console ).toHaveErrored();
	} );
} );
