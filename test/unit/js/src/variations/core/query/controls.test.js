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
	// Handles both `%s` and positional `%1$s` placeholders, so the generated
	// block names ("%1$s %2$s") come out as real names in the tests below.
	sprintf: ( fmt, ...args ) => {
		let index = 0;
		return fmt.replace(
			/%(?:(\d+)\$)?s/g,
			( match, position ) => args[ position ? position - 1 : index++ ],
		);
	},
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
	useIsPostTypeLoaded: jest.fn(),
} ) );

jest.mock( '@src/helpers/editor', () => ( {
	usePostTypeLabel: jest.fn( ( key, postType, fallback ) => fallback ),
} ) );

const mockUpdateBlockAttributes = jest.fn();
const mockMarkNextChangeAsNotPersistent = jest.fn();
jest.mock( '@wordpress/data', () => ( {
	useDispatch: jest.fn( () => ( {
		updateBlockAttributes: mockUpdateBlockAttributes,
		__unstableMarkNextChangeAsNotPersistent: mockMarkNextChangeAsNotPersistent,
	} ) ),
} ) );

/**
 * WordPress dependencies
 */
import { addFilter } from '@wordpress/hooks';
import { useIsPostTypeLoaded, usePostTypeSupports } from '@src/helpers/event';
import { usePostTypeLabel } from '@src/helpers/editor';

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
		useIsPostTypeLoaded.mockReturnValue( true );
	} );

	it( 'returns null when the queried post type does not support gatherpress-event-date', () => {
		usePostTypeSupports.mockReturnValue( false );

		const { container } = render(
			<EventQueryControlsPanel { ...baseProps( { postType: 'post' } ) } />,
		);

		expect( container.firstChild ).toBeNull();
		expect( usePostTypeSupports ).toHaveBeenCalledWith(
			'gatherpress-event-date',
			'post',
		);
	} );

	it( 'renders the panel when the queried post type supports gatherpress-event-date', () => {
		usePostTypeSupports.mockReturnValue( true );

		render( <EventQueryControlsPanel { ...baseProps() } /> );

		expect( screen.getByTestId( 'inspector-controls' ) ).toBeInTheDocument();
		expect( screen.getByTestId( 'panel-body' ) ).toHaveAttribute(
			'data-title',
			'Event Query Settings',
		);
		expect( usePostTypeSupports ).toHaveBeenCalledWith(
			'gatherpress-event-date',
			'gatherpress_event',
		);
	} );

	it( 'renders the standard query slot when inherit is false', () => {
		usePostTypeSupports.mockReturnValue( true );

		render(
			<EventQueryControlsPanel { ...baseProps( { inherit: false } ) } />,
		);

		expect(
			screen.getByTestId( 'query-controls-slot' ),
		).toBeInTheDocument();
		expect(
			screen.queryByTestId( 'inherited-query-controls-slot' ),
		).not.toBeInTheDocument();
	} );

	it( 'renders the inherited slot when inherit is true', () => {
		usePostTypeSupports.mockReturnValue( true );

		render(
			<EventQueryControlsPanel { ...baseProps( { inherit: true } ) } />,
		);

		expect(
			screen.getByTestId( 'inherited-query-controls-slot' ),
		).toBeInTheDocument();
		expect(
			screen.queryByTestId( 'query-controls-slot' ),
		).not.toBeInTheDocument();
	} );

	it( 'gracefully handles a missing query.postType (treats it as unsupported)', () => {
		usePostTypeSupports.mockReturnValue( false );

		const { container } = render(
			<EventQueryControlsPanel attributes={ { query: {} } } />,
		);

		expect( container.firstChild ).toBeNull();
		expect( usePostTypeSupports ).toHaveBeenCalledWith(
			'gatherpress-event-date',
			undefined,
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
		useIsPostTypeLoaded.mockReturnValue( true );
		mockUpdateBlockAttributes.mockClear();
	} );

	it( 'does not clean up while the post type is still loading (#2495)', () => {
		// `usePostTypeSupports` is false until `getPostType()` resolves, so
		// without the load gate an event loop lost its event query vars and
		// its datetime order when the editor opened.
		usePostTypeSupports.mockReturnValue( false );
		useIsPostTypeLoaded.mockReturnValue( false );

		render(
			<EventQueryControlsPanel
				clientId="abc"
				attributes={ {
					query: {
						postType: 'gatherpress_event',
						gatherpress_event_query: 'upcoming',
						include_unfinished: 1,
						orderBy: 'datetime',
						order: 'asc',
						inherit: false,
					},
				} }
			/>,
		);

		expect( lastQueryUpdate() ).toBeUndefined();
		expect( useIsPostTypeLoaded ).toHaveBeenCalledWith( 'gatherpress_event' );
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
			/>,
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
			/>,
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
			/>,
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
			/>,
		);

		expect( lastQueryUpdate() ).toBeUndefined();
	} );
} );

describe( 'EventQueryControlsPanel block name (#2495)', () => {
	// Collect the `metadata` payload of every updateBlockAttributes call that
	// targeted the metadata (the query cleanup effect also fires).
	const metadataUpdates = () =>
		mockUpdateBlockAttributes.mock.calls
			.filter( ( [ , attrs ] ) => attrs && 'metadata' in attrs )
			.map( ( [ , attrs ] ) => attrs.metadata );

	const renderPanel = ( {
		postType = 'gatherpress_event',
		eventQuery = 'upcoming',
		metadata,
	} = {} ) =>
		render(
			<EventQueryControlsPanel
				clientId="abc"
				attributes={ {
					metadata,
					query: {
						postType,
						gatherpress_event_query: eventQuery,
						orderBy: 'datetime',
						inherit: false,
					},
				} }
			/>,
		);

	const bindings = {
		content: { source: 'core/post-meta', args: { key: 'example' } },
	};

	beforeEach( () => {
		usePostTypeSupports.mockReset();
		usePostTypeSupports.mockReturnValue( true );
		useIsPostTypeLoaded.mockReturnValue( true );
		usePostTypeLabel.mockImplementation(
			( key, postType, fallback ) => fallback,
		);
		mockUpdateBlockAttributes.mockClear();
		mockMarkNextChangeAsNotPersistent.mockClear();
	} );

	// True when the not-persistent mark ran right before the metadata write.
	const wasMarkedNotPersistent = () => {
		const [ markOrder ] =
			mockMarkNextChangeAsNotPersistent.mock.invocationCallOrder;
		const [ updateOrder ] = mockUpdateBlockAttributes.mock.invocationCallOrder;
		return undefined !== markOrder && markOrder < updateOrder;
	};

	it( 'does not rename the block while the post type is still loading', () => {
		usePostTypeSupports.mockReturnValue( false );
		useIsPostTypeLoaded.mockReturnValue( false );

		renderPanel( { metadata: { name: 'Upcoming Events' } } );

		expect( metadataUpdates() ).toEqual( [] );
	} );

	it( 'does not write a name that is already set', () => {
		renderPanel( { metadata: { name: 'Upcoming Events' } } );

		expect( metadataUpdates() ).toEqual( [] );
	} );

	it( 'names an unnamed event loop without counting it as an edit', () => {
		// The name is a default, so opening a loop saved without one must
		// not show unsaved changes.
		renderPanel();

		expect( metadataUpdates() ).toEqual( [ { name: 'Upcoming Events' } ] );
		expect( wasMarkedNotPersistent() ).toBe( true );
	} );

	it( 'names an event loop saved with an empty name without counting it as an edit', () => {
		renderPanel( { metadata: { name: '' } } );

		expect( metadataUpdates() ).toEqual( [ { name: 'Upcoming Events' } ] );
		expect( wasMarkedNotPersistent() ).toBe( true );
	} );

	it( 'keeps the other metadata when it names the block', () => {
		renderPanel( { eventQuery: 'past', metadata: { bindings } } );

		expect( metadataUpdates() ).toEqual( [
			{ bindings, name: 'Past Events' },
		] );
	} );

	it( 'keeps a name that the author gave the block', () => {
		renderPanel( {
			eventQuery: 'past',
			metadata: { name: 'Our meetups', bindings },
		} );

		expect( metadataUpdates() ).toEqual( [] );
	} );

	it( 'renames a generated name when the query mode changes', () => {
		renderPanel( {
			eventQuery: 'past',
			metadata: { name: 'Upcoming Events', bindings },
		} );

		expect( metadataUpdates() ).toEqual( [
			{ bindings, name: 'Past Events' },
		] );
		// A rename follows the author's change, so it stays an edit.
		expect( mockMarkNextChangeAsNotPersistent ).not.toHaveBeenCalled();
	} );

	it( 'replaces a name generated with the fallback label', () => {
		// An earlier version named the loop with the fallback label when the
		// post type had not loaded yet.
		usePostTypeLabel.mockImplementation( ( key, postType, fallback ) =>
			'production' === postType ? 'Productions' : fallback,
		);

		renderPanel( {
			postType: 'production',
			metadata: { name: 'Upcoming Events' },
		} );

		expect( metadataUpdates() ).toEqual( [
			{ name: 'Upcoming Productions' },
		] );
	} );

	it( 'renames a name generated for the previous post type after a switch', () => {
		const labels = { production: 'Productions', show: 'Shows' };
		usePostTypeLabel.mockImplementation(
			( key, postType, fallback ) => labels[ postType ] ?? fallback,
		);

		const { rerender } = renderPanel( {
			postType: 'production',
			metadata: { name: 'Upcoming Productions' },
		} );

		expect( metadataUpdates() ).toEqual( [] );

		rerender(
			<EventQueryControlsPanel
				clientId="abc"
				attributes={ {
					metadata: { name: 'Upcoming Productions' },
					query: {
						postType: 'show',
						gatherpress_event_query: 'upcoming',
						orderBy: 'datetime',
						inherit: false,
					},
				} }
			/>,
		);

		expect( metadataUpdates() ).toEqual( [ { name: 'Upcoming Shows' } ] );
	} );

	it( 'clears a generated name when the post type does not support event dates', () => {
		usePostTypeSupports.mockReturnValue( false );

		renderPanel( {
			postType: 'post',
			metadata: { name: 'Upcoming Events', bindings },
		} );

		// The name key is dropped, as core's rename modal does, not saved
		// as an empty string.
		expect( metadataUpdates() ).toEqual( [ { bindings } ] );
		expect( mockMarkNextChangeAsNotPersistent ).not.toHaveBeenCalled();
	} );

	it( 'removes the metadata when the cleared name was its only key', () => {
		usePostTypeSupports.mockReturnValue( false );

		renderPanel( {
			postType: 'post',
			metadata: { name: 'Upcoming Events' },
		} );

		expect( metadataUpdates() ).toEqual( [ undefined ] );
	} );

	it( 'does not rewrite an empty name that an earlier version saved', () => {
		// Before #2495 the cleared name was saved as "". Opening such a
		// block must not change it.
		usePostTypeSupports.mockReturnValue( false );

		renderPanel( { postType: 'post', metadata: { name: '' } } );

		expect( metadataUpdates() ).toEqual( [] );
	} );

	it( 'keeps an author name when the post type does not support event dates', () => {
		usePostTypeSupports.mockReturnValue( false );

		renderPanel( { postType: 'post', metadata: { name: 'Latest news' } } );

		expect( metadataUpdates() ).toEqual( [] );
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
			/>,
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
			'advanced-query-loop',
		);

		expect( setAttributes ).not.toHaveBeenCalled();
	} );
} );
