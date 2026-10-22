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

	it( 'sets the rows to initial for 0, because repeat() with 0 is not valid CSS', () => {
		// `initial` also stops an outer week's rows from passing to a nested calendar.
		const none = {
			'--gatherpress-num-ribbon-slots': 0,
			'--gatherpress-ribbon-rows': 'initial',
		};

		expect( getRibbonRowsStyle( 0 ) ).toEqual( none );
		expect( getRibbonRowsStyle( undefined ) ).toEqual( none );
		expect( getRibbonRowsStyle( -3 ) ).toEqual( none );
	} );

	it( 'counts whole rows only', () => {
		expect( getRibbonRowsStyle( '2.7' ) ).toEqual( {
			'--gatherpress-num-ribbon-slots': 2,
			'--gatherpress-ribbon-rows': 'repeat(2, 24px)',
		} );
	} );
} );
