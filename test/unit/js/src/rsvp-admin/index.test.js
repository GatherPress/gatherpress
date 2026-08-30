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

const MOUNTS = {
	// Everything the list table can hand over.
	full: {
		postTypes: 'gatherpress_event',
		postId: '11',
		label: 'Pick an event',
		statuses: STATUSES,
		selected: 'attending',
	},
	// Nothing at all, which is what an unfiltered first visit prints.
	bare: {},
	// A truncated payload, which should cost the response filter its options
	// rather than take the screen's filters down with it.
	malformed: { postTypes: 'gatherpress_event', statuses: '{"attending"' },
};

const nodes = {};

/**
 * Runs the entry point once against every mount point.
 *
 * The module mounts on import rather than exporting anything: it is the
 * screen's entry point, and `domReady` fires straight away because jsdom is
 * already complete. It is imported once, with every case present, because
 * resetting the registry to import it again would load a second copy of React
 * whose pending work `act` cannot flush.
 *
 * @return {Promise<void>} Resolves once React has settled.
 */
const mountAll = async () => {
	Object.entries( MOUNTS ).forEach( ( [ name, dataset ] ) => {
		const node = document.createElement( 'div' );

		node.className = 'gatherpress-rsvp-filters-mount';
		Object.assign( node.dataset, dataset );
		document.body.appendChild( node );

		nodes[ name ] = node;
	} );

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
			mount.getByLabelText( 'Filter by response: Attending' )
		).toBeInTheDocument();
	} );

	it( 'falls back to a default label when the mount point omits one', () => {
		expect(
			within( nodes.bare ).getByLabelText( 'Filter by event' )
		).toBeInTheDocument();
	} );

	it( 'starts unfiltered when the request carried no filters', () => {
		expect(
			within( nodes.bare ).getByLabelText( 'Filter by response: all' )
		).toBeInTheDocument();
	} );

	it( 'still renders the event picker when the statuses are malformed', () => {
		expect(
			within( nodes.malformed ).getByLabelText( 'Filter by event' )
		).toBeInTheDocument();
	} );

	it( 'mounts every mount point on the screen', () => {
		// `extra_tablenav()` prints one above the table and one below it, so
		// mounting only the first would leave the other tablenav bare.
		expect( screen.getAllByRole( 'button', { name: 'Filter' } ) ).toHaveLength(
			Object.keys( MOUNTS ).length
		);
	} );
} );
