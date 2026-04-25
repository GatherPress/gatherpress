/**
 * External dependencies.
 */
import { describe, expect, it } from '@jest/globals';

/**
 * Internal dependencies.
 */
import {
	VENUE_FIELDS,
	cleanUrlForDisplay,
	getMetaKey,
} from '@src/blocks/venue-detail/helpers';

describe( 'Venue Detail helpers', () => {
	describe( 'VENUE_FIELDS', () => {
		it( 'should pair the address fieldType with gatherpress_address', () => {
			expect(
				VENUE_FIELDS.find( ( f ) => 'address' === f.fieldType )?.metaKey
			).toBe( 'gatherpress_address' );
		} );

		it( 'should pair the phone fieldType with gatherpress_phone', () => {
			expect(
				VENUE_FIELDS.find( ( f ) => 'phone' === f.fieldType )?.metaKey
			).toBe( 'gatherpress_phone' );
		} );

		it( 'should pair the url fieldType with gatherpress_website', () => {
			expect(
				VENUE_FIELDS.find( ( f ) => 'url' === f.fieldType )?.metaKey
			).toBe( 'gatherpress_website' );
		} );
	} );

	describe( 'cleanUrlForDisplay', () => {
		it( 'should return empty string for empty input', () => {
			expect( cleanUrlForDisplay( '' ) ).toBe( '' );
		} );

		it( 'should return empty string for null input', () => {
			expect( cleanUrlForDisplay( null ) ).toBe( '' );
		} );

		it( 'should return empty string for undefined input', () => {
			expect( cleanUrlForDisplay( undefined ) ).toBe( '' );
		} );

		it( 'should remove https protocol', () => {
			expect( cleanUrlForDisplay( 'https://example.com' ) ).toBe(
				'example.com'
			);
		} );

		it( 'should remove http protocol', () => {
			expect( cleanUrlForDisplay( 'http://example.com' ) ).toBe(
				'example.com'
			);
		} );

		it( 'should remove www prefix', () => {
			expect( cleanUrlForDisplay( 'https://www.example.com' ) ).toBe(
				'example.com'
			);
		} );

		it( 'should remove trailing slash', () => {
			expect( cleanUrlForDisplay( 'https://example.com/' ) ).toBe(
				'example.com'
			);
		} );

		it( 'should handle url with path', () => {
			expect(
				cleanUrlForDisplay( 'https://www.example.com/page/' )
			).toBe( 'example.com/page' );
		} );

		it( 'should handle url without protocol', () => {
			expect( cleanUrlForDisplay( 'www.example.com/' ) ).toBe(
				'example.com'
			);
		} );

		it( 'should preserve internal path slashes', () => {
			expect(
				cleanUrlForDisplay( 'https://example.com/path/to/page' )
			).toBe( 'example.com/path/to/page' );
		} );
	} );

	describe( 'getMetaKey', () => {
		it( 'should return gatherpress_address for address field type', () => {
			expect( getMetaKey( 'address' ) ).toBe(
				'gatherpress_address'
			);
		} );

		it( 'should return gatherpress_phone for phone field type', () => {
			expect( getMetaKey( 'phone' ) ).toBe( 'gatherpress_phone' );
		} );

		it( 'should return gatherpress_website for url field type', () => {
			expect( getMetaKey( 'url' ) ).toBe( 'gatherpress_website' );
		} );

		it( 'should return empty string for unknown field type', () => {
			expect( getMetaKey( 'unknown' ) ).toBe( '' );
		} );

		it( 'should return empty string for text field type', () => {
			expect( getMetaKey( 'text' ) ).toBe( '' );
		} );

		it( 'should return empty string for empty string', () => {
			expect( getMetaKey( '' ) ).toBe( '' );
		} );

		it( 'should return empty string for null', () => {
			expect( getMetaKey( null ) ).toBe( '' );
		} );

		it( 'should return empty string for undefined', () => {
			expect( getMetaKey( undefined ) ).toBe( '' );
		} );
	} );
} );
