/**
 * External dependencies
 */
import { describe, expect, it } from '@jest/globals';

/**
 * Internal dependencies
 */
import {
	getResponseLabel,
	toggleResponse,
} from '@src/rsvp-admin/response-filter';

const STATUSES = [
	{ value: 'attending', label: 'Attending' },
	{ value: 'not_attending', label: 'Not Attending' },
	{ value: 'waiting_list', label: 'Waiting List' },
];

describe( 'getResponseLabel', () => {
	// The toggle is an icon, so this is its accessible name and tooltip.
	it( 'reads as unfiltered when nothing is selected', () => {
		expect( getResponseLabel( STATUSES, [] ) ).toBe(
			'Filter by response: all'
		);
	} );

	it( 'names the status when exactly one is selected', () => {
		expect( getResponseLabel( STATUSES, [ 'waiting_list' ] ) ).toBe(
			'Filter by response: Waiting List'
		);
	} );

	it( 'counts them once naming each would not fit', () => {
		expect(
			getResponseLabel( STATUSES, [ 'attending', 'waiting_list' ] )
		).toBe( 'Filter by response: 2 selected' );
	} );

	it( 'still announces the control for an unknown value', () => {
		// A hand-edited URL can name a status the list does not carry.
		expect( getResponseLabel( STATUSES, [ 'invented' ] ) ).toBe(
			'Filter by response'
		);
	} );
} );

describe( 'toggleResponse', () => {
	it( 'adds a status that is not selected', () => {
		expect( toggleResponse( [], 'attending' ) ).toEqual( [ 'attending' ] );
	} );

	it( 'removes a status that is selected', () => {
		expect( toggleResponse( [ 'attending' ], 'attending' ) ).toEqual( [] );
	} );

	it( 'leaves the other selections alone', () => {
		expect(
			toggleResponse( [ 'attending', 'waiting_list' ], 'attending' )
		).toEqual( [ 'waiting_list' ] );
	} );

	it( 'does not mutate the selection it was given', () => {
		const selected = [ 'attending' ];

		toggleResponse( selected, 'waiting_list' );

		expect( selected ).toEqual( [ 'attending' ] );
	} );
} );
