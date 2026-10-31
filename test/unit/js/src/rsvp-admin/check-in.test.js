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
	{ value: 'checked-in', label: 'Checked in', icon: '<svg data-icon="check"></svg>' },
	{ value: 'no-show', label: 'No-show', icon: '<svg data-icon="close"></svg>' },
	{ value: 'not-recorded', label: 'Not recorded', icon: '<svg data-icon="line"></svg>' },
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
		render( <CheckIn rsvpId={ 7 } initialState={ initialState } states={ STATES } /> );
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

		const toggle = screen.getByRole( 'button', { name: 'Check-in: Checked in' } );

		expect( toggle ).toHaveTextContent( 'Checked in' );
		expect( toggle.querySelector( '[data-icon="check"]' ) ).toBeInTheDocument();
	} );

	it( 'falls back to the last state for an unknown value', async () => {
		await renderCheckIn( 'gone' );

		expect(
			screen.getByRole( 'button', { name: 'Check-in: Not recorded' } ),
		).toBeInTheDocument();
	} );

	it( 'saves a new state and shows it', async () => {
		mockApiFetch.mockResolvedValue( {
			value: 'no-show',
			label: 'No-show',
			icon: '',
		} );

		await renderCheckIn( 'not-recorded' );
		await pick( 'No-show' );

		expect(
			screen.getByRole( 'button', { name: 'Check-in: No-show' } ),
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
			screen.getByRole( 'button', { name: 'Check-in: Checked in' } ),
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
