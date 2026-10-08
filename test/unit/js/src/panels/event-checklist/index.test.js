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
				: args[ Number( position ) - 1 ],
		);
	},
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
	Button: () => <button type="button" />,
	CheckboxControl: () => <input type="checkbox" />,
	DropdownMenu: () => <div />,
	Flex: ( { children } ) => <div>{ children }</div>,
	FlexBlock: ( { children } ) => <div>{ children }</div>,
	FlexItem: ( { children } ) => <div>{ children }</div>,
	PanelRow: ( { children } ) => <div>{ children }</div>,
	TextControl: () => <input type="text" />,
	createSlotFill: jest.fn( () => ( {
		Fill: ( { children } ) => <div>{ children }</div>,
		Slot: () => <div data-testid="checklist-slot" />,
	} ) ),
} ) );

jest.mock( '@wordpress/data', () => ( {
	useDispatch: jest.fn( () => ( {
		editPost: jest.fn(),
		unlockPostSaving: jest.fn(),
	} ) ),
	useSelect: jest.fn( () => undefined ),
} ) );

// The checklist list needs the editor data store and `uuid`, which this test
// does not stand up. It is covered in checklist.test.js; here it only has to
// not block the render so the panel's placement and gate can be asserted.
jest.mock( '@src/panels/event-checklist/checklist', () => () => (
	<div data-testid="checklist-panel" />
) );

let mockSupportsChecklist = true;

jest.mock( '@src/helpers/event', () => ( {
	usePostTypeSupports: jest.fn( () => mockSupportsChecklist ),
} ) );

/**
 * Internal dependencies
 */
import { registerPlugin } from '@wordpress/plugins';
import '@src/panels/event-checklist';

const registrationCall = registerPlugin.mock.calls[ 0 ];
const EventChecklistSettings = registrationCall[ 1 ].render;

describe( 'EventChecklistSettings', () => {
	beforeEach( () => {
		jest.clearAllMocks();
		mockSupportsChecklist = true;
	} );

	it( 'registers the event checklist plugin on the render path', () => {
		expect( registrationCall[ 0 ] ).toBe( 'gatherpress-event-checklist' );
		expect( typeof EventChecklistSettings ).toBe( 'function' );
	} );

	it( 'renders the checklist panel when the post type supports it', () => {
		render( <EventChecklistSettings /> );

		expect( screen.getByText( 'Checklist' ) ).toBeInTheDocument();
		expect( screen.getByTestId( 'checklist-slot' ) ).toBeInTheDocument();
	} );

	it( 'renders nothing on a post type without checklist support', () => {
		mockSupportsChecklist = false;

		const { container } = render( <EventChecklistSettings /> );

		expect( container ).toBeEmptyDOMElement();
	} );
} );
