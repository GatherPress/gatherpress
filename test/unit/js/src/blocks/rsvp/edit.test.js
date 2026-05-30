/**
 * External dependencies
 */
import { describe, expect, it } from '@jest/globals';

/**
 * Internal dependencies
 */
import { parseSerializedInnerBlocks } from '@src/blocks/rsvp/edit';

/**
 * Regression coverage for #1704 — "RSVP block has encountered an error and
 * cannot be previewed".
 *
 * The `serializedInnerBlocks` attribute stores serialized block markup inside a
 * block attribute (double-nested JSON). When something rewrites that stored
 * string blindly — e.g. the GatherPress Alpha migration runs raw SQL
 * `REPLACE()` over the escaped class names inside `serializedInnerBlocks` — an
 * escape sequence can be mangled, leaving the attribute as invalid JSON. The
 * editor then hit an unguarded `JSON.parse` on every render and crashed the
 * whole block into Gutenberg's error boundary.
 */
describe( 'parseSerializedInnerBlocks', () => {
	it( 'parses a valid status→markup map', () => {
		const value = JSON.stringify( {
			no_status: '<!-- wp:gatherpress/modal-manager -->',
			attending: '<!-- wp:core/paragraph -->',
		} );

		expect( parseSerializedInnerBlocks( value ) ).toEqual( {
			no_status: '<!-- wp:gatherpress/modal-manager -->',
			attending: '<!-- wp:core/paragraph -->',
		} );
	} );

	it( 'returns {} for the malformed payload from #1704 instead of throwing', () => {
		// Mirrors the failure signature in the issue: a dropped escape leaves a
		// bare double-quote mid-string, so the raw parse explodes with
		// "Expected ',' or '}' after property value".
		const corrupt =
			'{"no_status":"<!-- wp:gatherpress/modal"manager -->"}';

		// Confirm this genuinely reproduces the crash with a raw JSON.parse.
		expect( () => JSON.parse( corrupt ) ).toThrow( SyntaxError );

		// The guarded helper swallows it and degrades to an empty map.
		expect( parseSerializedInnerBlocks( corrupt ) ).toEqual( {} );
	} );

	it( 'returns {} for an empty string', () => {
		expect( parseSerializedInnerBlocks( '' ) ).toEqual( {} );
	} );

	it( 'returns {} for undefined', () => {
		expect( parseSerializedInnerBlocks( undefined ) ).toEqual( {} );
	} );

	it( 'returns {} for the legacy "[]" default (array, not a status map)', () => {
		// block.json defaults the attribute to "[]"; a parsed array is valid
		// JSON but not a status map, so callers must still get an object.
		expect( parseSerializedInnerBlocks( '[]' ) ).toEqual( {} );
	} );

	it( 'returns {} for valid JSON that is not an object (e.g. null/number)', () => {
		expect( parseSerializedInnerBlocks( 'null' ) ).toEqual( {} );
		expect( parseSerializedInnerBlocks( '42' ) ).toEqual( {} );
	} );
} );
