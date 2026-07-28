/**
 * External dependencies
 */
import { describe, expect, it } from '@jest/globals';

/**
 * Internal dependencies
 */
import { calculateMode, getNewTaxonomyIds } from '@src/blocks/venue/helpers';

describe( 'venue helpers', () => {
	const onlineEventTermId = 99;

	describe( 'calculateMode', () => {
		it( 'should return "in-person" when terms array is empty', () => {
			expect( calculateMode( [], onlineEventTermId ) ).toBe( 'in-person' );
		} );

		it( 'should return "in-person" when terms is null', () => {
			expect( calculateMode( null, onlineEventTermId ) ).toBe( 'in-person' );
		} );

		it( 'should return "in-person" when terms is undefined', () => {
			expect( calculateMode( undefined, onlineEventTermId ) ).toBe(
				'in-person'
			);
		} );

		it( 'should return "in-person" when only venue terms exist', () => {
			const terms = [
				{ id: 1, slug: 'my-venue', name: 'My Venue' },
			];
			expect( calculateMode( terms, onlineEventTermId ) ).toBe( 'in-person' );
		} );

		it( 'should return "in-person" when multiple venue terms exist (no online)', () => {
			const terms = [
				{ id: 1, slug: 'venue-one', name: 'Venue One' },
				{ id: 2, slug: 'venue-two', name: 'Venue Two' },
			];
			expect( calculateMode( terms, onlineEventTermId ) ).toBe( 'in-person' );
		} );

		it( 'should return "online" when only online-event term exists', () => {
			const terms = [
				{ id: 99, slug: 'online-event', name: 'Online Event' },
			];
			expect( calculateMode( terms, onlineEventTermId ) ).toBe( 'online' );
		} );

		it( 'should return "hybrid" when both venue and online-event terms exist', () => {
			const terms = [
				{ id: 1, slug: 'my-venue', name: 'My Venue' },
				{ id: 99, slug: 'online-event', name: 'Online Event' },
			];
			expect( calculateMode( terms, onlineEventTermId ) ).toBe( 'hybrid' );
		} );

		it( 'should return "hybrid" when multiple venues and online-event exist', () => {
			const terms = [
				{ id: 1, slug: 'venue-one', name: 'Venue One' },
				{ id: 2, slug: 'venue-two', name: 'Venue Two' },
				{ id: 99, slug: 'online-event', name: 'Online Event' },
			];
			expect( calculateMode( terms, onlineEventTermId ) ).toBe( 'hybrid' );
		} );

		// Drives the `null === sentinel || Number( term.id ) !== sentinel` branch
		// when no sentinel is known — every term is treated as a venue.
		it( 'should return "in-person" when sentinel is null and only venue terms exist', () => {
			const terms = [
				{ id: 1, slug: 'my-venue', name: 'My Venue' },
				{ id: 2, slug: 'other-venue', name: 'Other Venue' },
			];
			expect( calculateMode( terms, null ) ).toBe( 'in-person' );
		} );

		// Drives the `Number( onlineEventTermId ) || null` fallback when
		// callers pass a falsy numeric sentinel (0 or empty string).
		it( 'should return "in-person" when sentinel is 0 and a venue term exists', () => {
			const terms = [
				{ id: 1, slug: 'my-venue', name: 'My Venue' },
			];
			expect( calculateMode( terms, 0 ) ).toBe( 'in-person' );
		} );
	} );

	describe( 'getNewTaxonomyIds', () => {
		const onlineEventTermId = 99;
		const venueTermId = 42;

		describe( 'in-person mode', () => {
			it( 'should return only venue term ID when venue exists', () => {
				const result = getNewTaxonomyIds( 'in-person', onlineEventTermId, venueTermId );
				expect( result ).toEqual( [ 42 ] );
			} );

			it( 'should return empty array when no venue exists', () => {
				const result = getNewTaxonomyIds( 'in-person', onlineEventTermId, null );
				expect( result ).toEqual( [] );
			} );

			it( 'should return empty array when venue is undefined', () => {
				const result = getNewTaxonomyIds( 'in-person', onlineEventTermId, undefined );
				expect( result ).toEqual( [] );
			} );
		} );

		describe( 'online mode', () => {
			it( 'should return only online-event term ID when it exists', () => {
				const result = getNewTaxonomyIds( 'online', onlineEventTermId, venueTermId );
				expect( result ).toEqual( [ 99 ] );
			} );

			it( 'should return empty array when online-event term does not exist', () => {
				const result = getNewTaxonomyIds( 'online', null, venueTermId );
				expect( result ).toEqual( [] );
			} );

			it( 'should return empty array when online-event term is undefined', () => {
				const result = getNewTaxonomyIds( 'online', undefined, venueTermId );
				expect( result ).toEqual( [] );
			} );
		} );

		describe( 'hybrid mode', () => {
			it( 'should return both venue and online-event IDs when both exist', () => {
				const result = getNewTaxonomyIds( 'hybrid', onlineEventTermId, venueTermId );
				expect( result ).toEqual( [ 42, 99 ] );
			} );

			it( 'should return only venue ID when online-event does not exist', () => {
				const result = getNewTaxonomyIds( 'hybrid', null, venueTermId );
				expect( result ).toEqual( [ 42 ] );
			} );

			it( 'should return only online-event ID when venue does not exist', () => {
				const result = getNewTaxonomyIds( 'hybrid', onlineEventTermId, null );
				expect( result ).toEqual( [ 99 ] );
			} );

			it( 'should return empty array when neither exists', () => {
				const result = getNewTaxonomyIds( 'hybrid', null, null );
				expect( result ).toEqual( [] );
			} );

			it( 'should return empty array when both are undefined', () => {
				const result = getNewTaxonomyIds( 'hybrid', undefined, undefined );
				expect( result ).toEqual( [] );
			} );

			it( 'should maintain correct order (venue first, then online)', () => {
				const result = getNewTaxonomyIds( 'hybrid', onlineEventTermId, venueTermId );
				expect( result[ 0 ] ).toBe( 42 ); // Venue first.
				expect( result[ 1 ] ).toBe( 99 ); // Online second.
			} );
		} );

		describe( 'edge cases', () => {
			it( 'should handle zero as valid term ID for in-person mode', () => {
				const result = getNewTaxonomyIds( 'in-person', onlineEventTermId, 0 );
				expect( result ).toEqual( [] );
			} );

			it( 'should handle zero as valid term ID for online mode', () => {
				const result = getNewTaxonomyIds( 'online', 0, venueTermId );
				expect( result ).toEqual( [] );
			} );

			it( 'should handle string numbers for in-person mode', () => {
				const result = getNewTaxonomyIds( 'in-person', onlineEventTermId, 42 );
				expect( result ).toEqual( [ 42 ] );
			} );

			it( 'should handle unknown mode by defaulting to hybrid logic', () => {
				const result = getNewTaxonomyIds( 'unknown-mode', onlineEventTermId, venueTermId );
				expect( result ).toEqual( [ 42, 99 ] );
			} );
		} );
	} );
} );
