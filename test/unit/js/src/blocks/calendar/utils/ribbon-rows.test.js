/**
 * External dependencies.
 */
import { describe, expect, it } from '@jest/globals';

/**
 * Internal dependencies.
 */
import { getRibbonRowsStyle } from '@src/blocks/calendar/utils/ribbon-rows';

describe( 'getRibbonRowsStyle', () => {
	it( 'gives the ribbon rows for the time grid template', () => {
		expect( getRibbonRowsStyle( 2 ) ).toEqual( {
			'--gatherpress-num-ribbon-slots': 2,
			'--gatherpress-ribbon-rows': 'repeat(2, 24px)',
		} );
	} );

	it( 'leaves the rows out for 0, because repeat() with 0 is not valid CSS', () => {
		expect( getRibbonRowsStyle( 0 ) ).toEqual( {
			'--gatherpress-num-ribbon-slots': 0,
		} );
		expect( getRibbonRowsStyle( undefined ) ).toEqual( {
			'--gatherpress-num-ribbon-slots': 0,
		} );
		expect( getRibbonRowsStyle( -3 ) ).toEqual( {
			'--gatherpress-num-ribbon-slots': 0,
		} );
	} );

	it( 'counts whole rows only', () => {
		expect( getRibbonRowsStyle( '2.7' ) ).toEqual( {
			'--gatherpress-num-ribbon-slots': 2,
			'--gatherpress-ribbon-rows': 'repeat(2, 24px)',
		} );
	} );
} );
