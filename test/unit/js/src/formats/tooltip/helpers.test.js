/**
 * External dependencies.
 */
import { describe, expect, it } from '@jest/globals';

/**
 * Internal dependencies.
 */
import { getTooltipAttributes } from '../../../../../../src/formats/tooltip/helpers';
import { DEFAULT_COLORS } from '../../../../../../src/formats/tooltip/constants';

describe( 'Tooltip helpers', () => {
	describe( 'getTooltipAttributes', () => {
		it( 'returns default values when activeFormat is null', () => {
			const result = getTooltipAttributes( null );

			expect( result ).toEqual( {
				tooltip: '',
				textColor: DEFAULT_COLORS.textColor,
				bgColor: DEFAULT_COLORS.bgColor,
			} );
		} );

		it( 'returns default values when activeFormat is undefined', () => {
			const result = getTooltipAttributes( undefined );

			expect( result ).toEqual( {
				tooltip: '',
				textColor: DEFAULT_COLORS.textColor,
				bgColor: DEFAULT_COLORS.bgColor,
			} );
		} );

		it( 'returns default values when activeFormat has no attributes', () => {
			const result = getTooltipAttributes( {} );

			expect( result ).toEqual( {
				tooltip: '',
				textColor: DEFAULT_COLORS.textColor,
				bgColor: DEFAULT_COLORS.bgColor,
			} );
		} );

		it( 'returns default values when attributes is null', () => {
			const result = getTooltipAttributes( { attributes: null } );

			expect( result ).toEqual( {
				tooltip: '',
				textColor: DEFAULT_COLORS.textColor,
				bgColor: DEFAULT_COLORS.bgColor,
			} );
		} );

		it( 'extracts tooltip text from attributes', () => {
			const activeFormat = {
				attributes: {
					'data-gatherpress-tooltip': 'Test tooltip',
				},
			};

			const result = getTooltipAttributes( activeFormat );

			expect( result.tooltip ).toBe( 'Test tooltip' );
		} );

		it( 'extracts text color from attributes', () => {
			const activeFormat = {
				attributes: {
					'data-gatherpress-tooltip': 'Test',
					'data-gatherpress-tooltip-text-color': '#ff0000',
				},
			};

			const result = getTooltipAttributes( activeFormat );

			expect( result.textColor ).toBe( '#ff0000' );
		} );

		it( 'extracts background color from attributes', () => {
			const activeFormat = {
				attributes: {
					'data-gatherpress-tooltip': 'Test',
					'data-gatherpress-tooltip-bg-color': '#00ff00',
				},
			};

			const result = getTooltipAttributes( activeFormat );

			expect( result.bgColor ).toBe( '#00ff00' );
		} );

		it( 'extracts all attributes together', () => {
			const activeFormat = {
				attributes: {
					'data-gatherpress-tooltip': 'Full test',
					'data-gatherpress-tooltip-text-color': '#123456',
					'data-gatherpress-tooltip-bg-color': '#654321',
				},
			};

			const result = getTooltipAttributes( activeFormat );

			expect( result ).toEqual( {
				tooltip: 'Full test',
				textColor: '#123456',
				bgColor: '#654321',
			} );
		} );

		it( 'returns empty tooltip when attribute is missing', () => {
			const activeFormat = {
				attributes: {
					'data-gatherpress-tooltip-text-color': '#ffffff',
				},
			};

			const result = getTooltipAttributes( activeFormat );

			expect( result.tooltip ).toBe( '' );
		} );

		it( 'returns default text color when attribute is missing', () => {
			const activeFormat = {
				attributes: {
					'data-gatherpress-tooltip': 'Test',
				},
			};

			const result = getTooltipAttributes( activeFormat );

			expect( result.textColor ).toBe( DEFAULT_COLORS.textColor );
		} );

		it( 'returns default background color when attribute is missing', () => {
			const activeFormat = {
				attributes: {
					'data-gatherpress-tooltip': 'Test',
				},
			};

			const result = getTooltipAttributes( activeFormat );

			expect( result.bgColor ).toBe( DEFAULT_COLORS.bgColor );
		} );

		it( 'returns default values for empty string attributes', () => {
			const activeFormat = {
				attributes: {
					'data-gatherpress-tooltip': '',
					'data-gatherpress-tooltip-text-color': '',
					'data-gatherpress-tooltip-bg-color': '',
				},
			};

			const result = getTooltipAttributes( activeFormat );

			expect( result.tooltip ).toBe( '' );
			expect( result.textColor ).toBe( DEFAULT_COLORS.textColor );
			expect( result.bgColor ).toBe( DEFAULT_COLORS.bgColor );
		} );
	} );
} );
