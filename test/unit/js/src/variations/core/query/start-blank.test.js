/**
 * Tests for the "Start blank" connected block variations.
 *
 * Guards the fix for #1801: every "Start blank" option must scaffold a
 * GatherPress event template (`gatherpress/event-date`) AND carry our own
 * pagination ("Event Pagination") and no-results ("No Event Results")
 * variations, instead of WP core's plain post blocks.
 */

/**
 * External dependencies
 */
import { describe, expect, it, jest } from '@jest/globals';

const registered = [];

jest.mock( '@wordpress/blocks', () => {
	const actual = jest.requireActual( '@wordpress/blocks' );
	return {
		...actual,
		registerBlockVariation: ( blockName, variation ) => {
			registered.push( { blockName, variation } );
		},
	};
} );

const {
	createBlocksFromInnerBlocksTemplate,
	registerBlockType,
	getBlockType,
} = jest.requireActual( '@wordpress/blocks' );

/**
 * Register a throwaway block type so createBlocksFromInnerBlocksTemplate can
 * instantiate the templates without a full WordPress environment.
 *
 * @param {string} name Block name to stub-register.
 */
const ensureBlock = ( name ) => {
	if ( ! getBlockType( name ) ) {
		registerBlockType( name, {
			apiVersion: 3,
			title: name,
			category: 'widgets',
			attributes: {},
			save: () => null,
			edit: () => null,
		} );
	}
};

[
	'core/query',
	'core/post-template',
	'core/post-title',
	'core/post-excerpt',
	'core/paragraph',
	'core/media-text',
	'core/query-pagination',
	'core/query-pagination-previous',
	'core/query-pagination-numbers',
	'core/query-pagination-next',
	'core/query-no-results',
	'gatherpress/event-date',
	'gatherpress/venue',
].forEach( ensureBlock );

// Importing the module triggers its registerBlockVariation() side effects.
require( '@src/variations/core/query/start-blank' );

// Only the four start-blank variations target core/query; the pagination and
// no-results modules imported as a side effect register against their own
// core blocks, so filter those out.
const startBlankVariations = registered
	.filter( ( r ) => 'core/query' === r.blockName )
	.map( ( r ) => r.variation );

const flattenNames = ( blocks ) => {
	const out = [];
	const walk = ( list ) => {
		for ( const block of list ) {
			out.push( block.name );
			walk( block.innerBlocks || [] );
		}
	};
	walk( blocks );
	return out;
};

describe( 'core/query "Start blank" variations', () => {
	it( 'registers four scoped variations against core/query', () => {
		expect( startBlankVariations ).toHaveLength( 4 );
	} );

	it( 'scopes every variation to the block (Start blank) picker only', () => {
		startBlankVariations.forEach( ( variation ) => {
			expect( variation.scope ).toEqual( [ 'block' ] );
		} );
	} );

	it( 'gives every variation a unique name and a title', () => {
		const names = startBlankVariations.map( ( v ) => v.name );
		expect( new Set( names ).size ).toBe( names.length );
		startBlankVariations.forEach( ( variation ) => {
			expect( typeof variation.title ).toBe( 'string' );
			expect( variation.title.length ).toBeGreaterThan( 0 );
		} );
	} );

	it( 'carries the event-query className without leaking string-spread keys', () => {
		startBlankVariations.forEach( ( variation ) => {
			expect( variation.attributes.className ).toBe(
				'gatherpress-event-query'
			);
			// A regression guard against spreading the className *string*,
			// which would scatter numeric-index keys ("0","1",...) across
			// the attributes object.
			Object.keys( variation.attributes ).forEach( ( key ) => {
				expect( key ).not.toMatch( /^\d+$/ );
			} );
		} );
	} );

	it( 'scaffolds an event template with pagination and no-results in each variation', () => {
		startBlankVariations.forEach( ( variation ) => {
			const blocks = createBlocksFromInnerBlocksTemplate(
				variation.innerBlocks
			);
			const all = flattenNames( blocks );

			// Top level holds the post-template plus the two query siblings.
			expect( blocks.map( ( b ) => b.name ) ).toEqual( [
				'core/post-template',
				'core/query-pagination',
				'core/query-no-results',
			] );

			// The regression guard: our blocks, not core's plain post blocks.
			expect( all ).toContain( 'gatherpress/event-date' );
			expect( all ).toContain( 'core/query-pagination' );
			expect( all ).toContain( 'core/query-no-results' );
		} );
	} );
} );
