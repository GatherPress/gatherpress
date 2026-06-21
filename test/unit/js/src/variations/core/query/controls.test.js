/**
 * External dependencies
 */
import { describe, expect, it, jest, beforeEach } from '@jest/globals';
import { render, screen } from '@testing-library/react';
import '@testing-library/jest-dom';

jest.mock( '@wordpress/hooks', () => ( {
	addFilter: jest.fn(),
} ) );

jest.mock( '@wordpress/element', () => {
	const actual = jest.requireActual( '@wordpress/element' );
	// Invoke the effect synchronously so QueryPosttypeObserver's auto-
	// transform fires during render in the HOC tests below.
	return { ...actual, useEffect: jest.fn( ( fn ) => fn() ) };
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
	sprintf: ( fmt, ...args ) => args.reduce( ( s, a ) => s.replace( '%s', a ), fmt ),
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

jest.mock( '@src/helpers/editor', () => ( {
	usePostTypeLabel: jest.fn( ( key, postType, fallback ) => fallback ),
} ) );

const mockUpdateBlockAttributes = jest.fn();
jest.mock( '@wordpress/data', () => ( {
	useDispatch: jest.fn( () => ( {
		updateBlockAttributes: mockUpdateBlockAttributes,
	} ) ),
} ) );

/**
 * WordPress dependencies
 */
import { addFilter } from '@wordpress/hooks';
import { usePostTypeSupports } from '@src/helpers/event';

/**
 * Internal dependencies
 */
import { EventQueryControlsPanel } from '@src/variations/core/query/controls';

// Importing the module above runs its side effects, including the
// `addFilter` registration of the `withEventQueryControls` HOC. Capture the
// HOC reference here so the QueryPosttypeObserver tests below can render
// with it.
const withEventQueryControls = addFilter.mock.calls[ 0 ][ 2 ];

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

	it( 'returns null when the queried post type does not support gatherpress-event-date', () => {
		usePostTypeSupports.mockReturnValue( false );

		const { container } = render(
			<EventQueryControlsPanel { ...baseProps( { postType: 'post' } ) } />
		);

		expect( container.firstChild ).toBeNull();
		expect( usePostTypeSupports ).toHaveBeenCalledWith(
			'gatherpress-event-date',
			'post'
		);
	} );

	it( 'renders the panel when the queried post type supports gatherpress-event-date', () => {
		usePostTypeSupports.mockReturnValue( true );

		render( <EventQueryControlsPanel { ...baseProps() } /> );

		expect( screen.getByTestId( 'inspector-controls' ) ).toBeInTheDocument();
		expect( screen.getByTestId( 'panel-body' ) ).toHaveAttribute(
			'data-title',
			'Event Query Settings'
		);
		expect( usePostTypeSupports ).toHaveBeenCalledWith(
			'gatherpress-event-date',
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
			'gatherpress-event-date',
			undefined
		);
	} );
} );

describe( 'EventQueryControlsPanel query cleanup (#1756)', () => {
	// Pull just the `query` payload from the most recent updateBlockAttributes
	// call that targeted the query (the metadata-name effect also fires).
	const lastQueryUpdate = () => {
		const call = mockUpdateBlockAttributes.mock.calls
			.filter( ( [ , attrs ] ) => attrs?.query )
			.at( -1 );
		return call ? call[ 1 ].query : undefined;
	};

	beforeEach( () => {
		usePostTypeSupports.mockReset();
		mockUpdateBlockAttributes.mockClear();
	} );

	it( 'strips event-only vars and resets orderBy=datetime when switching to a non-event post type', () => {
		usePostTypeSupports.mockReturnValue( false );

		render(
			<EventQueryControlsPanel
				clientId="abc"
				attributes={ {
					query: {
						postType: 'post',
						perPage: 5,
						gatherpress_event_query: 'upcoming',
						include_unfinished: 1,
						orderBy: 'datetime',
						order: 'asc',
					},
				} }
			/>
		);

		expect( lastQueryUpdate() ).toEqual( {
			postType: 'post',
			perPage: 5,
			orderBy: 'date',
			order: 'desc',
		} );
	} );

	it( 'resets orderBy=rand as well, since rand is GatherPress-only on the REST enum', () => {
		usePostTypeSupports.mockReturnValue( false );

		render(
			<EventQueryControlsPanel
				clientId="abc"
				attributes={ {
					query: { postType: 'page', orderBy: 'rand' },
				} }
			/>
		);

		expect( lastQueryUpdate() ).toEqual( {
			postType: 'page',
			orderBy: 'date',
			order: 'desc',
		} );
	} );

	it( 'does not touch the query when no event-only vars are present', () => {
		usePostTypeSupports.mockReturnValue( false );

		render(
			<EventQueryControlsPanel
				clientId="abc"
				attributes={ {
					query: { postType: 'post', orderBy: 'title' },
				} }
			/>
		);

		expect( lastQueryUpdate() ).toBeUndefined();
	} );

	it( 'does not clean up while the post type still supports event dates', () => {
		usePostTypeSupports.mockReturnValue( true );

		render(
			<EventQueryControlsPanel
				clientId="abc"
				attributes={ {
					query: {
						postType: 'gatherpress_event',
						orderBy: 'datetime',
						gatherpress_event_query: 'upcoming',
						inherit: false,
					},
				} }
			/>
		);

		expect( lastQueryUpdate() ).toBeUndefined();
	} );
} );

describe( 'QueryPosttypeObserver auto-transform', () => {
	const renderQuery = ( query = {}, namespace = undefined ) => {
		const setAttributes = jest.fn();
		const MockBlockEdit = () => <div data-testid="block-edit" />;
		const Enhanced = withEventQueryControls( MockBlockEdit );
		render(
			<Enhanced
				name="core/query"
				attributes={ { namespace, query } }
				setAttributes={ setAttributes }
			/>
		);
		return setAttributes;
	};

	beforeEach( () => {
		usePostTypeSupports.mockReset();
	} );

	it( 'transforms a plain core/query block into the event variation when the post type supports event-date', () => {
		// Reproduces #1608: any custom post type that declares
		// gatherpress-event-date support (e.g. `production`) should
		// trigger the auto-transform on first selection, not just the
		// hardcoded `gatherpress_event` post type.
		usePostTypeSupports.mockReturnValue( true );

		const setAttributes = renderQuery( { postType: 'production' } );

		expect( setAttributes ).toHaveBeenCalledTimes( 1 );
		const next = setAttributes.mock.calls[ 0 ][ 0 ];
		expect( next.namespace ).toBe( 'gatherpress-event-query' );
		expect( next.query ).toMatchObject( {
			postType: 'production',
			gatherpress_event_query: 'upcoming',
			include_unfinished: 1,
			order: 'asc',
			orderBy: 'datetime',
			inherit: false,
		} );
	} );

	it( 'does not transform when the post type does not support event-date', () => {
		usePostTypeSupports.mockReturnValue( false );

		const setAttributes = renderQuery( { postType: 'post' } );

		expect( setAttributes ).not.toHaveBeenCalled();
	} );

	it( 'does not transform when the block already has a namespace (e.g. AQL)', () => {
		usePostTypeSupports.mockReturnValue( true );

		const setAttributes = renderQuery(
			{ postType: 'production' },
			'advanced-query-loop'
		);

		expect( setAttributes ).not.toHaveBeenCalled();
	} );
} );
