/**
 * External dependencies.
 */
import { describe, expect, it, jest, beforeEach } from '@jest/globals';
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
	sprintf: ( fmt, ...args ) => args.reduce( ( s, a ) => s.replace( '%s', a ), fmt ),
} ) );

// The slot wrapper invokes its render-prop child immediately so we can assert
// on what the fill renders without setting up a real Slot consumer.
jest.mock( '@src/variations/core/query/slots/query-controls', () => ( {
	__esModule: true,
	default: ( { children } ) => (
		<div data-testid="query-controls-fill">
			{ children( {
				attributes: {
					query: {
						postType: 'gatherpress_event',
						inherit: false,
					},
				},
				setAttributes: jest.fn(),
			} ) }
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
} ) );

jest.mock( '@src/helpers/editor', () => ( {
	isInFSETemplate: jest.fn(),
} ) );

/**
 * WordPress dependencies.
 */
import { isEventPostType, usePostTypeSupports } from '@src/helpers/event';
import { isInFSETemplate } from '@src/helpers/editor';

/**
 * Internal dependencies.
 */
import { EventQueryControlsSlotFill } from '@src/variations/core/query/components';

const venueToggleLabel = 'Filter by current venue';
const excludeToggleLabel = 'Exclude Current Event';
const venueHelp =
	'When placed on a venue page, only shows events at that venue.';
const templateHelp =
	'The filter only takes effect when this template renders on a venue page.';

describe( 'EventQueryControlsSlotFill', () => {
	beforeEach( () => {
		isEventPostType.mockReset();
		usePostTypeSupports.mockReset();
		isInFSETemplate.mockReset();
	} );

	it( 'hides the venue filter toggle on a regular non-venue, non-template host', () => {
		isEventPostType.mockReturnValue( true );
		usePostTypeSupports.mockReturnValue( false );
		isInFSETemplate.mockReturnValue( false );

		render( <EventQueryControlsSlotFill /> );

		expect(
			screen.queryByText( venueToggleLabel )
		).not.toBeInTheDocument();
		expect( usePostTypeSupports ).toHaveBeenCalledWith(
			'gatherpress-venue-information'
		);
	} );

	it( 'shows the venue filter toggle with venue copy when host is a venue post', () => {
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

	it( 'still gates the exclude-current-event toggle on the existing isEventPostType check', () => {
		isEventPostType.mockReturnValue( false );
		usePostTypeSupports.mockReturnValue( true );
		isInFSETemplate.mockReturnValue( false );

		render( <EventQueryControlsSlotFill /> );

		expect(
			screen.queryByText( excludeToggleLabel )
		).not.toBeInTheDocument();
	} );

	it( 'shows the exclude-current-event toggle when the host is an event post type', () => {
		isEventPostType.mockReturnValue( true );
		usePostTypeSupports.mockReturnValue( true );
		isInFSETemplate.mockReturnValue( false );

		render( <EventQueryControlsSlotFill /> );

		expect( screen.getByText( excludeToggleLabel ) ).toBeInTheDocument();
	} );
} );
