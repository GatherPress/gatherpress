/**
 * External dependencies
 */
import { beforeEach, describe, expect, it, jest } from '@jest/globals';
import { act, fireEvent, render, screen } from '@testing-library/react';
import '@testing-library/jest-dom';

const mockApiFetch = jest.fn();
const mockSpeak = jest.fn();

jest.mock( '@wordpress/api-fetch', () => ( {
	__esModule: true,
	default: ( args ) => mockApiFetch( args ),
} ) );

jest.mock( '@wordpress/a11y', () => ( {
	speak: ( message ) => mockSpeak( message ),
} ) );

// The real popover keeps repositioning while it is open, which makes jsdom
// crawl. These tests cover what a pick saves, so the menu renders in place.
jest.mock( '@wordpress/components', () => ( {
	...jest.requireActual( '@wordpress/components' ),
	Dropdown: ( { renderToggle, renderContent } ) => (
		<>
			{ renderToggle( { isOpen: false, onToggle: () => {} } ) }
			{ renderContent( { onClose: () => {} } ) }
		</>
	),
} ) );

/**
 * Internal dependencies
 */
import CheckIn from '@src/rsvp-admin/check-in';

const STATES = [
	{ value: 'checked-in', label: 'Checked in', icon: 'yes' },
	{ value: 'no-show', label: 'No-show', icon: 'no-alt' },
	{ value: 'not-recorded', label: 'Not recorded', icon: 'minus' },
];

/**
 * Renders the dropdown and lets React settle.
 *
 * @param {string} initialState The state the page loaded with.
 *
 * @return {Promise<void>} Resolves once React has settled.
 */
const renderCheckIn = async ( initialState ) => {
	await act( async () => {
		render(
			<CheckIn
				rsvpId={ 7 }
				attendee="Ada Lovelace"
				initialState={ initialState }
				states={ STATES }
			/>,
		);
	} );
};

/**
 * Picks a state from the menu.
 *
 * @param {string} label The state to pick.
 *
 * @return {Promise<void>} Resolves once the save settles.
 */
const pick = async ( label ) => {
	await act( async () => {
		fireEvent.click( screen.getByRole( 'menuitemradio', { name: label } ) );
	} );
};

describe( 'CheckIn', () => {
	beforeEach( () => {
		mockApiFetch.mockReset();
		mockSpeak.mockReset();
	} );

	it( 'shows the current state with its icon', async () => {
		await renderCheckIn( 'checked-in' );

		const toggle = screen.getByRole( 'button', { name: 'Check-in for Ada Lovelace: Checked in' } );

		expect( toggle ).toHaveTextContent( 'Checked in' );
		expect( toggle.querySelector( '.dashicons-yes' ) ).toBeInTheDocument();
	} );

	it( 'puts the states in a menu the arrow keys move through', async () => {
		await renderCheckIn( 'checked-in' );

		const items = screen.getAllByRole( 'menuitemradio' );

		expect( screen.getByRole( 'menu' ) ).toContainElement( items[ 0 ] );

		// jsdom lays nothing out, so the menu would find no visible item.
		const rects = jest
			.spyOn( HTMLElement.prototype, 'getClientRects' )
			.mockReturnValue( [ {} ] );

		items[ 0 ].focus();
		fireEvent.keyDown( items[ 0 ], { code: 'ArrowDown' } );
		rects.mockRestore();

		expect( items[ 1 ] ).toHaveFocus();
	} );

	it( 'marks the current state in the menu', async () => {
		await renderCheckIn( 'no-show' );

		const current = screen.getByRole( 'menuitemradio', { name: 'No-show' } );

		expect( current ).toHaveAttribute( 'aria-checked', 'true' );
		expect( current ).toHaveClass( 'is-selected' );
		expect(
			screen.getByRole( 'menuitemradio', { name: 'Checked in' } ),
		).not.toHaveClass( 'is-selected' );
	} );

	it( 'falls back to the last state for an unknown value', async () => {
		await renderCheckIn( 'gone' );

		expect(
			screen.getByRole( 'button', { name: 'Check-in for Ada Lovelace: Not recorded' } ),
		).toBeInTheDocument();
	} );

	it( 'saves a new state and shows it', async () => {
		mockApiFetch.mockResolvedValue( {
			value: 'no-show',
			label: 'No-show',
			icon: 'no-alt',
		} );

		await renderCheckIn( 'not-recorded' );
		await pick( 'No-show' );

		expect(
			screen.getByRole( 'button', { name: 'Check-in for Ada Lovelace: No-show' } ),
		).toBeInTheDocument();
		expect( mockApiFetch ).toHaveBeenCalledWith( {
			path: '/gatherpress/v1/rsvp/7/check-in',
			method: 'POST',
			data: { state: 'no-show' },
		} );
		expect( mockSpeak ).toHaveBeenCalledWith( 'Check-in saved: No-show' );
	} );

	it( 'does not save the state the RSVP already has', async () => {
		await renderCheckIn( 'checked-in' );
		await pick( 'Checked in' );

		expect( mockApiFetch ).not.toHaveBeenCalled();
	} );

	it( 'keeps the state and shows the error when the save fails', async () => {
		mockApiFetch.mockRejectedValue( { message: 'Only an approved RSVP can be checked in.' } );

		await renderCheckIn( 'checked-in' );
		await pick( 'No-show' );

		expect( screen.getByRole( 'alert' ) ).toHaveTextContent(
			'Only an approved RSVP can be checked in.',
		);
		expect(
			screen.getByRole( 'button', { name: 'Check-in for Ada Lovelace: Checked in' } ),
		).toBeInTheDocument();
	} );

	it( 'shows a general error when the failure has no message', async () => {
		mockApiFetch.mockRejectedValue( {} );

		await renderCheckIn( 'checked-in' );
		await pick( 'No-show' );

		expect( screen.getByRole( 'alert' ) ).toHaveTextContent(
			'The check-in could not be saved.',
		);
	} );
} );
