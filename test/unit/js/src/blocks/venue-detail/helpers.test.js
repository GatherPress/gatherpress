/**
 * External dependencies.
 */
import { describe, expect, it } from '@jest/globals';

/**
 * Internal dependencies.
 */
import {
	VENUE_FIELD_MAPPING,
	cleanUrlForDisplay,
	getJsonFieldName,
} from '../../../../../../src/blocks/venue-detail/helpers';

describe( 'Venue Detail helpers', () => {
	describe( 'VENUE_FIELD_MAPPING', () => {
		it( 'should have address mapped to fullAddress', () => {
			expect( VENUE_FIELD_MAPPING.address ).toBe( 'fullAddress' );
		} );

		it( 'should have phone mapped to phoneNumber', () => {
			expect( VENUE_FIELD_MAPPING.phone ).toBe( 'phoneNumber' );
		} );

		it( 'should have url mapped to website', () => {
			expect( VENUE_FIELD_MAPPING.url ).toBe( 'website' );
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

	describe( 'getJsonFieldName', () => {
		it( 'should return fullAddress for address field type', () => {
			expect( getJsonFieldName( 'address' ) ).toBe( 'fullAddress' );
		} );

		it( 'should return phoneNumber for phone field type', () => {
			expect( getJsonFieldName( 'phone' ) ).toBe( 'phoneNumber' );
		} );

		it( 'should return website for url field type', () => {
			expect( getJsonFieldName( 'url' ) ).toBe( 'website' );
		} );

		it( 'should return empty string for unknown field type', () => {
			expect( getJsonFieldName( 'unknown' ) ).toBe( '' );
		} );

		it( 'should return empty string for text field type', () => {
			expect( getJsonFieldName( 'text' ) ).toBe( '' );
		} );

		it( 'should return empty string for empty string', () => {
			expect( getJsonFieldName( '' ) ).toBe( '' );
		} );

		it( 'should return empty string for null', () => {
			expect( getJsonFieldName( null ) ).toBe( '' );
		} );

		it( 'should return empty string for undefined', () => {
			expect( getJsonFieldName( undefined ) ).toBe( '' );
		} );
	} );
} );
