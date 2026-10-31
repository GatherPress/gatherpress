/**
 * External dependencies.
 */
import { describe, expect, it, jest } from '@jest/globals';
import { render, screen } from '@testing-library/react';

jest.mock( '@src/blocks/calendar/panels/source-post-controls', () => ( {
	SourcePostControls: () => null,
} ) );

jest.mock( '@src/blocks/calendar/panels/shadow-source-filter-controls', () => ( {
	ShadowSourceFilterControls: () => null,
} ) );

jest.mock( '@src/blocks/calendar/hooks/use-date-offset-help', () => ( {
	useDateOffsetHelp: () => '',
} ) );

/**
 * Internal dependencies.
 */
import { DateControls } from '@src/blocks/calendar/panels/date-controls';

describe( 'DateControls', () => {
	it( 'shows the offset of a saved date with an offset', () => {
		render(
			<DateControls
				clientId="calendar"
				viewType="week"
				unitCount={ 1 }
				selectedDate="2026-10-15"
				dateModifier={ 2 }
				onViewTypeChange={ jest.fn() }
				onUnitCountChange={ jest.fn() }
				onDateChange={ jest.fn() }
				onModifierChange={ jest.fn() }
			/>,
		);

		expect(
			screen.getByText( 'Current Selection' ).nextElementSibling.textContent,
		).toBe( '2026-10-15 +2 week(s)' );
	} );
} );
