/**
 * External dependencies
 */
import { describe, expect, it, jest } from '@jest/globals';
import { render, screen } from '@testing-library/react';
import '@testing-library/jest-dom';

/**
 * Internal dependencies
 */
import { useIsStartedSeries } from '@src/helpers/repeat';
import DateTimeRangePanel from '@src/panels/event-settings/datetime-range';

jest.mock( '@src/components/DateTimeRange', () => () => <p>Dates</p> );
jest.mock( '@src/components/ShowTimezone', () => () => <p>Timezone</p> );
jest.mock( '@src/components/EventStatus', () => () => <p>Status</p> );
jest.mock( '@src/helpers/repeat', () => ( {
	FROZEN_NOTE_ID: 'frozen-note',
	useIsStartedSeries: jest.fn(),
} ) );
jest.mock( '@wordpress/components', () => ( {
	__experimentalText: ( { id, children } ) => <p id={ id }>{ children }</p>,
} ) );

describe( 'DateTimeRangePanel', () => {
	it( 'explains a started series\' frozen first date under the date fields', () => {
		useIsStartedSeries.mockReturnValue( true );
		render( <DateTimeRangePanel /> );

		expect( screen.getByText( /The first date has passed/ ) ).toHaveAttribute( 'id', 'frozen-note' );
	} );

	it( 'shows only the date fields otherwise', () => {
		useIsStartedSeries.mockReturnValue( false );
		render( <DateTimeRangePanel /> );

		expect( screen.getByText( 'Dates' ) ).toBeInTheDocument();
		expect( screen.queryByText( /The first date has passed/ ) ).not.toBeInTheDocument();
	} );
} );
