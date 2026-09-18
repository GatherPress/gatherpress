/**
 * WordPress dependencies
 */
import { describe, expect, it, jest, beforeEach } from '@jest/globals';
import '@testing-library/jest-dom';
import { render, screen } from '@testing-library/react';

jest.mock( '@wordpress/i18n', () => ( {
	__: ( text ) => text,
	sprintf: ( format, ...args ) => {
		let sequential = 0;

		return format.replace( /%(?:(\d+)\$)?[ds]/g, ( match, position ) =>
			undefined === position
				? args[ sequential++ ]
				: args[ Number( position ) - 1 ]
		);
	},
} ) );

jest.mock( '@wordpress/dom-ready', () => ( {
	__esModule: true,
	default: jest.fn(),
} ) );

jest.mock( '@wordpress/hooks', () => ( {
	applyFilters: jest.fn( ( name, value ) => value ),
} ) );

jest.mock( '@wordpress/plugins', () => ( {
	registerPlugin: jest.fn(),
} ) );

jest.mock( '@wordpress/editor', () => ( {
	PluginDocumentSettingPanel: ( { title, children } ) => (
		<div>
			<h2>{ title }</h2>
			{ children }
		</div>
	),
} ) );

jest.mock( '@wordpress/components', () => ( {
	__experimentalVStack: ( { children } ) => <div>{ children }</div>,
} ) );

// The sibling panels need the full editor data store, which this test does not
// stand up. They are covered elsewhere; here they only have to not block the
// render so the checklist panel's placement can be asserted.
jest.mock( '@src/panels/event-settings/datetime-range', () => () => (
	<div data-testid="datetime-range" />
) );

jest.mock( '@src/panels/event-settings/notify-members', () => () => (
	<div data-testid="notify-members" />
) );

jest.mock( '@src/panels/event-settings/slot', () => ( {
	EventPluginDocumentSettings: { Slot: () => <div data-testid="slot" /> },
} ) );

jest.mock( '@src/panels/event-settings/checklist', () => () => (
	<div data-testid="checklist-panel" />
) );

let mockIsEventPostType = true;

jest.mock( '@src/helpers/event', () => ( {
	isEventPostType: jest.fn( () => mockIsEventPostType ),
} ) );

jest.mock( '@src/helpers/editor', () => ( {
	usePostTypeLabel: jest.fn( () => 'Event' ),
} ) );

jest.mock( '@wordpress/data', () => ( {
	dispatch: jest.fn( () => ( { toggleEditorPanelOpened: jest.fn() } ) ),
	select: jest.fn( () => ( { isEditorPanelOpened: jest.fn( () => true ) } ) ),
	useSelect: jest.fn( () => 'gatherpress_event' ),
} ) );

/**
 * Internal dependencies
 */
import { registerPlugin } from '@wordpress/plugins';
import '@src/panels/event-settings';

// Captured at import time: `clearAllMocks()` in `beforeEach` would otherwise
// wipe the call the module makes when it registers the plugin.
const registrationCall = registerPlugin.mock.calls[ 0 ];
const EventSettings = registrationCall[ 1 ].render;

describe( 'EventSettings', () => {
	beforeEach( () => {
		jest.clearAllMocks();
		mockIsEventPostType = true;
	} );

	it( 'registers the event settings plugin on the render path', () => {
		expect( registrationCall[ 0 ] ).toBe( 'gatherpress-event-settings' );
		expect( typeof EventSettings ).toBe( 'function' );
	} );

	it( 'renders the checklist panel inside the event settings panel', () => {
		render( <EventSettings /> );

		expect( screen.getByText( 'Event settings' ) ).toBeInTheDocument();
		expect( screen.getByTestId( 'checklist-panel' ) ).toBeInTheDocument();
		expect( screen.getByTestId( 'datetime-range' ) ).toBeInTheDocument();
		expect( screen.getByTestId( 'notify-members' ) ).toBeInTheDocument();
	} );

	it( 'renders nothing on a post type without event support', () => {
		mockIsEventPostType = false;

		const { container } = render( <EventSettings /> );

		expect( container ).toBeEmptyDOMElement();
	} );
} );
