/**
 * External dependencies
 */
import { beforeAll, describe, expect, it } from '@jest/globals';
import { act, screen, within } from '@testing-library/react';
import '@testing-library/jest-dom';

const STATUSES = JSON.stringify( [
	{ value: 'attending', label: 'Attending' },
	{ value: 'not_attending', label: 'Not Attending' },
	{ value: 'waiting_list', label: 'Waiting List' },
] );

const CHECK_IN_STATES = JSON.stringify( [
	{ value: 'checked-in', label: 'Checked in', icon: '<svg></svg>' },
	{ value: 'no-show', label: 'No-show', icon: '<svg></svg>' },
	{ value: 'not-recorded', label: 'Not recorded', icon: '<svg></svg>' },
] );

const MOUNTS = {
	full: {
		postTypes: 'gatherpress_event',
		postId: '11',
		label: 'Pick an event',
		statuses: STATUSES,
		selected: 'attending',
		checkInStates: CHECK_IN_STATES,
		checkIn: 'no-show',
	},
	bare: {},
	malformed: {
		postTypes: 'gatherpress_event',
		statuses: '{"attending"',
		checkInStates: '[{"value"',
	},
};

const nodes = {};

/**
 * Runs the entry point once against every mount point.
 *
 * The module mounts on import. Imported once with every case present, because
 * resetting the registry loads a second React whose work `act` cannot flush.
 *
 * @return {Promise<void>} Resolves once React has settled.
 */
const mountAll = async () => {
	Object.entries( MOUNTS ).forEach( ( [ name, dataset ] ) => {
		const node = document.createElement( 'div' );

		node.className = 'gatherpress-rsvp-filters';
		Object.assign( node.dataset, dataset );
		document.body.appendChild( node );

		nodes[ name ] = node;
	} );

	const cell = document.createElement( 'div' );

	cell.className = 'gatherpress-rsvp-check-in';
	cell.dataset.rsvpId = '7';
	cell.dataset.state = 'no-show';
	document.body.appendChild( cell );
	nodes.cell = cell;

	await act( async () => {
		await import( '@src/rsvp-admin' );
	} );
};

describe( 'rsvp-admin entry point', () => {
	beforeAll( mountAll );

	it( 'renders the filters from the mount point’s data', () => {
		const mount = within( nodes.full );

		expect( mount.getByLabelText( 'Pick an event' ) ).toBeInTheDocument();
		expect(
			mount.getByLabelText( 'Filter by response: Attending' ),
		).toBeInTheDocument();
	} );

	it( 'falls back to a default label when the mount point omits one', () => {
		expect(
			within( nodes.bare ).getByLabelText( 'Filter by event' ),
		).toBeInTheDocument();
	} );

	it( 'starts unfiltered when the request carried no filters', () => {
		expect(
			within( nodes.bare ).getByLabelText( 'Filter by response: all' ),
		).toBeInTheDocument();
	} );

	it( 'still renders the event picker when the statuses are malformed', () => {
		expect(
			within( nodes.malformed ).getByLabelText( 'Filter by event' ),
		).toBeInTheDocument();
	} );

	it( 'selects the check-in state carried in from the request', () => {
		expect(
			within( nodes.full ).getByLabelText( 'Filter by check-in' ),
		).toHaveValue( 'no-show' );
	} );

	it( 'offers only all check-ins when the states are malformed', () => {
		const select = within( nodes.malformed ).getByLabelText(
			'Filter by check-in',
		);

		expect( within( select ).getAllByRole( 'option' ) ).toHaveLength( 1 );
	} );

	it( 'turns a check-in cell into a dropdown', () => {
		expect(
			within( nodes.cell ).getByRole( 'button', {
				name: 'Check-in: No-show',
			} ),
		).toBeInTheDocument();
	} );

	it( 'mounts every mount point on the screen', () => {
		// `extra_tablenav()` prints one above the table and one below it.
		expect( screen.getAllByRole( 'button', { name: 'Filter' } ) ).toHaveLength(
			Object.keys( MOUNTS ).length,
		);
	} );
} );
