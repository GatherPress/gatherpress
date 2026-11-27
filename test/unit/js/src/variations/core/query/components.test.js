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
import { fireEvent, render, screen } from '@testing-library/react';
import '@testing-library/jest-dom';

jest.mock( '@wordpress/components', () => ( {
	RangeControl: ( { label } ) => <div data-testid="range-control">{ label }</div>,
	SelectControl: ( { label } ) => (
		<div data-testid="select-control">{ label }</div>
	),
	ToggleControl: ( { label, help, checked, onChange } ) => (
		<div data-testid="toggle-control">
			<span>{ label }</span>
			{ help && <span data-testid="toggle-help">{ help }</span> }
			<button
				type="button"
				aria-label={ label }
				aria-pressed={ checked ? 'true' : 'false' }
				onClick={ () => onChange( ! checked ) }
			/>
		</div>
	),
	__experimentalToggleGroupControl: ( {
		label,
		help,
		value,
		onChange,
		children,
	} ) => {
		const { Children, cloneElement } = jest.requireActual( 'react' );

		return (
			<div data-testid="toggle-group-control">
				{ label }
				{ help && <span data-testid="toggle-group-help">{ help }</span> }
				{ Children.map( children, ( child ) =>
					cloneElement( child, { selected: value, onSelect: onChange } ),
				) }
			</div>
		);
	},
	__experimentalToggleGroupControlOption: ( {
		label,
		value,
		selected,
		onSelect,
	} ) => (
		<button
			type="button"
			data-testid="toggle-group-option"
			aria-pressed={ selected === value ? 'true' : 'false' }
			onClick={ () => onSelect?.( value ) }
		>
			{ label }
		</button>
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
	usePostTypeSupports: jest.fn(),
	useHasEventActivityFilterSupport: jest.fn(),
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
import {
	isEventPostType,
	useHasEventActivityFilterSupport,
	usePostTypeSupports,
} from '@src/helpers/event';
import { isInFSETemplate, usePostTypeLabel } from '@src/helpers/editor';

/**
 * Internal dependencies
 */
import {
	EventQueryControlsSlotFill,
	EventInheritedQueryControlsSlotFill,
	HasEventsFilterControls,
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
		// Reset the shared fill props so each test starts from the
		// documented default (host === queried === gatherpress_event). Tests
		// that exercise a different host/queried pair set these locally.
		mockQueryControlsProps = {
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
		isEventPostType.mockReset();
		usePostTypeSupports.mockReset();
		useHasEventActivityFilterSupport.mockReset();
		isInFSETemplate.mockReset();
	} );

	it( 'hides the venue filter toggle on a regular non-venue, non-template host', () => {
		isEventPostType.mockReturnValue( true );
		usePostTypeSupports.mockReturnValue( false );
		isInFSETemplate.mockReturnValue( false );

		render( <EventQueryControlsSlotFill /> );

		expect( screen.queryByText( venueToggleLabel ) ).not.toBeInTheDocument();
		expect( usePostTypeSupports ).toHaveBeenCalledWith(
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
		usePostTypeSupports.mockReturnValue( true );
		isInFSETemplate.mockReturnValue( false );

		render( <EventQueryControlsSlotFill /> );

		expect( screen.getByText( venueToggleLabel ) ).toBeInTheDocument();
		expect( screen.getByText( venueHelp ) ).toBeInTheDocument();
		expect( screen.queryByText( templateHelp ) ).not.toBeInTheDocument();
	} );

	it( 'shows the venue filter toggle with template copy on a template / template part', () => {
		isEventPostType.mockReturnValue( false );
		usePostTypeSupports.mockReturnValue( false );
		isInFSETemplate.mockReturnValue( true );

		render( <EventQueryControlsSlotFill /> );

		expect( screen.getByText( venueToggleLabel ) ).toBeInTheDocument();
		expect( screen.getByText( templateHelp ) ).toBeInTheDocument();
		expect( screen.queryByText( venueHelp ) ).not.toBeInTheDocument();
	} );

	it( 'still gates the exclude-current-event toggle on host context (only when the host is an event post type)', () => {
		// The host (`gatherpress_event` in the default mockQueryControlsProps)
		// is a real event post type and the queried type matches, so
		// exclude-current-event must appear. The previous version of this
		// test used isEventPostType(false) to exercise the "host is not an
		// event" path, but the actual semantic for that path is now the
		// queried-type support check
		// (`usePostTypeSupports('gatherpress-event-date', queryPostType)`).
		// Keep the assertion focused on the host-must-equal-queried rule
		// here; the new "host is not an event" branch is covered separately
		// by the "hides the exclude-current-event toggle when the host is
		// not an event post type" test below.
		isEventPostType.mockReturnValue( true );
		usePostTypeSupports.mockReturnValue( true );
		useHasEventActivityFilterSupport.mockReturnValue( false );
		isInFSETemplate.mockReturnValue( false );

		render( <EventQueryControlsSlotFill /> );

		expect( screen.getByText( excludeToggleLabel ) ).toBeInTheDocument();
	} );

	it( 'hides the exclude-current-event toggle when the host is not an event post type', () => {
		// The queried type is `gatherpress_event` (an event post type) so the
		// event controls render, but the host is a page — exclude-current
		// must stay hidden because it requires the host to be an event post
		// type too (host === queried).
		mockQueryControlsProps = {
			...mockQueryControlsProps,
			context: {
				postType: 'page',
			},
			attributes: {
				query: {
					postType: 'gatherpress_event',
					inherit: false,
				},
			},
		};

		isEventPostType.mockReturnValue( false );
		usePostTypeSupports.mockImplementation(
			( support, postType ) =>
				'gatherpress-event-date' === support &&
				'gatherpress_event' === postType,
		);
		useHasEventActivityFilterSupport.mockReturnValue( false );
		isInFSETemplate.mockReturnValue( false );

		render( <EventQueryControlsSlotFill /> );

		expect( screen.queryByText( excludeToggleLabel ) ).not.toBeInTheDocument();
	} );

	it( 'renders the event controls when the queried post type is an event but the host is a page', () => {
		// Regression test for carstingaxion's Sept 4 report: the panel
		// "Event Query settings" was empty when querying the Events post
		// type from a non-event host. The event controls (List Type,
		// Include Unfinished, Per Page, Offset, Order) belong to whatever
		// post type the Query Loop is listing, not to the host.
		mockQueryControlsProps = {
			...mockQueryControlsProps,
			context: {
				postType: 'page',
			},
			attributes: {
				query: {
					postType: 'gatherpress_event',
					inherit: false,
				},
			},
		};

		isEventPostType.mockReturnValue( false );
		usePostTypeSupports.mockImplementation(
			( support, postType ) =>
				'gatherpress-event-date' === support &&
				'gatherpress_event' === postType,
		);
		useHasEventActivityFilterSupport.mockReturnValue( false );
		isInFSETemplate.mockReturnValue( false );

		render( <EventQueryControlsSlotFill /> );

		expect( screen.getByText( 'Event List Type' ) ).toBeInTheDocument();
		expect(
			screen.getByText( 'Include Unfinished Events' ),
		).toBeInTheDocument();
		expect( screen.getByText( 'Events Per Page' ) ).toBeInTheDocument();
		expect( screen.getByText( 'Event Offset' ) ).toBeInTheDocument();
		expect( screen.getByText( 'Order Events by' ) ).toBeInTheDocument();
	} );

	it( 'hides the event controls when the queried post type is a shadow source (not an event)', () => {
		// When the query lists shadow-source posts (e.g. venues) the event
		// controls are not the right thing to show. Only the activity
		// filter and the host-equal-queried exclude toggle apply, both of
		// which are hidden in this scenario.
		mockQueryControlsProps = {
			...mockQueryControlsProps,
			context: {
				postType: 'page',
			},
			attributes: {
				query: {
					postType: 'gatherpress_venue',
					inherit: false,
				},
			},
		};

		isEventPostType.mockReturnValue( false );
		usePostTypeSupports.mockImplementation(
			( support, postType ) =>
				'gatherpress-shadow-source' === support &&
				'gatherpress_venue' === postType,
		);
		useHasEventActivityFilterSupport.mockImplementation(
			( postType ) => 'gatherpress_venue' === postType,
		);
		isInFSETemplate.mockReturnValue( false );

		render( <EventQueryControlsSlotFill /> );

		expect( screen.queryByText( 'Event List Type' ) ).not.toBeInTheDocument();
		expect(
			screen.queryByText( 'Include Unfinished Events' ),
		).not.toBeInTheDocument();
		expect( screen.queryByText( 'Events Per Page' ) ).not.toBeInTheDocument();
		expect( screen.queryByText( 'Event Offset' ) ).not.toBeInTheDocument();
		expect( screen.queryByText( 'Order Events by' ) ).not.toBeInTheDocument();
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
		usePostTypeSupports.mockReturnValue( true );
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
		usePostTypeSupports.mockReturnValue( true );
		isInFSETemplate.mockReturnValue( false );

		render( <EventQueryControlsSlotFill /> );

		expect( screen.queryByText( excludeToggleLabel ) ).not.toBeInTheDocument();
	} );

	it( 'shows the event-activity filter when the queried type is a shadow source and differs from the host', () => {
		mockQueryControlsProps = {
			...mockQueryControlsProps,
			context: {
				postType: 'gatherpress_event',
			},
			attributes: {
				query: {
					postType: 'gatherpress_venue',
					inherit: false,
				},
			},
		};

		isEventPostType.mockReturnValue( true );
		usePostTypeSupports.mockImplementation(
			( support, postType ) =>
				'gatherpress-shadow-source' === support &&
				'gatherpress_venue' === postType,
		);
		useHasEventActivityFilterSupport.mockImplementation(
			( postType ) => 'gatherpress_venue' === postType,
		);
		isInFSETemplate.mockReturnValue( false );

		render( <EventQueryControlsSlotFill /> );

		expect(
			screen.getByText( 'Filter by event activity' ),
		).toBeInTheDocument();
		expect( screen.queryByText( 'Event activity' ) ).not.toBeInTheDocument();
	} );

	it( 'hides the event-activity filter when the queried type is not a shadow source', () => {
		mockQueryControlsProps = {
			...mockQueryControlsProps,
			context: {
				postType: 'page',
			},
			attributes: {
				query: {
					postType: 'gatherpress_event',
					inherit: false,
				},
			},
		};

		isEventPostType.mockReturnValue( false );
		usePostTypeSupports.mockReturnValue( false );
		useHasEventActivityFilterSupport.mockReturnValue( true );
		isInFSETemplate.mockReturnValue( false );

		render( <EventQueryControlsSlotFill /> );

		expect(
			screen.queryByText( 'Filter by event activity' ),
		).not.toBeInTheDocument();
	} );

	it( 'hides the event-activity filter when the queried shadow source does not wire its taxonomy onto events', () => {
		mockQueryControlsProps = {
			...mockQueryControlsProps,
			context: {
				postType: 'gatherpress_event',
			},
			attributes: {
				query: {
					postType: 'gatherpress_venue',
					inherit: false,
				},
			},
		};

		isEventPostType.mockReturnValue( true );
		usePostTypeSupports.mockImplementation(
			( support, postType ) =>
				'gatherpress-shadow-source' === support &&
				'gatherpress_venue' === postType,
		);
		useHasEventActivityFilterSupport.mockReturnValue( false );
		isInFSETemplate.mockReturnValue( false );

		render( <EventQueryControlsSlotFill /> );

		expect(
			screen.queryByText( 'Filter by event activity' ),
		).not.toBeInTheDocument();
		expect( useHasEventActivityFilterSupport ).toHaveBeenCalledWith(
			'gatherpress_venue',
		);
	} );

	it( 'shows the event-activity filter on a template when the queried type is a wired shadow source', () => {
		mockQueryControlsProps = {
			...mockQueryControlsProps,
			attributes: {
				query: {
					postType: 'gatherpress_venue',
					inherit: false,
				},
			},
		};

		isEventPostType.mockReturnValue( false );
		usePostTypeSupports.mockImplementation(
			( support, postType ) =>
				'gatherpress-shadow-source' === support &&
				'gatherpress_venue' === postType,
		);
		useHasEventActivityFilterSupport.mockImplementation(
			( postType ) => 'gatherpress_venue' === postType,
		);
		isInFSETemplate.mockReturnValue( true );

		render( <EventQueryControlsSlotFill /> );

		expect(
			screen.getByText( 'Filter by event activity' ),
		).toBeInTheDocument();
	} );

	it( 'hides the event-activity filter on a template when the queried type is an unwired shadow source', () => {
		mockQueryControlsProps = {
			...mockQueryControlsProps,
			attributes: {
				query: {
					postType: 'gatherpress_venue',
					inherit: false,
				},
			},
		};

		isEventPostType.mockReturnValue( false );
		usePostTypeSupports.mockImplementation(
			( support, postType ) =>
				'gatherpress-shadow-source' === support &&
				'gatherpress_venue' === postType,
		);
		useHasEventActivityFilterSupport.mockReturnValue( false );
		isInFSETemplate.mockReturnValue( true );

		render( <EventQueryControlsSlotFill /> );

		expect(
			screen.queryByText( 'Filter by event activity' ),
		).not.toBeInTheDocument();
	} );

	it( 'shows the event-activity filter when the queried type matches the host type', () => {
		mockQueryControlsProps = {
			...mockQueryControlsProps,
			context: {
				postType: 'gatherpress_venue',
			},
			attributes: {
				query: {
					postType: 'gatherpress_venue',
					inherit: false,
				},
			},
		};

		isEventPostType.mockReturnValue( false );
		usePostTypeSupports.mockReturnValue( true );
		useHasEventActivityFilterSupport.mockReturnValue( true );
		isInFSETemplate.mockReturnValue( false );

		render( <EventQueryControlsSlotFill /> );

		expect(
			screen.getByText( 'Filter by event activity' ),
		).toBeInTheDocument();
	} );

	it( 'shows the event-activity filter when the host has no post type context', () => {
		mockQueryControlsProps = {
			...mockQueryControlsProps,
			context: {},
			attributes: {
				query: {
					postType: 'gatherpress_venue',
					inherit: false,
				},
			},
		};

		isEventPostType.mockReturnValue( false );
		usePostTypeSupports.mockImplementation(
			( support, postType ) =>
				'gatherpress-shadow-source' === support &&
				'gatherpress_venue' === postType,
		);
		useHasEventActivityFilterSupport.mockImplementation(
			( postType ) => 'gatherpress_venue' === postType,
		);
		isInFSETemplate.mockReturnValue( false );

		render( <EventQueryControlsSlotFill /> );

		expect(
			screen.getByText( 'Filter by event activity' ),
		).toBeInTheDocument();
	} );

	it( 'hides the event controls when the query has no post type set', () => {
		// The host is an event post type, but `usePostTypeSupports` must not
		// fall back to it for an unset queried type — an untyped query gets
		// no event controls even though the host would satisfy the check.
		mockQueryControlsProps = {
			...mockQueryControlsProps,
			context: {
				postType: 'gatherpress_venue',
			},
			attributes: {
				query: {
					inherit: false,
				},
			},
		};

		isEventPostType.mockReturnValue( false );
		usePostTypeSupports.mockImplementation(
			( support, postType ) =>
				'gatherpress-event-date' === support &&
				'gatherpress_venue' === postType,
		);
		useHasEventActivityFilterSupport.mockReturnValue( true );
		isInFSETemplate.mockReturnValue( false );

		render( <EventQueryControlsSlotFill /> );

		expect(
			screen.queryByText( 'Filter by event activity' ),
		).not.toBeInTheDocument();
		expect( screen.queryByText( 'Event List Type' ) ).not.toBeInTheDocument();
		expect( screen.queryByText( 'Events Per Page' ) ).not.toBeInTheDocument();
	} );
} );

describe( 'HasEventsFilterControls', () => {
	beforeEach( () => {
		usePostTypeLabel.mockReset();
		usePostTypeLabel.mockImplementation(
			( key, postType, fallback ) => fallback,
		);
	} );

	const activityLabel = 'Filter by event activity';
	const activityTypeLabel = 'Event activity';
	const activityHelp =
		'Only shows Posts that have upcoming or past events attached.';
	const upcomingHelp =
		'Only shows Posts that have at least one upcoming event.';
	const pastHelp = 'Only shows Posts that have at least one past event.';

	it( 'renders the activity toggle and hides the sub-filter when the filter is off', () => {
		const setAttributes = jest.fn();

		render(
			<HasEventsFilterControls
				attributes={ {
					query: {
						postType: 'gatherpress_venue',
					},
				} }
				setAttributes={ setAttributes }
			/>,
		);

		expect( screen.getByText( activityLabel ) ).toBeInTheDocument();
		expect( screen.getByText( activityHelp ) ).toBeInTheDocument();
		expect(
			screen.queryByText( activityTypeLabel ),
		).not.toBeInTheDocument();
	} );

	it( 'uses selected post type plural label in activity help', () => {
		usePostTypeLabel.mockReturnValue( 'Productions' );

		render(
			<HasEventsFilterControls
				attributes={ {
					query: { postType: 'production', gatherpress_has_events_filter: 1 },
				} }
				setAttributes={ jest.fn() }
			/>,
		);

		expect(
			screen.getByText(
				'Only shows Productions that have upcoming or past events attached.',
			),
		).toBeInTheDocument();
		expect(
			screen.getByText(
				'Only shows Productions that have at least one upcoming event.',
			),
		).toBeInTheDocument();
	} );

	it( 'writes gatherpress_has_events_filter and defaults gatherpress_upcoming_events_only to on', () => {
		const setAttributes = jest.fn();
		const query = {
			postType: 'gatherpress_venue',
		};

		render(
			<HasEventsFilterControls
				attributes={ { query } }
				setAttributes={ setAttributes }
			/>,
		);

		fireEvent.click( screen.getByRole( 'button', { name: activityLabel } ) );

		expect( setAttributes ).toHaveBeenCalledWith( {
			query: {
				postType: 'gatherpress_venue',
				gatherpress_has_events_filter: 1,
				gatherpress_upcoming_events_only: 1,
			},
		} );
	} );

	it( 'shows the upcoming sub-filter by default when the activity filter is on', () => {
		render(
			<HasEventsFilterControls
				attributes={ {
					query: {
						postType: 'gatherpress_venue',
						gatherpress_has_events_filter: 1,
					},
				} }
				setAttributes={ jest.fn() }
			/>,
		);

		expect( screen.getByText( activityTypeLabel ) ).toBeInTheDocument();
		expect( screen.getByText( upcomingHelp ) ).toBeInTheDocument();
		expect(
			screen.getByRole( 'button', { name: 'Upcoming' } ),
		).toHaveAttribute( 'aria-pressed', 'true' );
		expect( screen.getByRole( 'button', { name: 'Past' } ) ).toHaveAttribute(
			'aria-pressed',
			'false',
		);
	} );

	it( 'selects Past when gatherpress_upcoming_events_only is off', () => {
		render(
			<HasEventsFilterControls
				attributes={ {
					query: {
						postType: 'gatherpress_venue',
						gatherpress_has_events_filter: 1,
						gatherpress_upcoming_events_only: 0,
					},
				} }
				setAttributes={ jest.fn() }
			/>,
		);

		expect( screen.getByText( pastHelp ) ).toBeInTheDocument();
		expect( screen.getByRole( 'button', { name: 'Past' } ) ).toHaveAttribute(
			'aria-pressed',
			'true',
		);
	} );

	it( 'writes gatherpress_upcoming_events_only to 0 when Past is picked', () => {
		const setAttributes = jest.fn();

		render(
			<HasEventsFilterControls
				attributes={ {
					query: {
						postType: 'gatherpress_venue',
						gatherpress_has_events_filter: 1,
						gatherpress_upcoming_events_only: 1,
					},
				} }
				setAttributes={ setAttributes }
			/>,
		);

		fireEvent.click( screen.getByRole( 'button', { name: 'Past' } ) );

		expect( setAttributes ).toHaveBeenCalledWith( {
			query: {
				postType: 'gatherpress_venue',
				gatherpress_has_events_filter: 1,
				gatherpress_upcoming_events_only: 0,
			},
		} );
	} );

	it( 'writes gatherpress_upcoming_events_only to 1 when Upcoming is picked', () => {
		const setAttributes = jest.fn();

		render(
			<HasEventsFilterControls
				attributes={ {
					query: {
						postType: 'gatherpress_venue',
						gatherpress_has_events_filter: 1,
						gatherpress_upcoming_events_only: 0,
					},
				} }
				setAttributes={ setAttributes }
			/>,
		);

		fireEvent.click( screen.getByRole( 'button', { name: 'Upcoming' } ) );

		expect( setAttributes ).toHaveBeenCalledWith( {
			query: {
				postType: 'gatherpress_venue',
				gatherpress_has_events_filter: 1,
				gatherpress_upcoming_events_only: 1,
			},
		} );
	} );

	it( 'clears gatherpress_has_events_filter and keeps the stored gatherpress_upcoming_events_only when turned off', () => {
		const setAttributes = jest.fn();

		render(
			<HasEventsFilterControls
				attributes={ {
					query: {
						postType: 'gatherpress_venue',
						gatherpress_has_events_filter: 1,
						gatherpress_upcoming_events_only: 0,
					},
				} }
				setAttributes={ setAttributes }
			/>,
		);

		fireEvent.click( screen.getByRole( 'button', { name: activityLabel } ) );

		expect( setAttributes ).toHaveBeenCalledWith( {
			query: {
				postType: 'gatherpress_venue',
				gatherpress_has_events_filter: 0,
				gatherpress_upcoming_events_only: 0,
			},
		} );
	} );

	it( 'clears gatherpress_has_events_filter without writing a sub-filter value when none was stored', () => {
		// Pins the other side of the ternary: with no stored
		// gatherpress_upcoming_events_only, turning the primary filter off writes
		// `undefined` rather than the default 1.
		const setAttributes = jest.fn();

		render(
			<HasEventsFilterControls
				attributes={ {
					query: {
						postType: 'gatherpress_venue',
						gatherpress_has_events_filter: 1,
					},
				} }
				setAttributes={ setAttributes }
			/>,
		);

		fireEvent.click( screen.getByRole( 'button', { name: activityLabel } ) );

		expect( setAttributes ).toHaveBeenCalledWith( {
			query: {
				postType: 'gatherpress_venue',
				gatherpress_has_events_filter: 0,
				gatherpress_upcoming_events_only: undefined,
			},
		} );
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

		usePostTypeSupports.mockImplementation(
			( support ) => 'gatherpress-event-date' === support,
		);
		useHasEventActivityFilterSupport.mockReturnValue( false );
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

	it( 'hands the filter the query block\'s own props, so it can scope itself', () => {
		let received = null;

		mockQueryControlsProps = {
			...mockQueryControlsProps,
			clientId: 'query-block-1',
		};

		addFilter(
			'gatherpress.eventQueryControls',
			NAMESPACE,
			( controls, props ) => {
				received = props;
				return controls;
			},
		);

		render( <EventQueryControlsSlotFill /> );

		expect( received.clientId ).toBe( 'query-block-1' );
		expect( received.attributes.query.postType ).toBe( 'gatherpress_event' );
	} );

	it( 'leaves the panel alone for a block the filter does not claim', () => {
		mockQueryControlsProps = {
			...mockQueryControlsProps,
			clientId: 'someone-elses-query',
		};

		addFilter( 'gatherpress.eventQueryControls', NAMESPACE, ( controls, { clientId } ) =>
			'my-calendar-query' === clientId
				? controls.filter( ( { name } ) => 'offset' !== name )
				: controls,
		);

		render( <EventQueryControlsSlotFill /> );

		expect( screen.getByText( 'Event Offset' ) ).toBeInTheDocument();
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

		usePostTypeSupports.mockImplementation(
			( support ) => 'gatherpress-event-date' === support,
		);
		useHasEventActivityFilterSupport.mockReturnValue( false );
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
		usePostTypeSupports.mockImplementation(
			( support, postType ) =>
				( 'gatherpress-event-date' === support &&
					'gatherpress_event' === postType ) ||
				( 'gatherpress-shadow-source' === support &&
					HOST_TYPE === postType ),
		);
		useHasEventActivityFilterSupport.mockReturnValue( false );
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
