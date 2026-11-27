/**
 * External dependencies.
 */
import { describe, expect, it, jest } from '@jest/globals';

jest.mock( '@wordpress/blocks', () => ( {
	registerBlockVariation: jest.fn(),
} ) );

/**
 * WordPress dependencies.
 */
import { registerBlockVariation } from '@wordpress/blocks';

/**
 * Internal dependencies.
 */
import QUERY_VARIATION from '@src/blocks/calendar/templates/query-variation';
// Importing the module registers the variations as a side effect.
import '@src/blocks/calendar/variations/pagination';

// Read the calls before any test can clear them.
const calls = [ ...registerBlockVariation.mock.calls ];

/**
 * Find a registered variation by the block it was registered on.
 *
 * @param {string} blockName Block name.
 *
 * @return {Object} The variation registered on that block.
 */
const variationOf = ( blockName ) =>
	calls.find( ( [ name ] ) => blockName === name )[ 1 ];

const pagination = variationOf( 'core/query-pagination' );
const previous = variationOf( 'core/query-pagination-previous' );
const next = variationOf( 'core/query-pagination-next' );

const CONTAINER_CLASS = 'gatherpress-calendar-pagination';
const PREVIOUS_CLASS = 'gatherpress-calendar-pagination-previous';
const NEXT_CLASS = 'gatherpress-calendar-pagination-next';

describe( 'calendar pagination variations', () => {
	it( 'registers one variation on each query pagination block, in order', () => {
		expect( calls.map( ( [ name ] ) => name ) ).toEqual( [
			'core/query-pagination',
			'core/query-pagination-previous',
			'core/query-pagination-next',
		] );
		expect( calls.map( ( [ , variation ] ) => variation.name ) ).toEqual( [
			'gatherpress-calendar-pagination',
			'gatherpress-calendar-pagination-previous',
			'gatherpress-calendar-pagination-next',
		] );
	} );

	it( 'shows every variation in the inserter, in the GatherPress category', () => {
		[ pagination, previous, next ].forEach( ( variation ) => {
			expect( variation.category ).toBe( 'gatherpress' );
			expect( variation.scope ).toEqual( [ 'inserter' ] );
			expect( variation.keywords ).toContain( 'calendar' );
		} );
		expect( pagination.title ).toBe( 'Calendar Pagination' );
		expect( previous.title ).toBe( 'Previous Calendar Period' );
		expect( next.title ).toBe( 'Next Calendar Period' );
	} );

	describe( 'Calendar Pagination', () => {
		it( 'lays out chevron arrows at both ends of a flex row', () => {
			expect( pagination.attributes ).toEqual( {
				className: CONTAINER_CLASS,
				paginationArrow: 'chevron',
				layout: { type: 'flex', justifyContent: 'space-between' },
				metadata: { name: 'Calendar Pagination' },
			} );
		} );

		it( 'holds a Previous Month link and then a Next Month link', () => {
			expect( pagination.innerBlocks ).toEqual( [
				[
					'core/query-pagination-previous',
					{
						className: PREVIOUS_CLASS,
						label: 'Previous Month',
						metadata: { name: 'Previous Period' },
					},
				],
				[
					'core/query-pagination-next',
					{
						className: NEXT_CLASS,
						label: 'Next Month',
						metadata: { name: 'Next Period' },
					},
				],
			] );
		} );

		it( 'holds links that are the Previous and Next Calendar Period variations', () => {
			const [ previousLink, nextLink ] = pagination.innerBlocks;

			expect(
				previous.isActive( previousLink[ 1 ], previous.attributes ),
			).toBe( true );
			expect( next.isActive( nextLink[ 1 ], next.attributes ) ).toBe(
				true,
			);
		} );
	} );

	describe( 'Previous and Next Calendar Period', () => {
		it( 'labels the previous link Previous Month', () => {
			expect( previous.attributes ).toEqual( {
				className: PREVIOUS_CLASS,
				label: 'Previous Month',
				metadata: { name: 'Previous Calendar Period' },
			} );
		} );

		it( 'labels the next link Next Month', () => {
			expect( next.attributes ).toEqual( {
				className: NEXT_CLASS,
				label: 'Next Month',
				metadata: { name: 'Next Calendar Period' },
			} );
		} );
	} );

	describe.each( [
		[ 'Calendar Pagination', pagination, CONTAINER_CLASS, [ PREVIOUS_CLASS, NEXT_CLASS ] ],
		[ 'Previous Calendar Period', previous, PREVIOUS_CLASS, [ CONTAINER_CLASS, NEXT_CLASS ] ],
		[ 'Next Calendar Period', next, NEXT_CLASS, [ CONTAINER_CLASS, PREVIOUS_CLASS ] ],
	] )( '%s isActive', ( label, variation, ownClass, otherClasses ) => {
		const isActive = ( blockAttributes ) =>
			variation.isActive( blockAttributes, variation.attributes );

		it( 'is active for a block with the variation class', () => {
			expect( isActive( { className: ownClass } ) ).toBe( true );
		} );

		it( 'is active when the variation class is one of several classes', () => {
			expect(
				isActive( { className: `is-style-outline ${ ownClass } extra` } ),
			).toBe( true );
		} );

		it( 'is not active for the class of another calendar pagination variation', () => {
			otherClasses.forEach( ( className ) => {
				expect( isActive( { className } ) ).toBe( false );
			} );
		} );

		it( 'is not active for a block with other classes', () => {
			expect( isActive( { className: 'wp-block-query-pagination' } ) ).toBe(
				false,
			);
		} );

		it( 'is not active for a block with no class', () => {
			expect( isActive( { label: 'Next Page' } ) ).toBe( false );
			expect( isActive( { className: '' } ) ).toBe( false );
		} );

		it( 'is not active when the block has no attributes', () => {
			expect( isActive( undefined ) ).toBe( false );
		} );
	} );

	it( 'recognizes the pagination blocks of the Event Calendar query as these variations', () => {
		const [ , [ containerName, containerAttributes, links ] ] =
			QUERY_VARIATION;
		const [ [ , previousAttributes ], [ , nextAttributes ] ] = links;

		expect( containerName ).toBe( 'core/query-pagination' );
		expect(
			pagination.isActive( containerAttributes, pagination.attributes ),
		).toBe( true );
		expect(
			previous.isActive( previousAttributes, previous.attributes ),
		).toBe( true );
		expect( next.isActive( nextAttributes, next.attributes ) ).toBe( true );
	} );
} );
