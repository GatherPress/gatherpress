/**
 * External dependencies.
 */
import {
	describe,
	expect,
	it,
	beforeEach,
	jest,
} from '@jest/globals';
import { render, screen, fireEvent } from '@testing-library/react';
import '@testing-library/jest-dom';

/**
 * WordPress dependencies.
 */
import { addFilter } from '@wordpress/hooks';
import { switchToBlockType } from '@wordpress/blocks';
import { useDispatch } from '@wordpress/data';

/**
 * Internal dependencies.
 */
import { usePostTypeSupports } from '@src/helpers/event';

jest.mock( '@wordpress/hooks', () => ( {
	addFilter: jest.fn(),
} ) );

jest.mock( '@wordpress/blocks', () => ( {
	switchToBlockType: jest.fn(),
} ) );

jest.mock( '@wordpress/data', () => ( {
	useDispatch: jest.fn(),
} ) );

jest.mock( '@wordpress/compose', () => ( {
	createHigherOrderComponent: jest.fn( ( hoc ) => hoc ),
} ) );

jest.mock( '@wordpress/block-editor', () => ( {
	InspectorControls: ( { children } ) => (
		<div data-testid="inspector-controls">{ children }</div>
	),
} ) );

jest.mock( '@wordpress/components', () => ( {
	Button: ( { children, onClick } ) => (
		<button data-testid="convert-button" onClick={ onClick }>
			{ children }
		</button>
	),
	PanelBody: ( { children } ) => (
		<div data-testid="panel-body">{ children }</div>
	),
} ) );

jest.mock( '@wordpress/i18n', () => ( {
	__: ( text ) => text,
} ) );

jest.mock( '@src/helpers/event', () => ( {
	usePostTypeSupports: jest.fn(),
} ) );

// Importing for side effects: this triggers the addFilter call we capture below.
require( '@src/supports/post-date-convert' );

// Capture the registration call once at module load — `clearAllMocks` in
// `beforeEach` would otherwise wipe the call history before tests can read it.
const registrationCall = addFilter.mock.calls.find(
	( [ , namespace ] ) =>
		'gatherpress/post-date-convert-to-event-date' === namespace
);
const wrapBlockEdit = registrationCall[ 2 ];

const getWrappedEdit = () => {
	const BlockEditMock = ( props ) => (
		<div data-testid="original-edit" data-name={ props.name } />
	);
	return wrapBlockEdit( BlockEditMock );
};

describe( 'post-date-convert support', () => {
	const replaceBlocks = jest.fn();

	beforeEach( () => {
		usePostTypeSupports.mockReset();
		switchToBlockType.mockReset();
		replaceBlocks.mockReset();
		useDispatch.mockReturnValue( { replaceBlocks } );
	} );

	it( 'registers the editor.BlockEdit filter under the gatherpress namespace', () => {
		expect( registrationCall ).toBeDefined();
		expect( registrationCall[ 0 ] ).toBe( 'editor.BlockEdit' );
		expect( typeof registrationCall[ 2 ] ).toBe( 'function' );
	} );

	it( 'passes through unchanged for non-post-date blocks even when post type supports event-date', () => {
		usePostTypeSupports.mockReturnValue( true );
		const Wrapped = getWrappedEdit();

		render(
			<Wrapped
				name="core/paragraph"
				clientId="abc"
				attributes={ {} }
			/>
		);

		expect( screen.getByTestId( 'original-edit' ) ).toBeInTheDocument();
		expect(
			screen.queryByTestId( 'convert-button' )
		).not.toBeInTheDocument();
	} );

	it( 'passes through unchanged when post type does not support event-date', () => {
		usePostTypeSupports.mockReturnValue( false );
		const Wrapped = getWrappedEdit();

		render(
			<Wrapped
				name="core/post-date"
				clientId="abc"
				attributes={ {} }
			/>
		);

		expect( screen.getByTestId( 'original-edit' ) ).toBeInTheDocument();
		expect(
			screen.queryByTestId( 'convert-button' )
		).not.toBeInTheDocument();
	} );

	it( 'renders the inspector button on core/post-date when post type supports event-date', () => {
		usePostTypeSupports.mockReturnValue( true );
		const Wrapped = getWrappedEdit();

		render(
			<Wrapped
				name="core/post-date"
				clientId="abc"
				attributes={ {} }
			/>
		);

		expect( screen.getByTestId( 'original-edit' ) ).toBeInTheDocument();
		expect( screen.getByTestId( 'inspector-controls' ) ).toBeInTheDocument();
		expect( screen.getByTestId( 'convert-button' ) ).toHaveTextContent(
			'Convert to Event Date'
		);
		expect(
			screen.getByText( "Display an event's date and time." )
		).toBeInTheDocument();
	} );

	it( 'invokes switchToBlockType + replaceBlocks when the button is clicked', () => {
		usePostTypeSupports.mockReturnValue( true );
		switchToBlockType.mockReturnValue( [
			{ name: 'gatherpress/event-date' },
		] );
		const Wrapped = getWrappedEdit();
		const attributes = { format: 'F j, Y', textAlign: 'center' };

		render(
			<Wrapped
				name="core/post-date"
				clientId="block-id-1"
				attributes={ attributes }
			/>
		);

		fireEvent.click( screen.getByTestId( 'convert-button' ) );

		expect( switchToBlockType ).toHaveBeenCalledWith(
			{
				name: 'core/post-date',
				attributes,
				innerBlocks: [],
			},
			'gatherpress/event-date'
		);
		expect( replaceBlocks ).toHaveBeenCalledWith( 'block-id-1', [
			{ name: 'gatherpress/event-date' },
		] );
	} );

	it( 'does not call replaceBlocks when switchToBlockType returns null', () => {
		usePostTypeSupports.mockReturnValue( true );
		switchToBlockType.mockReturnValue( null );
		const Wrapped = getWrappedEdit();

		render(
			<Wrapped
				name="core/post-date"
				clientId="block-id-2"
				attributes={ {} }
			/>
		);

		fireEvent.click( screen.getByTestId( 'convert-button' ) );

		expect( replaceBlocks ).not.toHaveBeenCalled();
	} );
} );
