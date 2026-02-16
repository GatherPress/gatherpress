/**
 * External dependencies.
 */
import { describe, expect, it } from '@jest/globals';

/**
 * Internal dependencies.
 */
import { FORMAT_NAME, DEFAULT_COLORS } from '../../../../../../src/formats/tooltip/constants';

describe( 'Tooltip constants', () => {
	describe( 'FORMAT_NAME', () => {
		it( 'has the correct format name', () => {
			expect( FORMAT_NAME ).toBe( 'gatherpress/tooltip' );
		} );

		it( 'is a string', () => {
			expect( typeof FORMAT_NAME ).toBe( 'string' );
		} );
	} );

	describe( 'DEFAULT_COLORS', () => {
		it( 'has textColor property', () => {
			expect( DEFAULT_COLORS ).toHaveProperty( 'textColor' );
		} );

		it( 'has bgColor property', () => {
			expect( DEFAULT_COLORS ).toHaveProperty( 'bgColor' );
		} );

		it( 'has white as default text color', () => {
			expect( DEFAULT_COLORS.textColor ).toBe( '#ffffff' );
		} );

		it( 'has dark gray as default background color', () => {
			expect( DEFAULT_COLORS.bgColor ).toBe( '#333333' );
		} );

		it( 'only has two properties', () => {
			expect( Object.keys( DEFAULT_COLORS ).length ).toBe( 2 );
		} );
	} );
} );
