/**
 * External dependencies
 */
import {
	describe,
	expect,
	it,
	jest,
	beforeEach,
	afterEach,
} from '@jest/globals';
import { render, screen } from '@testing-library/react';
import '@testing-library/jest-dom';

jest.mock( '@wordpress/components', () => ( {
	RangeControl: ( { label } ) => (
		<div data-testid="range-control">{ label }</div>
	),
	SelectControl: ( { label } ) => (
		<div data-testid="select-control">{ label }</div>
	),
	ToggleControl: ( { label, help } ) => (
		<div data-testid="toggle-control">
			<span>{ label }</span>
			{ help && <span data-testid="toggle-help">{ help }</span> }
		</div>
	),
	__experimentalToggleGroupControl: ( { label, children } ) => (
		<div data-testid="toggle-group-control">
			{ label }
			{ children }
		</div>
	),
	__experimentalToggleGroupControlOption: ( { label } ) => (
		<div data-testid="toggle-group-option">{ label }</div>
	),
} ) );

jest.mock( '@wordpress/data', () => ( {
	useSelect: jest.fn( () => ( { id: 1 } ) ),
} ) );

jest.mock( '@wordpress/i18n', () => ( {
	__: ( text ) => text,
	_x: ( text ) => text,
	sprintf: ( fmt, ...args ) => {
		// Mirror @wordpress/i18n: support both positional (%1$s) and
		// sequential (%s) placeholders so help copy interpolates correctly.
		let sequential = 0;
		return fmt.replace( /%(\d+)\$s|%s/g, ( match, position ) =>
			position ? args[ position - 1 ] : args[ sequential++ ],
		);
	},
} ) );

// The slot wrapper invokes its render-prop child immediately so we can assert
// on what the fill renders without setting up a real Slot consumer.
let mockQueryControlsProps = {
	context: {
		postType: 'gatherpress_event',
	},
	attributes: {
		query: {
			postType: 'gatherpress_event',
			inherit: false,
		},
	},
	setAttributes: jest.fn(),
};

jest.mock( '@src/variations/core/query/slots/query-controls', () => ( {
	__esModule: true,
	default: ( { children } ) => (
		<div data-testid="query-controls-fill">
			{ children( mockQueryControlsProps ) }
		</div>
	),
} ) );

jest.mock( '@src/variations/core/query/slots/inherited-query-controls', () => ( {
	__esModule: true,
	default: ( { children } ) => (
		<div data-testid="inherited-fill">
			{ children( {
				attributes: { query: { inherit: true } },
				setAttributes: jest.fn(),
			} ) }
		</div>
	),
} ) );

jest.mock( '@src/helpers/event', () => ( {
	isEventPostType: jest.fn(),
	isPostTypeSupporting: jest.fn(),
} ) );

jest.mock( '@src/helpers/editor', () => ( {
	isInFSETemplate: jest.fn(),
	getPostTypeLabel: jest.fn( ( key, postType, fallback ) => fallback ),
	usePostTypeLabel: jest.fn( ( key, postType, fallback ) => fallback ),
} ) );

/**
 * WordPress dependencies
 */
import { useSelect } from '@wordpress/data';
import { memo } from '@wordpress/element';
import { addFilter, removeFilter } from '@wordpress/hooks';
import { isEventPostType, isPostTypeSupporting } from '@src/helpers/event';
import { isInFSETemplate } from '@src/helpers/editor';

/**
 * Internal dependencies
 */
import {
	EventQueryControlsSlotFill,
	EventInheritedQueryControlsSlotFill,
	ShadowSourceFilterControls,
} from '@src/variations/core/query/components';

const venueToggleLabel = 'Filter by Current Venue';
const excludeToggleLabel = 'Exclude Current Event';
const venueHelp =
	'When placed inside Venue context, only shows Events tied to that Venue.';
const templateHelp =
	'The filter only takes effect when this template renders on a shadow-source page (venue, tour, production, etc.).';

describe( 'EventQueryControlsSlotFill', () => {
	beforeEach( () => {
		isEventPostType.mockReset();
		isPostTypeSupporting.mockReset();
		isInFSETemplate.mockReset();
	} );

	it( 'hides the venue filter toggle on a regular non-venue, non-template host', () => {
		isEventPostType.mockReturnValue( true );
		isPostTypeSupporting.mockReturnValue( false );
		isInFSETemplate.mockReturnValue( false );

		render( <EventQueryControlsSlotFill /> );

		expect(
			screen.queryByText( venueToggleLabel ),
		).not.toBeInTheDocument();
		expect( isPostTypeSupporting ).toHaveBeenCalledWith(
			'gatherpress-shadow-source',
			'gatherpress_event',
		);
	} );

	it( 'shows the venue filter toggle with venue copy when host is a venue post', () => {
		mockQueryControlsProps = {
			...mockQueryControlsProps,
			context: {
				postType: 'gatherpress_venue',
			},
			attributes: {
				query: {
					postType: 'gatherpress_event',
					inherit: false,
				},
			},
		};

		isEventPostType.mockReturnValue( false );
		isPostTypeSupporting.mockReturnValue( true );
		isInFSETemplate.mockReturnValue( false );

		render( <EventQueryControlsSlotFill /> );

		expect( screen.getByText( venueToggleLabel ) ).toBeInTheDocument();
		expect( screen.getByText( venueHelp ) ).toBeInTheDocument();
		expect( screen.queryByText( templateHelp ) ).not.toBeInTheDocument();
	} );

	it( 'shows the venue filter toggle with template copy on a template / template part', () => {
		isEventPostType.mockReturnValue( false );
		isPostTypeSupporting.mockReturnValue( false );
		isInFSETemplate.mockReturnValue( true );

		render( <EventQueryControlsSlotFill /> );

		expect( screen.getByText( venueToggleLabel ) ).toBeInTheDocument();
		expect( screen.getByText( templateHelp ) ).toBeInTheDocument();
		expect( screen.queryByText( venueHelp ) ).not.toBeInTheDocument();
	} );

	it( 'still gates the exclude-current-event toggle on the existing isEventPostType check', () => {
		isEventPostType.mockReturnValue( false );
		isPostTypeSupporting.mockReturnValue( true );
		isInFSETemplate.mockReturnValue( false );

		render( <EventQueryControlsSlotFill /> );

		expect(
			screen.queryByText( excludeToggleLabel ),
		).not.toBeInTheDocument();
	} );

	it( 'shows the exclude-current-event toggle when the host is an event post type', () => {
		mockQueryControlsProps = {
			...mockQueryControlsProps,
			context: {
				postType: 'gatherpress_event',
			},
			attributes: {
				query: {
					postType: 'gatherpress_event',
					inherit: false,
				},
			},
		};

		isEventPostType.mockReturnValue( true );
		isPostTypeSupporting.mockReturnValue( true );
		isInFSETemplate.mockReturnValue( false );

		render( <EventQueryControlsSlotFill /> );

		expect( screen.getByText( excludeToggleLabel ) ).toBeInTheDocument();
	} );

	it( 'hides the exclude-current-event toggle when the query post type differs from the host post type', () => {
		mockQueryControlsProps = {
			...mockQueryControlsProps,
			context: {
				postType: 'gatherpress_event',
			},
			attributes: {
				query: {
					postType: 'post',
					inherit: false,
				},
			},
		};

		isEventPostType.mockReturnValue( true );
		isPostTypeSupporting.mockReturnValue( true );
		isInFSETemplate.mockReturnValue( false );

		render( <EventQueryControlsSlotFill /> );

		expect(
			screen.queryByText( excludeToggleLabel ),
		).not.toBeInTheDocument();
	} );
} );

describe( 'gatherpress.eventQueryControls', () => {
	const NAMESPACE = 'test/query-controls';

	beforeEach( () => {
		mockQueryControlsProps = {
			...mockQueryControlsProps,
			context: { postType: 'gatherpress_event' },
			attributes: {
				query: { postType: 'gatherpress_event', inherit: false },
			},
		};

		isEventPostType.mockReturnValue( true );
		isPostTypeSupporting.mockReturnValue( false );
		isInFSETemplate.mockReturnValue( false );
	} );

	afterEach( () => {
		removeFilter( 'gatherpress.eventQueryControls', NAMESPACE );
		removeFilter( 'gatherpress.eventInheritedQueryControls', NAMESPACE );
	} );

	it( 'hands the filter every control that would render, in order', () => {
		let seen = [];

		addFilter(
			'gatherpress.eventQueryControls',
			NAMESPACE,
			( controls ) => {
				seen = controls.map( ( { name } ) => name );
				return controls;
			},
		);

		render( <EventQueryControlsSlotFill /> );

		expect( seen ).toEqual( [
			'listType',
			'includeUnfinished',
			'exclude',
			'count',
			'offset',
			'order',
		] );
	} );

	it( 'lets a filter drop a control', () => {
		addFilter( 'gatherpress.eventQueryControls', NAMESPACE, ( controls ) =>
			controls.filter( ( { name } ) => 'offset' !== name ),
		);

		render( <EventQueryControlsSlotFill /> );

		expect( screen.queryByText( 'Event Offset' ) ).not.toBeInTheDocument();
		expect( screen.getByText( 'Events Per Page' ) ).toBeInTheDocument();
	} );

	it( 'lets a filter add a control of its own', () => {
		addFilter( 'gatherpress.eventQueryControls', NAMESPACE, ( controls ) => [
			...controls,
			{
				name: 'test/extra',
				Component: () => <div>Extra Control</div>,
			},
		] );

		render( <EventQueryControlsSlotFill /> );

		expect( screen.getByText( 'Extra Control' ) ).toBeInTheDocument();
	} );

	it( 'passes the block edit props through to a filtered-in control', () => {
		let received = null;

		addFilter( 'gatherpress.eventQueryControls', NAMESPACE, ( controls ) => [
			{
				name: 'test/probe',
				Component: ( props ) => {
					received = props;
					return null;
				},
				props: { extra: 'from-entry' },
			},
			...controls,
		] );

		render( <EventQueryControlsSlotFill /> );

		expect( received.attributes.query.postType ).toBe( 'gatherpress_event' );
		expect( received.extra ).toBe( 'from-entry' );
	} );

	it( 'survives a filter returning entries it cannot render', () => {
		addFilter( 'gatherpress.eventQueryControls', NAMESPACE, () => [
			null,
			{ name: 'no-component' },
			{ Component: () => <div>No Name</div> },
			{ name: 'test/ok', Component: () => <div>Still Here</div> },
		] );

		expect( () => render( <EventQueryControlsSlotFill /> ) ).not.toThrow();
		expect( screen.getByText( 'Still Here' ) ).toBeInTheDocument();
		expect( screen.queryByText( 'No Name' ) ).not.toBeInTheDocument();
	} );

	it( 'survives a filter returning something that is not an array', () => {
		addFilter( 'gatherpress.eventQueryControls', NAMESPACE, () => null );

		expect( () => render( <EventQueryControlsSlotFill /> ) ).not.toThrow();
		expect( screen.queryByText( 'Event Offset' ) ).not.toBeInTheDocument();
	} );

	it( 'filters the inherited panel through its own hook', () => {
		let seen = [];

		addFilter(
			'gatherpress.eventInheritedQueryControls',
			NAMESPACE,
			( controls ) => {
				seen = controls.map( ( { name } ) => name );
				return controls.filter( ( { name } ) => 'order' !== name );
			},
		);

		render( <EventInheritedQueryControlsSlotFill /> );

		expect( seen ).toEqual( [ 'listType', 'includeUnfinished', 'order' ] );
		expect( screen.queryByText( 'Order Events by' ) ).not.toBeInTheDocument();
	} );

	it( 'leaves the main panel alone when only the inherited hook is filtered', () => {
		addFilter(
			'gatherpress.eventInheritedQueryControls',
			NAMESPACE,
			() => [],
		);

		render( <EventQueryControlsSlotFill /> );

		expect( screen.getByText( 'Event Offset' ) ).toBeInTheDocument();
	} );
} );

describe( 'gatherpress.eventQueryControls component validation', () => {
	const NAMESPACE = 'test/component-validation';

	beforeEach( () => {
		mockQueryControlsProps = {
			...mockQueryControlsProps,
			context: { postType: 'gatherpress_event' },
			attributes: {
				query: { postType: 'gatherpress_event', inherit: false },
			},
		};

		isEventPostType.mockReturnValue( true );
		isPostTypeSupporting.mockReturnValue( false );
		isInFSETemplate.mockReturnValue( false );
	} );

	afterEach( () => {
		removeFilter( 'gatherpress.eventQueryControls', NAMESPACE );
	} );

	it( 'skips an entry whose Component is not something React can render', () => {
		addFilter( 'gatherpress.eventQueryControls', NAMESPACE, () => [
			{ name: 'number', Component: 42 },
			{ name: 'string', Component: 'not a component' },
			{ name: 'plain-object', Component: {} },
			{ name: 'test/ok', Component: () => <div>Still Here</div> },
		] );

		expect( () => render( <EventQueryControlsSlotFill /> ) ).not.toThrow();
		expect( screen.getByText( 'Still Here' ) ).toBeInTheDocument();
	} );

	it( 'still renders a memoized component, which is an object rather than a function', () => {
		const Memoized = memo( () => <div>Memoized Control</div> );

		addFilter( 'gatherpress.eventQueryControls', NAMESPACE, ( controls ) => [
			...controls,
			{ name: 'test/memo', Component: Memoized },
		] );

		render( <EventQueryControlsSlotFill /> );

		expect( screen.getByText( 'Memoized Control' ) ).toBeInTheDocument();
	} );
} );

describe( 'shadow-source sync', () => {
	const NAMESPACE = 'test/shadow-source-sync';
	const HOST_ID = 42;
	const HOST_TYPE = 'gatherpress_venue';

	// A shadow-source host querying events, with the filter switched on but
	// the source IDs never written: the case the sync exists for.
	const staleQuery = {
		postType: 'gatherpress_event',
		inherit: false,
		shadow_filter: 1,
		gatherpress_shadow_source_post_id: null,
		gatherpress_shadow_source_post_type: null,
	};

	const fakeSelect = ( store ) => {
		if ( 'core/editor' === store ) {
			return {
				getCurrentPostId: () => HOST_ID,
				getCurrentPostType: () => HOST_TYPE,
				getCurrentPost: () => ( { id: HOST_ID } ),
			};
		}

		if ( 'core' === store ) {
			return {
				getPostType: () => ( {
					supports: { 'gatherpress-shadow-source': true },
				} ),
			};
		}

		return {};
	};

	let setAttributes;

	beforeEach( () => {
		setAttributes = jest.fn();

		mockQueryControlsProps = {
			context: { postType: HOST_TYPE, postId: HOST_ID },
			attributes: { query: { ...staleQuery } },
			setAttributes,
		};

		useSelect.mockImplementation( ( callback ) => callback( fakeSelect ) );
		isEventPostType.mockReturnValue( false );
		isPostTypeSupporting.mockReturnValue( true );
		isInFSETemplate.mockReturnValue( false );
	} );

	afterEach( () => {
		removeFilter( 'gatherpress.eventQueryControls', NAMESPACE );
		useSelect.mockImplementation( () => ( { id: 1 } ) );
	} );

	const backfillCalls = () =>
		setAttributes.mock.calls.filter(
			( [ next ] ) =>
				HOST_ID === next?.query?.gatherpress_shadow_source_post_id &&
				HOST_TYPE === next?.query?.gatherpress_shadow_source_post_type,
		);

	it( 'backfills the source when the toggle is in the panel', () => {
		render( <EventQueryControlsSlotFill /> );

		expect( backfillCalls() ).toHaveLength( 1 );
	} );

	it( 'still backfills when a plugin removes the toggle', () => {
		addFilter( 'gatherpress.eventQueryControls', NAMESPACE, ( controls ) =>
			controls.filter( ( { name } ) => 'shadowSourceFilter' !== name ),
		);

		render( <EventQueryControlsSlotFill /> );

		expect(
			screen.queryByText( 'Filter by Current Venue' ),
		).not.toBeInTheDocument();
		expect( backfillCalls() ).toHaveLength( 1 );
	} );

	it( 'backfills when the published toggle is rendered in a panel of its own', () => {
		render(
			<ShadowSourceFilterControls
				context={ { postType: HOST_TYPE, postId: HOST_ID } }
				attributes={ { query: { ...staleQuery } } }
				setAttributes={ setAttributes }
			/>,
		);

		expect( backfillCalls() ).toHaveLength( 1 );
	} );
} );
