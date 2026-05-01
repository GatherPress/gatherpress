/**
 * External dependencies.
 */
import { describe, expect, it, jest, beforeEach } from '@jest/globals';
import { render, screen } from '@testing-library/react';
import '@testing-library/jest-dom';

jest.mock( '@wordpress/hooks', () => ( {
	addFilter: jest.fn(),
} ) );

jest.mock( '@wordpress/element', () => {
	const actual = jest.requireActual( '@wordpress/element' );
	return { ...actual, useEffect: jest.fn() };
} );

jest.mock( '@wordpress/plugins', () => ( {
	registerPlugin: jest.fn(),
} ) );

jest.mock( '@wordpress/block-editor', () => ( {
	InspectorControls: ( { children } ) => (
		<div data-testid="inspector-controls">{ children }</div>
	),
} ) );

jest.mock( '@wordpress/components', () => ( {
	PanelBody: ( { children, title } ) => (
		<div data-testid="panel-body" data-title={ title }>
			{ children }
		</div>
	),
} ) );

jest.mock( '@wordpress/i18n', () => ( {
	__: ( text ) => text,
} ) );

jest.mock( '@src/variations/core/query/slots/query-controls', () => {
	const Slot = ( { fillProps } ) => (
		<div
			data-testid="query-controls-slot"
			data-post-type={ fillProps?.attributes?.query?.postType }
		/>
	);
	const Fill = ( { children } ) => <>{ children }</>;
	Fill.Slot = Slot;
	return { __esModule: true, default: Fill };
} );

jest.mock( '@src/variations/core/query/slots/inherited-query-controls', () => {
	const Slot = () => <div data-testid="inherited-query-controls-slot" />;
	const Fill = ( { children } ) => <>{ children }</>;
	Fill.Slot = Slot;
	return { __esModule: true, default: Fill };
} );

jest.mock( '@src/variations/core/query/components', () => ( {
	EventQueryControlsSlotFill: () => null,
	EventInheritedQueryControlsSlotFill: () => null,
} ) );

jest.mock( '@src/helpers/event', () => ( {
	usePostTypeSupports: jest.fn(),
} ) );

/**
 * WordPress dependencies.
 */
import { usePostTypeSupports } from '@src/helpers/event';

/**
 * Internal dependencies.
 */
import { EventQueryControlsPanel } from '@src/variations/core/query/controls';

describe( 'EventQueryControlsPanel', () => {
	const baseProps = ( { postType = 'gatherpress_event', inherit = false } = {} ) => ( {
		attributes: {
			query: {
				postType,
				inherit,
			},
		},
	} );

	beforeEach( () => {
		usePostTypeSupports.mockReset();
	} );

	it( 'returns null when the queried post type does not support gatherpress-event', () => {
		usePostTypeSupports.mockReturnValue( false );

		const { container } = render(
			<EventQueryControlsPanel { ...baseProps( { postType: 'post' } ) } />
		);

		expect( container.firstChild ).toBeNull();
		expect( usePostTypeSupports ).toHaveBeenCalledWith(
			'gatherpress-event',
			'post'
		);
	} );

	it( 'renders the panel when the queried post type supports gatherpress-event', () => {
		usePostTypeSupports.mockReturnValue( true );

		render( <EventQueryControlsPanel { ...baseProps() } /> );

		expect( screen.getByTestId( 'inspector-controls' ) ).toBeInTheDocument();
		expect( screen.getByTestId( 'panel-body' ) ).toHaveAttribute(
			'data-title',
			'Event Query Settings'
		);
		expect( usePostTypeSupports ).toHaveBeenCalledWith(
			'gatherpress-event',
			'gatherpress_event'
		);
	} );

	it( 'renders the standard query slot when inherit is false', () => {
		usePostTypeSupports.mockReturnValue( true );

		render(
			<EventQueryControlsPanel { ...baseProps( { inherit: false } ) } />
		);

		expect(
			screen.getByTestId( 'query-controls-slot' )
		).toBeInTheDocument();
		expect(
			screen.queryByTestId( 'inherited-query-controls-slot' )
		).not.toBeInTheDocument();
	} );

	it( 'renders the inherited slot when inherit is true', () => {
		usePostTypeSupports.mockReturnValue( true );

		render(
			<EventQueryControlsPanel { ...baseProps( { inherit: true } ) } />
		);

		expect(
			screen.getByTestId( 'inherited-query-controls-slot' )
		).toBeInTheDocument();
		expect(
			screen.queryByTestId( 'query-controls-slot' )
		).not.toBeInTheDocument();
	} );

	it( 'gracefully handles a missing query.postType (treats it as unsupported)', () => {
		usePostTypeSupports.mockReturnValue( false );

		const { container } = render(
			<EventQueryControlsPanel attributes={ { query: {} } } />
		);

		expect( container.firstChild ).toBeNull();
		expect( usePostTypeSupports ).toHaveBeenCalledWith(
			'gatherpress-event',
			undefined
		);
	} );
} );
