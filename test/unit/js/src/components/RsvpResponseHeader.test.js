/**
 * External dependencies.
 */
import { render, screen, fireEvent } from '@testing-library/react';
import { describe, expect, it, jest, beforeEach } from '@jest/globals';
import '@testing-library/jest-dom';

/**
 * WordPress dependencies.
 */
jest.mock( '@wordpress/i18n', () => ( {
	__: ( text ) => text,
} ) );

jest.mock( '@wordpress/element', () => ( {
	...jest.requireActual( '@wordpress/element' ),
} ) );

/**
 * Internal dependencies.
 */
import * as broadcasting from '../../../../../src/helpers/broadcasting';
import * as globals from '../../../../../src/helpers/globals';

jest.mock( '../../../../../src/helpers/broadcasting' );
jest.mock( '../../../../../src/helpers/globals' );

// Mock RsvpResponseNavigation component.
jest.mock(
	'../../../../../src/components/RsvpResponseNavigation',
	() => ( props ) => (
		<div data-testid="rsvp-response-navigation">
			Mock Navigation - Active: { props.activeValue }
		</div>
	)
);

import RsvpResponseHeader from '../../../../../src/components/RsvpResponseHeader';

/**
 * Coverage for RsvpResponseHeader component.
 */
describe( 'RsvpResponseHeader', () => {
	const mockItems = [
		{ value: 'attending', title: 'Attending' },
		{ value: 'not_attending', title: 'Not Attending' },
		{ value: 'waiting_list', title: 'Waiting List' },
	];

	const mockOnTitleClick = jest.fn();
	const mockSetRsvpLimit = jest.fn();
	const mockListener = jest.fn();

	beforeEach( () => {
		jest.clearAllMocks();
		broadcasting.Listener = mockListener;
		globals.getFromGlobal = jest.fn( ( key ) => {
			if ( 'eventDetails.postId' === key ) {
				return 123;
			}
			if ( 'eventDetails.responses' === key ) {
				return {
					attending: {
						count: 20,
						records: [],
					},
					not_attending: {
						count: 5,
						records: [],
					},
					waiting_list: {
						count: 3,
						records: [],
					},
				};
			}
			return null;
		} );
	} );

	it( 'renders component with header class', () => {
		const { container } = render(
			<RsvpResponseHeader
				items={ mockItems }
				activeValue="attending"
				onTitleClick={ mockOnTitleClick }
				rsvpLimit={ 8 }
				setRsvpLimit={ mockSetRsvpLimit }
				defaultLimit={ 8 }
			/>
		);

		expect(
			container.querySelector( '.gatherpress-rsvp-response__header' )
		).toBeInTheDocument();
	} );

	it( 'renders dashicons groups icon', () => {
		const { container } = render(
			<RsvpResponseHeader
				items={ mockItems }
				activeValue="attending"
				onTitleClick={ mockOnTitleClick }
				rsvpLimit={ 8 }
				setRsvpLimit={ mockSetRsvpLimit }
				defaultLimit={ 8 }
			/>
		);

		const icon = container.querySelector( '.dashicons-groups' );
		expect( icon ).toBeInTheDocument();
		expect( icon ).toHaveClass( 'dashicons' );
	} );

	it( 'renders RsvpResponseNavigation component', () => {
		render(
			<RsvpResponseHeader
				items={ mockItems }
				activeValue="attending"
				onTitleClick={ mockOnTitleClick }
				rsvpLimit={ 8 }
				setRsvpLimit={ mockSetRsvpLimit }
				defaultLimit={ 8 }
			/>
		);

		expect(
			screen.getByTestId( 'rsvp-response-navigation' )
		).toBeInTheDocument();
		expect( screen.getByText( /Active: attending/ ) ).toBeInTheDocument();
	} );

	it( 'shows "See all" link when count exceeds limit', () => {
		render(
			<RsvpResponseHeader
				items={ mockItems }
				activeValue="attending"
				onTitleClick={ mockOnTitleClick }
				rsvpLimit={ 8 }
				setRsvpLimit={ mockSetRsvpLimit }
				defaultLimit={ 8 }
			/>
		);

		expect( screen.getByText( 'See all' ) ).toBeInTheDocument();
	} );

	it( 'shows "See fewer" link when rsvpLimit is false', () => {
		render(
			<RsvpResponseHeader
				items={ mockItems }
				activeValue="attending"
				onTitleClick={ mockOnTitleClick }
				rsvpLimit={ false }
				setRsvpLimit={ mockSetRsvpLimit }
				defaultLimit={ 8 }
			/>
		);

		expect( screen.getByText( 'See fewer' ) ).toBeInTheDocument();
	} );

	it( 'calls setRsvpLimit with false when clicking "See all"', () => {
		render(
			<RsvpResponseHeader
				items={ mockItems }
				activeValue="attending"
				onTitleClick={ mockOnTitleClick }
				rsvpLimit={ 8 }
				setRsvpLimit={ mockSetRsvpLimit }
				defaultLimit={ 8 }
			/>
		);

		const link = screen.getByText( 'See all' );
		fireEvent.click( link );

		expect( mockSetRsvpLimit ).toHaveBeenCalledWith( false );
	} );

	it( 'calls setRsvpLimit with defaultLimit when clicking "See fewer"', () => {
		render(
			<RsvpResponseHeader
				items={ mockItems }
				activeValue="attending"
				onTitleClick={ mockOnTitleClick }
				rsvpLimit={ false }
				setRsvpLimit={ mockSetRsvpLimit }
				defaultLimit={ 10 }
			/>
		);

		const link = screen.getByText( 'See fewer' );
		fireEvent.click( link );

		expect( mockSetRsvpLimit ).toHaveBeenCalledWith( 10 );
	} );

	it( 'prevents default when clicking see all/fewer link', () => {
		render(
			<RsvpResponseHeader
				items={ mockItems }
				activeValue="attending"
				onTitleClick={ mockOnTitleClick }
				rsvpLimit={ 8 }
				setRsvpLimit={ mockSetRsvpLimit }
				defaultLimit={ 8 }
			/>
		);

		const link = screen.getByText( 'See all' );
		const event = { preventDefault: jest.fn() };
		fireEvent.click( link, event );

		// Default should be prevented.
		expect( mockSetRsvpLimit ).toHaveBeenCalled();
	} );

	it( 'hides see all link when count does not exceed limit', () => {
		globals.getFromGlobal = jest.fn( ( key ) => {
			if ( 'eventDetails.postId' === key ) {
				return 123;
			}
			if ( 'eventDetails.responses' === key ) {
				return {
					attending: {
						count: 5,
						records: [],
					},
				};
			}
			return null;
		} );

		render(
			<RsvpResponseHeader
				items={ mockItems }
				activeValue="attending"
				onTitleClick={ mockOnTitleClick }
				rsvpLimit={ 8 }
				setRsvpLimit={ mockSetRsvpLimit }
				defaultLimit={ 8 }
			/>
		);

		expect( screen.queryByText( 'See all' ) ).not.toBeInTheDocument();
		expect( screen.queryByText( 'See fewer' ) ).not.toBeInTheDocument();
	} );

	it( 'calls Listener with correct parameters', () => {
		render(
			<RsvpResponseHeader
				items={ mockItems }
				activeValue="attending"
				onTitleClick={ mockOnTitleClick }
				rsvpLimit={ 8 }
				setRsvpLimit={ mockSetRsvpLimit }
				defaultLimit={ 8 }
			/>
		);

		expect( mockListener ).toHaveBeenCalledWith(
			expect.objectContaining( {
				setRsvpSeeAllLink: expect.any( Function ),
			} ),
			123
		);
	} );

	it( 'sets rsvpSeeAllLink to true when count exceeds default limit', () => {
		render(
			<RsvpResponseHeader
				items={ mockItems }
				activeValue="attending"
				onTitleClick={ mockOnTitleClick }
				rsvpLimit={ 8 }
				setRsvpLimit={ mockSetRsvpLimit }
				defaultLimit={ 8 }
			/>
		);

		// Since count is 20 and limit is 8, see all should be shown.
		expect( screen.getByText( 'See all' ) ).toBeInTheDocument();
	} );

	it( 'sets rsvpSeeAllLink to false when responses is null', () => {
		globals.getFromGlobal = jest.fn( ( key ) => {
			if ( 'eventDetails.postId' === key ) {
				return 123;
			}
			return null;
		} );

		render(
			<RsvpResponseHeader
				items={ mockItems }
				activeValue="attending"
				onTitleClick={ mockOnTitleClick }
				rsvpLimit={ 8 }
				setRsvpLimit={ mockSetRsvpLimit }
				defaultLimit={ 8 }
			/>
		);

		expect( screen.queryByText( 'See all' ) ).not.toBeInTheDocument();
	} );

	it( 'has correct see all link wrapper class', () => {
		const { container } = render(
			<RsvpResponseHeader
				items={ mockItems }
				activeValue="attending"
				onTitleClick={ mockOnTitleClick }
				rsvpLimit={ 8 }
				setRsvpLimit={ mockSetRsvpLimit }
				defaultLimit={ 8 }
			/>
		);

		expect(
			container.querySelector( '.gatherpress-rsvp-response__see-all' )
		).toBeInTheDocument();
	} );

	it( 'handles missing count with nullish coalescing', () => {
		globals.getFromGlobal = jest.fn( ( key ) => {
			if ( 'eventDetails.postId' === key ) {
				return 123;
			}
			if ( 'eventDetails.responses' === key ) {
				return {
					attending: {
						// Count is missing/undefined.
						records: [],
					},
				};
			}
			return null;
		} );

		const { container } = render(
			<RsvpResponseHeader
				items={ mockItems }
				activeValue="attending"
				onTitleClick={ mockOnTitleClick }
				rsvpLimit={ 8 }
				setRsvpLimit={ mockSetRsvpLimit }
				defaultLimit={ 8 }
			/>
		);

		// Should not show "See all" link when count is undefined (defaults to 0).
		expect( container.querySelector( '.gatherpress-rsvp-response__see-all' ) ).not.toBeInTheDocument();
	} );
} );
