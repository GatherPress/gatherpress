/**
 * External dependencies
 */
import { describe, expect, it, jest, beforeEach } from '@jest/globals';
import { fireEvent, render, screen } from '@testing-library/react';
import '@testing-library/jest-dom';

/**
 * WordPress dependencies
 */
import { useDispatch, useSelect } from '@wordpress/data';

/**
 * Internal dependencies
 */
import AllDay from '@src/components/AllDay';

// The datetime helpers import @wordpress/core-data, which needs the real
// @wordpress/data, and that imports itself while it loads. A Proxy reads it
// lazily, so only the two hooks are replaced.
jest.mock( '@wordpress/data', () => {
	const mockActual = jest.requireActual( '@wordpress/data' );
	const mockHooks = {
		useDispatch: jest.fn(),
		useSelect: jest.fn(),
	};

	return new Proxy( mockActual, {
		get: ( target, prop ) =>
			prop in mockHooks ? mockHooks[ prop ] : target[ prop ],
	} );
} );

jest.mock( '@wordpress/components', () => ( {
	ToggleControl: ( { label, checked, onChange } ) => (
		<div>
			<input
				id="mock-all-day"
				type="checkbox"
				checked={ checked }
				onChange={ ( e ) => onChange( e.target.checked ) }
			/>
			<label htmlFor="mock-all-day">{ label }</label>
		</div>
	),
} ) );

/**
 * Points the mocked `core/editor` and `gatherpress/datetime` selectors at
 * the given editor state.
 *
 * @since TBD
 *
 * @param {Object|undefined} meta  The post meta the editor returns.
 * @param {string}           start The stored start datetime.
 * @param {string}           end   The stored end datetime.
 *
 * @return {void}
 */
const mockEditor = ( meta, start, end ) => {
	useSelect.mockImplementation( ( callback ) =>
		callback( ( store ) =>
			'core/editor' === store
				? { getEditedPostAttribute: () => meta }
				: {
					getDateTimeStart: () => start,
					getDateTimeEnd: () => end,
				},
		),
	);
};

describe( 'AllDay component', () => {
	const mockEditPost = jest.fn();
	const mockUnlockPostSaving = jest.fn();
	const mockSetDateTimeStart = jest.fn();
	const mockSetDateTimeEnd = jest.fn();

	beforeEach( () => {
		jest.clearAllMocks();
		useDispatch.mockImplementation( ( store ) =>
			'core/editor' === store
				? {
					editPost: mockEditPost,
					unlockPostSaving: mockUnlockPostSaving,
				}
				: {
					setDateTimeStart: mockSetDateTimeStart,
					setDateTimeEnd: mockSetDateTimeEnd,
				},
		);
	} );

	it( 'is off when the editor has no meta', () => {
		mockEditor( undefined, '2026-12-01 18:00:00', '2026-12-01 20:00:00' );

		render( <AllDay /> );

		expect( screen.getByLabelText( 'All day' ) ).not.toBeChecked();
		expect( mockEditPost ).not.toHaveBeenCalled();
	} );

	it( 'is on when the meta marks the event all-day', () => {
		mockEditor(
			{ gatherpress_is_all_day: true },
			'2026-12-01 00:00:00',
			'2026-12-01 23:59:59',
		);

		render( <AllDay /> );

		expect( screen.getByLabelText( 'All day' ) ).toBeChecked();
	} );

	describe( 'turning it on', () => {
		it( 'snaps the dates to the day boundaries and hides an unset timezone', () => {
			mockEditor(
				{ gatherpress_is_all_day: false },
				'2026-12-01 18:00:00',
				'2026-12-02 20:30:00',
			);

			render( <AllDay /> );
			fireEvent.click( screen.getByLabelText( 'All day' ) );

			expect( mockEditPost ).toHaveBeenCalledWith( {
				meta: {
					gatherpress_is_all_day: true,
					gatherpress_show_timezone: 'never',
				},
			} );
			expect( mockSetDateTimeStart ).toHaveBeenCalledWith(
				'2026-12-01 00:00:00',
			);
			expect( mockSetDateTimeEnd ).toHaveBeenCalledWith(
				'2026-12-02 23:59:59',
			);
			expect( mockUnlockPostSaving ).toHaveBeenCalledTimes( 1 );
		} );

		it( 'leaves a timezone preference that was set deliberately', () => {
			mockEditor(
				{ gatherpress_show_timezone: 'always' },
				'2026-12-01 18:00:00',
				'2026-12-01 20:00:00',
			);

			render( <AllDay /> );
			fireEvent.click( screen.getByLabelText( 'All day' ) );

			expect( mockEditPost ).toHaveBeenCalledWith( {
				meta: { gatherpress_is_all_day: true },
			} );
		} );
	} );

	describe( 'turning it off', () => {
		it( 'restores the times it replaced onto the dates selected since', () => {
			const meta = { gatherpress_is_all_day: false };
			mockEditor( meta, '2026-12-01 18:00:00', '2026-12-01 20:30:00' );

			const { rerender } = render( <AllDay /> );
			fireEvent.click( screen.getByLabelText( 'All day' ) );

			// The store now holds the all-day span, moved to later dates.
			mockEditor(
				{ gatherpress_is_all_day: true, gatherpress_show_timezone: 'never' },
				'2026-12-05 00:00:00',
				'2026-12-06 23:59:59',
			);
			rerender( <AllDay /> );
			jest.clearAllMocks();

			fireEvent.click( screen.getByLabelText( 'All day' ) );

			expect( mockEditPost ).toHaveBeenCalledWith( {
				meta: {
					gatherpress_is_all_day: false,
					gatherpress_show_timezone: '',
				},
			} );
			expect( mockSetDateTimeStart ).toHaveBeenCalledWith(
				'2026-12-05 18:00:00',
			);
			expect( mockSetDateTimeEnd ).toHaveBeenCalledWith(
				'2026-12-06 20:30:00',
			);
			expect( mockUnlockPostSaving ).toHaveBeenCalledTimes( 1 );
		} );

		it( 'restores the times the post loaded with when it was never turned on', () => {
			mockEditor(
				{ gatherpress_is_all_day: true, gatherpress_show_timezone: 'always' },
				'2026-12-01 09:15:00',
				'2026-12-01 17:45:00',
			);

			render( <AllDay /> );
			fireEvent.click( screen.getByLabelText( 'All day' ) );

			expect( mockEditPost ).toHaveBeenCalledWith( {
				meta: { gatherpress_is_all_day: false },
			} );
			expect( mockSetDateTimeStart ).toHaveBeenCalledWith(
				'2026-12-01 09:15:00',
			);
			expect( mockSetDateTimeEnd ).toHaveBeenCalledWith(
				'2026-12-01 17:45:00',
			);
		} );
	} );
} );
