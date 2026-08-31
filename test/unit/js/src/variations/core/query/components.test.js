/**
 * External dependencies
 */
import { describe, expect, it, jest, beforeEach } from '@jest/globals';
import { fireEvent, render, screen } from '@testing-library/react';
import '@testing-library/jest-dom';

jest.mock( '@wordpress/components', () => ( {
	RangeControl: ( { label } ) => (
		<div data-testid="range-control">{ label }</div>
	),
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
			position ? args[ position - 1 ] : args[ sequential++ ]
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
	hasEventActivityFilterSupport: jest.fn(),
} ) );

jest.mock( '@src/helpers/editor', () => ( {
	isInFSETemplate: jest.fn(),
	getPostTypeLabel: jest.fn( ( key, postType, fallback ) => fallback ),
	usePostTypeLabel: jest.fn( ( key, postType, fallback ) => fallback ),
} ) );

/**
 * WordPress dependencies
 */
import {
	hasEventActivityFilterSupport,
	isEventPostType,
	isPostTypeSupporting,
} from '@src/helpers/event';
import { isInFSETemplate } from '@src/helpers/editor';

/**
 * Internal dependencies
 */
import {
	EventQueryControlsSlotFill,
	HasEventsFilterControls,
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
		hasEventActivityFilterSupport.mockReset();
		isInFSETemplate.mockReset();
	} );

	it( 'hides the venue filter toggle on a regular non-venue, non-template host', () => {
		isEventPostType.mockReturnValue( true );
		isPostTypeSupporting.mockReturnValue( false );
		isInFSETemplate.mockReturnValue( false );

		render( <EventQueryControlsSlotFill /> );

		expect(
			screen.queryByText( venueToggleLabel )
		).not.toBeInTheDocument();
		expect( isPostTypeSupporting ).toHaveBeenCalledWith(
			'gatherpress-shadow-source',
			'gatherpress_event'
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
			screen.queryByText( excludeToggleLabel )
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
			screen.queryByText( excludeToggleLabel )
		).not.toBeInTheDocument();
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
		isPostTypeSupporting.mockImplementation(
			( support, postType ) =>
				'gatherpress-shadow-source' === support &&
				'gatherpress_venue' === postType
		);
		hasEventActivityFilterSupport.mockImplementation(
			( postType ) => 'gatherpress_venue' === postType
		);
		isInFSETemplate.mockReturnValue( false );

		render( <EventQueryControlsSlotFill /> );

		expect(
			screen.getByText( 'Filter by event activity' )
		).toBeInTheDocument();
		expect(
			screen.queryByText( 'Upcoming events only' )
		).not.toBeInTheDocument();
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
		isPostTypeSupporting.mockReturnValue( false );
		hasEventActivityFilterSupport.mockReturnValue( true );
		isInFSETemplate.mockReturnValue( false );

		render( <EventQueryControlsSlotFill /> );

		expect(
			screen.queryByText( 'Filter by event activity' )
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
		isPostTypeSupporting.mockImplementation(
			( support, postType ) =>
				'gatherpress-shadow-source' === support &&
				'gatherpress_venue' === postType
		);
		hasEventActivityFilterSupport.mockReturnValue( false );
		isInFSETemplate.mockReturnValue( false );

		render( <EventQueryControlsSlotFill /> );

		expect(
			screen.queryByText( 'Filter by event activity' )
		).not.toBeInTheDocument();
		expect( hasEventActivityFilterSupport ).toHaveBeenCalledWith(
			'gatherpress_venue'
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
		isPostTypeSupporting.mockImplementation(
			( support, postType ) =>
				'gatherpress-shadow-source' === support &&
				'gatherpress_venue' === postType
		);
		hasEventActivityFilterSupport.mockImplementation(
			( postType ) => 'gatherpress_venue' === postType
		);
		isInFSETemplate.mockReturnValue( true );

		render( <EventQueryControlsSlotFill /> );

		expect(
			screen.getByText( 'Filter by event activity' )
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
		isPostTypeSupporting.mockImplementation(
			( support, postType ) =>
				'gatherpress-shadow-source' === support &&
				'gatherpress_venue' === postType
		);
		hasEventActivityFilterSupport.mockReturnValue( false );
		isInFSETemplate.mockReturnValue( true );

		render( <EventQueryControlsSlotFill /> );

		expect(
			screen.queryByText( 'Filter by event activity' )
		).not.toBeInTheDocument();
	} );

	it( 'hides the event-activity filter when the queried type matches the host type', () => {
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
		isPostTypeSupporting.mockReturnValue( true );
		hasEventActivityFilterSupport.mockReturnValue( true );
		isInFSETemplate.mockReturnValue( false );

		render( <EventQueryControlsSlotFill /> );

		expect(
			screen.queryByText( 'Filter by event activity' )
		).not.toBeInTheDocument();
	} );
} );

describe( 'HasEventsFilterControls', () => {
	const activityLabel = 'Filter by event activity';
	const upcomingLabel = 'Upcoming events only';
	const pastLabel = 'Past events only';
	const activityHelp =
		'Only shows Events that have upcoming or past events attached.';
	const upcomingHelp =
		'Only shows source posts that have at least one upcoming event.';
	const pastHelp =
		'Only shows source posts that have at least one past event.';

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
			/>
		);

		expect( screen.getByText( activityLabel ) ).toBeInTheDocument();
		expect( screen.getByText( activityHelp ) ).toBeInTheDocument();
		expect( screen.queryByText( upcomingLabel ) ).not.toBeInTheDocument();
		expect( screen.queryByText( pastLabel ) ).not.toBeInTheDocument();
	} );

	it( 'writes has_events_filter and defaults upcoming_events_only to on', () => {
		const setAttributes = jest.fn();
		const query = {
			postType: 'gatherpress_venue',
		};

		render(
			<HasEventsFilterControls
				attributes={ { query } }
				setAttributes={ setAttributes }
			/>
		);

		fireEvent.click( screen.getByRole( 'button', { name: activityLabel } ) );

		expect( setAttributes ).toHaveBeenCalledWith( {
			query: {
				postType: 'gatherpress_venue',
				has_events_filter: 1,
				upcoming_events_only: 1,
			},
		} );
	} );

	it( 'shows the upcoming sub-filter by default when the activity filter is on', () => {
		render(
			<HasEventsFilterControls
				attributes={ {
					query: {
						postType: 'gatherpress_venue',
						has_events_filter: 1,
					},
				} }
				setAttributes={ jest.fn() }
			/>
		);

		expect( screen.getByText( upcomingLabel ) ).toBeInTheDocument();
		expect( screen.getByText( upcomingHelp ) ).toBeInTheDocument();
		expect( screen.queryByText( pastLabel ) ).not.toBeInTheDocument();
		expect(
			screen.getByRole( 'button', { name: upcomingLabel } )
		).toHaveAttribute( 'aria-pressed', 'true' );
	} );

	it( 'flips the sub-filter label to past events only when upcoming_events_only is off', () => {
		render(
			<HasEventsFilterControls
				attributes={ {
					query: {
						postType: 'gatherpress_venue',
						has_events_filter: 1,
						upcoming_events_only: 0,
					},
				} }
				setAttributes={ jest.fn() }
			/>
		);

		expect( screen.getByText( pastLabel ) ).toBeInTheDocument();
		expect( screen.getByText( pastHelp ) ).toBeInTheDocument();
		expect( screen.queryByText( upcomingLabel ) ).not.toBeInTheDocument();
		expect(
			screen.getByRole( 'button', { name: pastLabel } )
		).toHaveAttribute( 'aria-pressed', 'false' );
	} );

	it( 'writes upcoming_events_only to 0 when the sub-filter is turned off', () => {
		const setAttributes = jest.fn();

		render(
			<HasEventsFilterControls
				attributes={ {
					query: {
						postType: 'gatherpress_venue',
						has_events_filter: 1,
						upcoming_events_only: 1,
					},
				} }
				setAttributes={ setAttributes }
			/>
		);

		fireEvent.click( screen.getByRole( 'button', { name: upcomingLabel } ) );

		expect( setAttributes ).toHaveBeenCalledWith( {
			query: {
				postType: 'gatherpress_venue',
				has_events_filter: 1,
				upcoming_events_only: 0,
			},
		} );
	} );

	it( 'writes upcoming_events_only to 1 when the sub-filter is turned back on', () => {
		const setAttributes = jest.fn();

		render(
			<HasEventsFilterControls
				attributes={ {
					query: {
						postType: 'gatherpress_venue',
						has_events_filter: 1,
						upcoming_events_only: 0,
					},
				} }
				setAttributes={ setAttributes }
			/>
		);

		fireEvent.click( screen.getByRole( 'button', { name: pastLabel } ) );

		expect( setAttributes ).toHaveBeenCalledWith( {
			query: {
				postType: 'gatherpress_venue',
				has_events_filter: 1,
				upcoming_events_only: 1,
			},
		} );
	} );

	it( 'clears has_events_filter and keeps the stored upcoming_events_only when turned off', () => {
		const setAttributes = jest.fn();

		render(
			<HasEventsFilterControls
				attributes={ {
					query: {
						postType: 'gatherpress_venue',
						has_events_filter: 1,
						upcoming_events_only: 0,
					},
				} }
				setAttributes={ setAttributes }
			/>
		);

		fireEvent.click( screen.getByRole( 'button', { name: activityLabel } ) );

		expect( setAttributes ).toHaveBeenCalledWith( {
			query: {
				postType: 'gatherpress_venue',
				has_events_filter: 0,
				upcoming_events_only: 0,
			},
		} );
	} );
} );
