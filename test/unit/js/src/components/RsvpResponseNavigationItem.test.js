/**
 * External dependencies.
 */
import { render, screen } from '@testing-library/react';
import { describe, expect, it, jest, beforeEach } from '@jest/globals';
import '@testing-library/jest-dom';

/**
 * WordPress dependencies.
 */
jest.mock( '@wordpress/element', () => ( {
	...jest.requireActual( '@wordpress/element' ),
	useEffect: jest.fn( ( effect ) => effect() ),
} ) );

/**
 * Internal dependencies.
 */
import RsvpResponseNavigationItem from '../../../../../src/components/RsvpResponseNavigationItem';
import * as broadcasting from '../../../../../src/helpers/broadcasting';
import * as globals from '../../../../../src/helpers/globals';

jest.mock( '../../../../../src/helpers/broadcasting' );
jest.mock( '../../../../../src/helpers/globals' );

/**
 * Coverage for RsvpResponseNavigationItem component.
 */
describe( 'RsvpResponseNavigationItem', () => {
	const mockOnTitleClick = jest.fn();
	const mockBroadcaster = jest.fn();

	beforeEach( () => {
		jest.clearAllMocks();
		broadcasting.Broadcaster = mockBroadcaster;
		globals.getFromGlobal = jest.fn( ( key ) => {
			if ( 'eventDetails.postId' === key ) {
				return 123;
			}
			return null;
		} );
	} );

	it( 'renders as anchor when not active item', () => {
		const item = { title: 'Attending', value: 'attending' };
		const { container } = render(
			<RsvpResponseNavigationItem
				item={ item }
				activeItem={ false }
				count={ 5 }
				onTitleClick={ mockOnTitleClick }
				defaultLimit={ 8 }
			/>
		);

		const anchor = container.querySelector( 'a' );
		expect( anchor ).toBeInTheDocument();
		expect( anchor ).toHaveClass( 'gatherpress-rsvp-response__anchor' );
		expect( anchor ).toHaveAttribute( 'data-item', 'attending' );
		expect( anchor ).toHaveAttribute( 'data-toggle', 'tab' );
		expect( anchor ).toHaveAttribute( 'href', '#' );
		expect( anchor ).toHaveAttribute( 'role', 'tab' );
		expect( anchor ).toHaveAttribute(
			'aria-controls',
			'#gatherpress-rsvp-attending'
		);
	} );

	it( 'renders as span when active item', () => {
		const item = { title: 'Attending', value: 'attending' };
		const { container } = render(
			<RsvpResponseNavigationItem
				item={ item }
				activeItem={ true }
				count={ 5 }
				onTitleClick={ mockOnTitleClick }
				defaultLimit={ 8 }
			/>
		);

		const span = container.querySelector( 'span.gatherpress-rsvp-response__anchor' );
		expect( span ).toBeInTheDocument();
		expect( span.tagName ).toBe( 'SPAN' );
		expect( container.querySelector( 'a.gatherpress-rsvp-response__anchor' ) ).not.toBeInTheDocument();
	} );

	it( 'displays the title text', () => {
		const item = { title: 'Not Attending', value: 'not_attending' };
		render(
			<RsvpResponseNavigationItem
				item={ item }
				activeItem={ false }
				count={ 3 }
				onTitleClick={ mockOnTitleClick }
				defaultLimit={ 8 }
			/>
		);

		expect( screen.getByText( 'Not Attending' ) ).toBeInTheDocument();
	} );

	it( 'displays the count in parentheses', () => {
		const item = { title: 'Waiting List', value: 'waiting_list' };
		const { container } = render(
			<RsvpResponseNavigationItem
				item={ item }
				activeItem={ false }
				count={ 7 }
				onTitleClick={ mockOnTitleClick }
				defaultLimit={ 8 }
			/>
		);

		const countSpan = container.querySelector( '.gatherpress-rsvp-response__count' );
		expect( countSpan ).toBeInTheDocument();
		expect( countSpan.textContent ).toContain( '7' );
	} );

	it( 'calls onTitleClick when clicked', () => {
		const item = { title: 'Attending', value: 'attending' };
		const { container } = render(
			<RsvpResponseNavigationItem
				item={ item }
				activeItem={ false }
				count={ 5 }
				onTitleClick={ mockOnTitleClick }
				defaultLimit={ 8 }
			/>
		);

		const anchor = container.querySelector( 'a' );
		anchor.click();

		expect( mockOnTitleClick ).toHaveBeenCalledTimes( 1 );
	} );

	it( 'returns empty string when count is 0 and value is not attending', () => {
		const item = { title: 'Not Attending', value: 'not_attending' };
		const { container } = render(
			<RsvpResponseNavigationItem
				item={ item }
				activeItem={ false }
				count={ 0 }
				onTitleClick={ mockOnTitleClick }
				defaultLimit={ 8 }
			/>
		);

		expect( container.innerHTML ).toBe( '' );
	} );

	it( 'renders when count is 0 but value is attending', () => {
		const item = { title: 'Attending', value: 'attending' };
		const { container } = render(
			<RsvpResponseNavigationItem
				item={ item }
				activeItem={ false }
				count={ 0 }
				onTitleClick={ mockOnTitleClick }
				defaultLimit={ 8 }
			/>
		);

		expect( container.querySelector( 'a' ) ).toBeInTheDocument();
		const countSpan = container.querySelector( '.gatherpress-rsvp-response__count' );
		expect( countSpan ).toBeInTheDocument();
		expect( countSpan.textContent ).toContain( '0' );
	} );

	it( 'broadcasts when activeItem is true', () => {
		const item = { title: 'Attending', value: 'attending' };
		render(
			<RsvpResponseNavigationItem
				item={ item }
				activeItem={ true }
				count={ 5 }
				onTitleClick={ mockOnTitleClick }
				defaultLimit={ 8 }
			/>
		);

		expect( mockBroadcaster ).toHaveBeenCalledWith(
			{ setRsvpSeeAllLink: false },
			123
		);
	} );

	it( 'broadcasts with rsvpSeeAllLink true when count exceeds defaultLimit', () => {
		const item = { title: 'Attending', value: 'attending' };
		render(
			<RsvpResponseNavigationItem
				item={ item }
				activeItem={ true }
				count={ 10 }
				onTitleClick={ mockOnTitleClick }
				defaultLimit={ 8 }
			/>
		);

		expect( mockBroadcaster ).toHaveBeenCalledWith(
			{ setRsvpSeeAllLink: true },
			123
		);
	} );

	it( 'does not broadcast when activeItem is false', () => {
		const item = { title: 'Attending', value: 'attending' };
		render(
			<RsvpResponseNavigationItem
				item={ item }
				activeItem={ false }
				count={ 10 }
				onTitleClick={ mockOnTitleClick }
				defaultLimit={ 8 }
			/>
		);

		expect( mockBroadcaster ).not.toHaveBeenCalled();
	} );

	it( 'has correct wrapper div class', () => {
		const item = { title: 'Attending', value: 'attending' };
		const { container } = render(
			<RsvpResponseNavigationItem
				item={ item }
				activeItem={ false }
				count={ 5 }
				onTitleClick={ mockOnTitleClick }
				defaultLimit={ 8 }
			/>
		);

		expect(
			container.querySelector( '.gatherpress-rsvp-response__navigation-item' )
		).toBeInTheDocument();
	} );

	it( 'has correct count span class', () => {
		const item = { title: 'Attending', value: 'attending' };
		const { container } = render(
			<RsvpResponseNavigationItem
				item={ item }
				activeItem={ false }
				count={ 5 }
				onTitleClick={ mockOnTitleClick }
				defaultLimit={ 8 }
			/>
		);

		expect(
			container.querySelector( '.gatherpress-rsvp-response__count' )
		).toBeInTheDocument();
	} );

	it( 'uses default activeItem value when not provided', () => {
		const item = { title: 'Attending', value: 'attending' };
		const { container } = render(
			<RsvpResponseNavigationItem
				item={ item }
				count={ 5 }
				onTitleClick={ mockOnTitleClick }
				defaultLimit={ 8 }
			/>
		);

		// When activeItem is not provided, it defaults to false, so should render as anchor.
		const anchor = container.querySelector( 'a' );
		expect( anchor ).toBeInTheDocument();
		expect( anchor ).toHaveClass( 'gatherpress-rsvp-response__anchor' );
	} );
} );
