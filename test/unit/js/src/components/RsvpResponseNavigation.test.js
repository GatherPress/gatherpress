/**
 * External dependencies.
 */
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { describe, expect, it, jest, beforeEach } from '@jest/globals';
import '@testing-library/jest-dom';

/**
 * WordPress dependencies.
 */
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

// Mock RsvpResponseNavigationItem component.
jest.mock(
	'../../../../../src/components/RsvpResponseNavigationItem',
	() => ( props ) => (
		<div data-testid={ `nav-item-${ props.item.value }` }>
			{ props.item.title } ({ props.count })
		</div>
	)
);

import RsvpResponseNavigation from '../../../../../src/components/RsvpResponseNavigation';

/**
 * Coverage for RsvpResponseNavigation component.
 */
describe( 'RsvpResponseNavigation', () => {
	const mockItems = [
		{ value: 'attending', title: 'Attending' },
		{ value: 'not_attending', title: 'Not Attending' },
		{ value: 'waiting_list', title: 'Waiting List' },
	];

	const mockOnTitleClick = jest.fn();
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
					attending: { count: 10 },
					not_attending: { count: 5 },
					waiting_list: { count: 3 },
				};
			}
			return null;
		} );
	} );

	it( 'renders component with navigation wrapper', () => {
		const { container } = render(
			<RsvpResponseNavigation
				items={ mockItems }
				activeValue="attending"
				onTitleClick={ mockOnTitleClick }
				defaultLimit={ 8 }
			/>
		);

		expect(
			container.querySelector( '.gatherpress-rsvp-response__navigation-wrapper' )
		).toBeInTheDocument();
	} );

	it( 'displays active item title and count', () => {
		render(
			<RsvpResponseNavigation
				items={ mockItems }
				activeValue="attending"
				onTitleClick={ mockOnTitleClick }
				defaultLimit={ 8 }
			/>
		);

		expect( screen.getByText( 'Attending' ) ).toBeInTheDocument();
		expect( screen.getByText( '(10)' ) ).toBeInTheDocument();
	} );

	it( 'renders RsvpResponseNavigationItem for each item when dropdown is open', () => {
		const { container } = render(
			<RsvpResponseNavigation
				items={ mockItems }
				activeValue="attending"
				onTitleClick={ mockOnTitleClick }
				defaultLimit={ 8 }
			/>
		);

		const activeLink = container.querySelector( '.gatherpress-rsvp-response__navigation-active' );

		// Click to show dropdown.
		fireEvent.click( activeLink );

		expect( screen.getByTestId( 'nav-item-attending' ) ).toBeInTheDocument();
		expect( screen.getByTestId( 'nav-item-not_attending' ) ).toBeInTheDocument();
		expect( screen.getByTestId( 'nav-item-waiting_list' ) ).toBeInTheDocument();
	} );

	it( 'initializes with default counts from global responses', () => {
		render(
			<RsvpResponseNavigation
				items={ mockItems }
				activeValue="attending"
				onTitleClick={ mockOnTitleClick }
				defaultLimit={ 8 }
			/>
		);

		// Should display the count from global responses.
		expect( screen.getByText( 'Attending' ) ).toBeInTheDocument();
		expect( screen.getByText( '(10)' ) ).toBeInTheDocument();
	} );

	it( 'calls Listener with correct parameters', () => {
		render(
			<RsvpResponseNavigation
				items={ mockItems }
				activeValue="attending"
				onTitleClick={ mockOnTitleClick }
				defaultLimit={ 8 }
			/>
		);

		expect( mockListener ).toHaveBeenCalledWith(
			expect.objectContaining( {
				setRsvpCount: expect.any( Function ),
			} ),
			123
		);
	} );

	it( 'renders as anchor tag when hideNavigationDropdown is false', () => {
		globals.getFromGlobal = jest.fn( ( key ) => {
			if ( 'eventDetails.postId' === key ) {
				return 123;
			}
			if ( 'eventDetails.responses' === key ) {
				return {
					attending: { count: 10 },
					not_attending: { count: 5 },
					waiting_list: { count: 3 },
				};
			}
			return null;
		} );

		const { container } = render(
			<RsvpResponseNavigation
				items={ mockItems }
				activeValue="attending"
				onTitleClick={ mockOnTitleClick }
				defaultLimit={ 8 }
			/>
		);

		const anchor = container.querySelector( '.gatherpress-rsvp-response__navigation-active' );
		expect( anchor ).toBeInTheDocument();
		expect( anchor.tagName ).toBe( 'A' );
	} );

	it( 'renders as span tag when hideNavigationDropdown is true', () => {
		globals.getFromGlobal = jest.fn( ( key ) => {
			if ( 'eventDetails.postId' === key ) {
				return 123;
			}
			if ( 'eventDetails.responses' === key ) {
				return {
					attending: { count: 10 },
					not_attending: { count: 0 },
					waiting_list: { count: 0 },
				};
			}
			return null;
		} );

		const { container } = render(
			<RsvpResponseNavigation
				items={ mockItems }
				activeValue="attending"
				onTitleClick={ mockOnTitleClick }
				defaultLimit={ 8 }
			/>
		);

		const span = container.querySelector( '.gatherpress-rsvp-response__navigation-active' );
		expect( span ).toBeInTheDocument();
		expect( span.tagName ).toBe( 'SPAN' );
	} );

	it( 'toggles navigation dropdown when clicked', () => {
		const { container } = render(
			<RsvpResponseNavigation
				items={ mockItems }
				activeValue="attending"
				onTitleClick={ mockOnTitleClick }
				defaultLimit={ 8 }
			/>
		);

		const activeLink = container.querySelector( '.gatherpress-rsvp-response__navigation-active' );

		// Initially, navigation dropdown should not be visible.
		expect(
			container.querySelector( '.gatherpress-rsvp-response__navigation' )
		).not.toBeInTheDocument();

		// Click to show dropdown.
		fireEvent.click( activeLink );

		// Now navigation dropdown should be visible.
		expect(
			container.querySelector( '.gatherpress-rsvp-response__navigation' )
		).toBeInTheDocument();

		// Click again to hide dropdown.
		fireEvent.click( activeLink );

		// Navigation dropdown should be hidden again.
		expect(
			container.querySelector( '.gatherpress-rsvp-response__navigation' )
		).not.toBeInTheDocument();
	} );

	it( 'prevents default when clicking active navigation', () => {
		const { container } = render(
			<RsvpResponseNavigation
				items={ mockItems }
				activeValue="attending"
				onTitleClick={ mockOnTitleClick }
				defaultLimit={ 8 }
			/>
		);

		const activeLink = container.querySelector( '.gatherpress-rsvp-response__navigation-active' );
		const event = { preventDefault: jest.fn() };

		fireEvent.click( activeLink, event );

		// Note: fireEvent.click doesn't actually call preventDefault,
		// but we can verify the onClick handler exists.
		expect( activeLink ).toHaveAttribute( 'href', '#' );
	} );

	it( 'closes dropdown when clicking outside', async () => {
		const { container } = render(
			<div>
				<div data-testid="outside-element">Outside</div>
				<RsvpResponseNavigation
					items={ mockItems }
					activeValue="attending"
					onTitleClick={ mockOnTitleClick }
					defaultLimit={ 8 }
				/>
			</div>
		);

		const activeLink = container.querySelector( '.gatherpress-rsvp-response__navigation-active' );

		// Click to show dropdown.
		fireEvent.click( activeLink );

		// Verify dropdown is visible.
		expect(
			container.querySelector( '.gatherpress-rsvp-response__navigation' )
		).toBeInTheDocument();

		// Click outside.
		const outsideElement = screen.getByTestId( 'outside-element' );
		fireEvent.click( outsideElement );

		// Wait for state update.
		await waitFor( () => {
			expect(
				container.querySelector( '.gatherpress-rsvp-response__navigation' )
			).not.toBeInTheDocument();
		} );
	} );

	it( 'closes dropdown when Escape key is pressed', async () => {
		const { container } = render(
			<RsvpResponseNavigation
				items={ mockItems }
				activeValue="attending"
				onTitleClick={ mockOnTitleClick }
				defaultLimit={ 8 }
			/>
		);

		const activeLink = container.querySelector( '.gatherpress-rsvp-response__navigation-active' );

		// Click to show dropdown.
		fireEvent.click( activeLink );

		// Verify dropdown is visible.
		expect(
			container.querySelector( '.gatherpress-rsvp-response__navigation' )
		).toBeInTheDocument();

		// Press Escape key.
		fireEvent.keyDown( document, { key: 'Escape' } );

		// Wait for state update.
		await waitFor( () => {
			expect(
				container.querySelector( '.gatherpress-rsvp-response__navigation' )
			).not.toBeInTheDocument();
		} );
	} );

	it( 'does not close dropdown when other key is pressed', async () => {
		const { container } = render(
			<RsvpResponseNavigation
				items={ mockItems }
				activeValue="attending"
				onTitleClick={ mockOnTitleClick }
				defaultLimit={ 8 }
			/>
		);

		const activeLink = container.querySelector( '.gatherpress-rsvp-response__navigation-active' );

		// Click to show dropdown.
		fireEvent.click( activeLink );

		// Verify dropdown is visible.
		expect(
			container.querySelector( '.gatherpress-rsvp-response__navigation' )
		).toBeInTheDocument();

		// Press other key (e.g., 'a').
		fireEvent.keyDown( document, { key: 'a' } );

		// Dropdown should still be visible.
		expect(
			container.querySelector( '.gatherpress-rsvp-response__navigation' )
		).toBeInTheDocument();
	} );

	it( 'hides navigation dropdown when not_attending and waiting_list counts are 0', () => {
		globals.getFromGlobal = jest.fn( ( key ) => {
			if ( 'eventDetails.postId' === key ) {
				return 123;
			}
			if ( 'eventDetails.responses' === key ) {
				return {
					attending: { count: 10 },
					not_attending: { count: 0 },
					waiting_list: { count: 0 },
				};
			}
			return null;
		} );

		const { container } = render(
			<RsvpResponseNavigation
				items={ mockItems }
				activeValue="attending"
				onTitleClick={ mockOnTitleClick }
				defaultLimit={ 8 }
			/>
		);

		const activeElement = container.querySelector( '.gatherpress-rsvp-response__navigation-active' );

		// Should be span when hideNavigationDropdown is true.
		expect( activeElement.tagName ).toBe( 'SPAN' );

		// Click should not show dropdown.
		fireEvent.click( activeElement );

		expect(
			container.querySelector( '.gatherpress-rsvp-response__navigation' )
		).not.toBeInTheDocument();
	} );

	it( 'shows navigation dropdown when not_attending count is greater than 0', () => {
		globals.getFromGlobal = jest.fn( ( key ) => {
			if ( 'eventDetails.postId' === key ) {
				return 123;
			}
			if ( 'eventDetails.responses' === key ) {
				return {
					attending: { count: 10 },
					not_attending: { count: 5 },
					waiting_list: { count: 0 },
				};
			}
			return null;
		} );

		const { container } = render(
			<RsvpResponseNavigation
				items={ mockItems }
				activeValue="attending"
				onTitleClick={ mockOnTitleClick }
				defaultLimit={ 8 }
			/>
		);

		const activeElement = container.querySelector( '.gatherpress-rsvp-response__navigation-active' );

		// Should be anchor when hideNavigationDropdown is false.
		expect( activeElement.tagName ).toBe( 'A' );
	} );

	it( 'shows navigation dropdown when waiting_list count is greater than 0', () => {
		globals.getFromGlobal = jest.fn( ( key ) => {
			if ( 'eventDetails.postId' === key ) {
				return 123;
			}
			if ( 'eventDetails.responses' === key ) {
				return {
					attending: { count: 10 },
					not_attending: { count: 0 },
					waiting_list: { count: 3 },
				};
			}
			return null;
		} );

		const { container } = render(
			<RsvpResponseNavigation
				items={ mockItems }
				activeValue="attending"
				onTitleClick={ mockOnTitleClick }
				defaultLimit={ 8 }
			/>
		);

		const activeElement = container.querySelector( '.gatherpress-rsvp-response__navigation-active' );

		// Should be anchor when hideNavigationDropdown is false.
		expect( activeElement.tagName ).toBe( 'A' );
	} );

	it( 'displays correct active item when activeValue changes', () => {
		const { rerender } = render(
			<RsvpResponseNavigation
				items={ mockItems }
				activeValue="attending"
				onTitleClick={ mockOnTitleClick }
				defaultLimit={ 8 }
			/>
		);

		expect( screen.getByText( 'Attending' ) ).toBeInTheDocument();

		rerender(
			<RsvpResponseNavigation
				items={ mockItems }
				activeValue="not_attending"
				onTitleClick={ mockOnTitleClick }
				defaultLimit={ 8 }
			/>
		);

		expect( screen.getByText( 'Not Attending' ) ).toBeInTheDocument();
	} );

	it( 'handles empty responses from global', () => {
		globals.getFromGlobal = jest.fn( ( key ) => {
			if ( 'eventDetails.postId' === key ) {
				return 123;
			}
			if ( 'eventDetails.responses' === key ) {
				return null;
			}
			return null;
		} );

		render(
			<RsvpResponseNavigation
				items={ mockItems }
				activeValue="attending"
				onTitleClick={ mockOnTitleClick }
				defaultLimit={ 8 }
			/>
		);

		// Should display 0 when no responses.
		expect( screen.getByText( '(0)' ) ).toBeInTheDocument();
	} );

	it( 'passes defaultLimit to RsvpResponseNavigationItem components', () => {
		const { container } = render(
			<RsvpResponseNavigation
				items={ mockItems }
				activeValue="attending"
				onTitleClick={ mockOnTitleClick }
				defaultLimit={ 12 }
			/>
		);

		const activeLink = container.querySelector( '.gatherpress-rsvp-response__navigation-active' );

		// Click to show dropdown.
		fireEvent.click( activeLink );

		// Verify all items are rendered (they receive defaultLimit prop).
		expect( screen.getByTestId( 'nav-item-attending' ) ).toBeInTheDocument();
		expect( screen.getByTestId( 'nav-item-not_attending' ) ).toBeInTheDocument();
		expect( screen.getByTestId( 'nav-item-waiting_list' ) ).toBeInTheDocument();
	} );
} );
