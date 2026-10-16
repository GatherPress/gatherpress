/**
 * External dependencies
 */
import { describe, expect, it } from '@jest/globals';

/**
 * Internal dependencies
 */
import {
	getRsvpFilterStatus,
	resolveRsvpFilterCount,
} from '@src/blocks/rsvp-response/filters/dropdown-item-count';
import { RSVP_COUNTS_STORE } from '@src/helpers/namespace';

/**
 * Builds a `select` stand-in for the stores the handler reads.
 *
 * @param {Object} counts        Counts keyed by status.
 * @param {number} currentPostId The post `core/editor` reports as current.
 *
 * @return {Function} A select function.
 */
const selectWith = ( counts, currentPostId = null ) => ( storeName ) => {
	if ( 'core/editor' === storeName ) {
		return { getCurrentPostId: () => currentPostId };
	}

	if ( RSVP_COUNTS_STORE !== storeName ) {
		throw new Error( `Unexpected store: ${ storeName }` );
	}

	return {
		getCount: ( postId, status ) => counts[ status ]?.count ?? null,
	};
};

describe( 'getRsvpFilterStatus', () => {
	it( 'maps each seeded filter class to its status', () => {
		expect( getRsvpFilterStatus( 'gatherpress--is-attending' ) ).toBe(
			'attending',
		);
		expect( getRsvpFilterStatus( 'gatherpress--is-waiting-list' ) ).toBe(
			'waiting_list',
		);
		expect( getRsvpFilterStatus( 'gatherpress--is-not-attending' ) ).toBe(
			'not_attending',
		);
	} );

	it( 'finds the class among others', () => {
		expect(
			getRsvpFilterStatus( 'is-style-foo gatherpress--is-attending bar' ),
		).toBe( 'attending' );
	} );

	it( 'returns null for an unrelated, empty, or missing class list', () => {
		expect( getRsvpFilterStatus( 'some-other-class' ) ).toBeNull();
		expect( getRsvpFilterStatus( '' ) ).toBeNull();
		expect( getRsvpFilterStatus( undefined ) ).toBeNull();
		expect( getRsvpFilterStatus( null ) ).toBeNull();
	} );
} );

describe( 'resolveRsvpFilterCount', () => {
	const attributes = { className: 'gatherpress--is-attending' };
	const context = { postId: 42 };

	it( 'substitutes the resolved count', () => {
		expect(
			resolveRsvpFilterCount( '<a href="#">Attending (%d)</a>', {
				attributes,
				context,
				select: selectWith( { attending: { count: 3 } } ),
			} ),
		).toBe( '<a href="#">Attending (3)</a>' );
	} );

	it( 'substitutes a zero count rather than treating it as unresolved', () => {
		expect(
			resolveRsvpFilterCount( 'Attending (%d)', {
				attributes,
				context,
				select: selectWith( { attending: { count: 0 } } ),
			} ),
		).toBe( 'Attending (0)' );
	} );

	it( 'leaves text without a placeholder alone', () => {
		expect(
			resolveRsvpFilterCount( 'Attending', {
				attributes,
				context,
				select: selectWith( { attending: { count: 3 } } ),
			} ),
		).toBe( 'Attending' );
	} );

	it( 'leaves a non-filter item alone', () => {
		expect(
			resolveRsvpFilterCount( 'Anything (%d)', {
				attributes: { className: 'not-a-filter' },
				context,
				select: selectWith( { attending: { count: 3 } } ),
			} ),
		).toBe( 'Anything (%d)' );
	} );

	it( 'falls back to the post being edited when context has no postId', () => {
		// Block context only carries postId inside a Query Loop.
		expect(
			resolveRsvpFilterCount( 'Attending (%d)', {
				attributes,
				context: {},
				select: selectWith( { attending: { count: 3 } }, 42 ),
			} ),
		).toBe( 'Attending (3)' );
	} );

	it( 'leaves the placeholder when no post can be resolved at all', () => {
		expect(
			resolveRsvpFilterCount( 'Attending (%d)', {
				attributes,
				context: {},
				select: selectWith( { attending: { count: 3 } } ),
			} ),
		).toBe( 'Attending (%d)' );
	} );

	it( 'leaves the placeholder until the count resolves', () => {
		expect(
			resolveRsvpFilterCount( 'Attending (%d)', {
				attributes,
				context,
				select: selectWith( {} ),
			} ),
		).toBe( 'Attending (%d)' );
	} );

	it( 'tolerates missing text', () => {
		expect(
			resolveRsvpFilterCount( undefined, {
				attributes,
				context,
				select: selectWith( { attending: { count: 3 } } ),
			} ),
		).toBeUndefined();
	} );
} );
