/**
 * External dependencies.
 */
import { beforeEach, describe, expect, it, jest } from '@jest/globals';
import { renderHook } from '@testing-library/react';

jest.mock( '@wordpress/data', () => ( {
	useSelect: jest.fn(),
	useDispatch: jest.fn(),
} ) );

jest.mock( '@wordpress/block-editor', () => ( {
	store: 'core/block-editor',
} ) );

/**
 * WordPress dependencies.
 */
import { useDispatch, useSelect } from '@wordpress/data';

/**
 * Internal dependencies.
 */
import { useCalendarSync } from '@src/blocks/calendar/hooks/use-calendar-sync';
import { getCalendarBlockName } from '@src/blocks/calendar/utils/block-sync-utils';

const updateBlockAttributes = jest.fn();

/**
 * Give the calendar block its parent Query blocks, outermost first.
 *
 * @param {Array<string>} parents Client IDs of the parent Query blocks.
 *
 * @return {void}
 */
const giveParents = ( parents ) => {
	useSelect.mockImplementation( ( callback ) =>
		callback( () => ( {
			getBlockParentsByBlockName: () => parents,
			getBlock: ( clientId ) => ( {
				clientId,
				attributes: { metadata: {} },
				innerBlocks: [],
			} ),
		} ) ),
	);
};

/**
 * Render the hook, then switch the calendar to a week view.
 *
 * @return {void}
 */
const switchToWeekView = () => {
	const { rerender } = renderHook(
		( props ) => useCalendarSync( { clientId: 'calendar', ...props } ),
		{ initialProps: { viewType: 'month', unitCount: 1 } },
	);

	rerender( { viewType: 'week', unitCount: 1 } );
};

describe( 'useCalendarSync', () => {
	beforeEach( () => {
		jest.clearAllMocks();
		useDispatch.mockReturnValue( { updateBlockAttributes } );
	} );

	it( 'names the closest of nested Query blocks', () => {
		giveParents( [ 'outer-query', 'inner-query' ] );

		switchToWeekView();

		expect( updateBlockAttributes ).toHaveBeenCalledTimes( 1 );
		expect( updateBlockAttributes ).toHaveBeenCalledWith( 'inner-query', {
			metadata: { name: getCalendarBlockName( 'week', 1 ) },
		} );
	} );

	it( 'names nothing without a parent Query block', () => {
		giveParents( [] );

		switchToWeekView();

		expect( updateBlockAttributes ).not.toHaveBeenCalled();
	} );
} );
