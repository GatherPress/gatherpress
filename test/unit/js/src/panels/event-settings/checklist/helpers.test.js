/**
 * WordPress dependencies
 */
import { describe, expect, it, jest } from '@jest/globals';

jest.mock( 'uuid', () => ( {
	v4: jest.fn( () => 'generated-id' ),
} ) );

/**
 * Internal dependencies
 */
import {
	MAX_CHECKLIST_ID_LENGTH,
	MAX_CHECKLIST_ITEMS,
	MAX_CHECKLIST_TEXT_LENGTH,
	addChecklistItem,
	createChecklistItem,
	getChecklistProgress,
	moveChecklistItem,
	parseChecklist,
	removeChecklistItem,
	sanitizeChecklistItem,
	serializeChecklist,
	updateChecklistItem,
} from '@src/panels/event-settings/checklist/helpers';

describe( 'Checklist helpers', () => {
	describe( 'sanitizeChecklistItem', () => {
		it( 'returns null for a falsy entry', () => {
			expect( sanitizeChecklistItem( null ) ).toBeNull();
		} );

		it( 'returns null for a non-object entry', () => {
			expect( sanitizeChecklistItem( 'a string' ) ).toBeNull();
		} );

		it( 'returns null when the id is missing', () => {
			expect( sanitizeChecklistItem( { text: 'No id' } ) ).toBeNull();
		} );

		it( 'returns null when the id is blank', () => {
			expect( sanitizeChecklistItem( { id: '   ' } ) ).toBeNull();
		} );

		it( 'returns null when the id is over the cap', () => {
			expect(
				sanitizeChecklistItem( {
					id: 'i'.repeat( MAX_CHECKLIST_ID_LENGTH + 1 ),
				} )
			).toBeNull();
		} );

		it( 'keeps an id exactly at the cap', () => {
			const id = 'i'.repeat( MAX_CHECKLIST_ID_LENGTH );

			expect( sanitizeChecklistItem( { id } ).id ).toBe( id );
		} );

		it( 'measures the id cap in codepoints, like mb_strlen', () => {
			// An astral-plane character is one codepoint but two UTF-16 units,
			// so 40 of them are 40 codepoints and 80 `.length`. A naive
			// `.length` check would wrongly drop this id.
			const codepoints = ( MAX_CHECKLIST_ID_LENGTH / 2 ) + 8;
			const id = '\u{1f600}'.repeat( codepoints );

			expect( Array.from( id ) ).toHaveLength( codepoints );
			expect( id.length ).toBeGreaterThan( MAX_CHECKLIST_ID_LENGTH );
			expect( sanitizeChecklistItem( { id } ).id ).toBe( id );
		} );

		it( 'normalizes an id to a string', () => {
			expect( sanitizeChecklistItem( { id: 42 } ) ).toEqual( {
				id: '42',
				text: '',
				completed: false,
			} );
		} );

		it( 'defaults a non-string text to an empty string', () => {
			expect( sanitizeChecklistItem( { id: 'a', text: 7 } ).text ).toBe(
				''
			);
		} );

		it( 'trims text over the cap', () => {
			const item = sanitizeChecklistItem( {
				id: 'a',
				text: 'x'.repeat( MAX_CHECKLIST_TEXT_LENGTH + 10 ),
			} );

			expect( item.text ).toHaveLength( MAX_CHECKLIST_TEXT_LENGTH );
		} );

		it( 'keeps every character when the text is exactly at the cap', () => {
			const text = 'x'.repeat( MAX_CHECKLIST_TEXT_LENGTH );

			expect( sanitizeChecklistItem( { id: 'a', text } ).text ).toBe(
				text
			);
		} );

		it( 'coerces a truthy completed value to true', () => {
			expect( sanitizeChecklistItem( { id: 'a', completed: 1 } ) ).toEqual(
				{
					id: 'a',
					text: '',
					completed: true,
				}
			);
		} );

		it( 'coerces a falsy completed value to false', () => {
			expect(
				sanitizeChecklistItem( { id: 'a', completed: 0 } ).completed
			).toBe( false );
		} );

		it( 'reads the string "false" as not complete', () => {
			expect(
				sanitizeChecklistItem( { id: 'a', completed: 'false' } ).completed
			).toBe( false );
		} );

		it( 'reads the string "0" as not complete', () => {
			expect(
				sanitizeChecklistItem( { id: 'a', completed: '0' } ).completed
			).toBe( false );
		} );

		it( 'reads the string "FALSE" as not complete', () => {
			expect(
				sanitizeChecklistItem( { id: 'a', completed: 'FALSE' } ).completed
			).toBe( false );
		} );

		it( 'reads the string "1" as complete', () => {
			expect(
				sanitizeChecklistItem( { id: 'a', completed: '1' } ).completed
			).toBe( true );
		} );
	} );

	describe( 'parseChecklist', () => {
		it( 'returns an empty list for a non-string value', () => {
			expect( parseChecklist( null ) ).toEqual( [] );
		} );

		it( 'returns an empty list for a blank string', () => {
			expect( parseChecklist( '   ' ) ).toEqual( [] );
		} );

		it( 'returns an empty list for malformed JSON', () => {
			expect( parseChecklist( '[{"id":"a"' ) ).toEqual( [] );
		} );

		it( 'returns an empty list when the JSON is not an array', () => {
			expect( parseChecklist( '{"id":"a"}' ) ).toEqual( [] );
		} );

		it( 'parses a stored checklist', () => {
			expect(
				parseChecklist(
					'[{"id":"a","text":"Ask","completed":true}]'
				)
			).toEqual( [ { id: 'a', text: 'Ask', completed: true } ] );
		} );

		it( 'drops unusable entries while parsing', () => {
			expect(
				parseChecklist( '["string",{"text":"no id"},{"id":"kept"}]' )
			).toEqual( [ { id: 'kept', text: '', completed: false } ] );
		} );

		it( 'caps the parsed list', () => {
			const stored = JSON.stringify(
				Array.from( { length: MAX_CHECKLIST_ITEMS + 5 }, ( _, i ) => ( {
					id: `item-${ i }`,
				} ) )
			);

			expect( parseChecklist( stored ) ).toHaveLength(
				MAX_CHECKLIST_ITEMS
			);
		} );
	} );

	describe( 'serializeChecklist', () => {
		it( 'encodes items as JSON', () => {
			expect(
				serializeChecklist( [
					{ id: 'a', text: 'Ask', completed: false },
				] )
			).toBe( '[{"id":"a","text":"Ask","completed":false}]' );
		} );

		it( 'encodes a non-array value as an empty checklist', () => {
			expect( serializeChecklist( null ) ).toBe( '[]' );
		} );

		it( 'drops unusable items before encoding', () => {
			expect(
				serializeChecklist( [ { text: 'no id' }, { id: 'kept' } ] )
			).toBe( '[{"id":"kept","text":"","completed":false}]' );
		} );

		it( 'caps the encoded list', () => {
			const items = Array.from( { length: MAX_CHECKLIST_ITEMS + 5 }, ( _, i ) => ( {
				id: `item-${ i }`,
			} ) );

			expect( JSON.parse( serializeChecklist( items ) ) ).toHaveLength(
				MAX_CHECKLIST_ITEMS
			);
		} );
	} );

	describe( 'createChecklistItem', () => {
		it( 'creates an empty, unchecked item', () => {
			expect( createChecklistItem() ).toEqual( {
				id: 'generated-id',
				text: '',
				completed: false,
			} );
		} );

		it( 'keeps the given text', () => {
			expect( createChecklistItem( 'Ask' ).text ).toBe( 'Ask' );
		} );
	} );

	describe( 'getChecklistProgress', () => {
		it( 'counts completed items', () => {
			expect(
				getChecklistProgress( [
					{ id: 'a', completed: true },
					{ id: 'b', completed: false },
					{ id: 'c', completed: true },
				] )
			).toEqual( { completed: 2, total: 3 } );
		} );

		it( 'handles a non-array value', () => {
			expect( getChecklistProgress( null ) ).toEqual( {
				completed: 0,
				total: 0,
			} );
		} );

		it( 'handles a falsy item in the list', () => {
			expect( getChecklistProgress( [ null ] ) ).toEqual( {
				completed: 0,
				total: 1,
			} );
		} );
	} );

	describe( 'addChecklistItem', () => {
		it( 'appends a new item', () => {
			const items = addChecklistItem( [ { id: 'a', text: 'Ask' } ], 'Pay' );

			expect( items ).toHaveLength( 2 );
			expect( items[ 1 ] ).toEqual( {
				id: 'generated-id',
				text: 'Pay',
				completed: false,
			} );
		} );

		it( 'returns the same list when it is already full', () => {
			const full = Array.from( { length: MAX_CHECKLIST_ITEMS }, ( _, i ) => ( {
				id: `item-${ i }`,
			} ) );

			expect( addChecklistItem( full, 'Pay' ) ).toBe( full );
		} );

		it( 'handles a non-array value', () => {
			expect( addChecklistItem( null ) ).toHaveLength( 1 );
		} );
	} );

	describe( 'updateChecklistItem', () => {
		it( 'merges the change into the matching item', () => {
			expect(
				updateChecklistItem(
					[
						{ id: 'a', text: 'Ask', completed: false },
						{ id: 'b', text: 'Pay', completed: false },
					],
					'b',
					{ completed: true }
				)
			).toEqual( [
				{ id: 'a', text: 'Ask', completed: false },
				{ id: 'b', text: 'Pay', completed: true },
			] );
		} );

		it( 'leaves the list alone when no item matches', () => {
			const items = [ { id: 'a', text: 'Ask', completed: false } ];

			expect( updateChecklistItem( items, 'missing', {} ) ).toEqual(
				items
			);
		} );

		it( 'handles a non-array value', () => {
			expect( updateChecklistItem( null, 'a', {} ) ).toEqual( [] );
		} );
	} );

	describe( 'removeChecklistItem', () => {
		it( 'drops the matching item', () => {
			const items = removeChecklistItem(
				[ { id: 'a' }, { id: 'b' } ],
				'a'
			);

			expect( items ).toEqual( [ { id: 'b' } ] );
		} );

		it( 'handles a non-array value', () => {
			expect( removeChecklistItem( null, 'a' ) ).toEqual( [] );
		} );
	} );

	describe( 'moveChecklistItem', () => {
		const items = [ { id: 'a' }, { id: 'b' }, { id: 'c' } ];

		it( 'moves an item up', () => {
			expect( moveChecklistItem( items, 'b', -1 ).map( ( i ) => i.id ) ).toEqual(
				[ 'b', 'a', 'c' ]
			);
		} );

		it( 'moves an item down', () => {
			expect( moveChecklistItem( items, 'b', 1 ).map( ( i ) => i.id ) ).toEqual(
				[ 'a', 'c', 'b' ]
			);
		} );

		it( 'leaves the list alone when the id is unknown', () => {
			expect( moveChecklistItem( items, 'missing', 1 ) ).toBe( items );
		} );

		it( 'leaves the list alone when moving the first item up', () => {
			expect( moveChecklistItem( items, 'a', -1 ) ).toBe( items );
		} );

		it( 'leaves the list alone when moving the last item down', () => {
			expect( moveChecklistItem( items, 'c', 1 ) ).toBe( items );
		} );

		it( 'handles a non-array value', () => {
			expect( moveChecklistItem( null, 'a', 1 ) ).toEqual( [] );
		} );
	} );
} );
