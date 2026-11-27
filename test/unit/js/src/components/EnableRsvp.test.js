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
import EnableRsvp from '@src/components/EnableRsvp';
import { getFromSettings } from '@src/helpers/editor-settings';

jest.mock( '@wordpress/data', () => ( {
	useDispatch: jest.fn(),
	useSelect: jest.fn(),
} ) );

jest.mock( '@wordpress/components', () => ( {
	CheckboxControl: ( { label, checked, onChange, help } ) => (
		<div>
			<input
				id="mock-enable-rsvp"
				type="checkbox"
				checked={ checked }
				onChange={ ( e ) => onChange( e.target.checked ) }
			/>
			<label htmlFor="mock-enable-rsvp">{ label }</label>
			<p>{ help }</p>
		</div>
	),
} ) );

jest.mock( '@src/helpers/editor-settings', () => ( {
	getFromSettings: jest.fn(),
} ) );

/**
 * Points the mocked `core/editor` selectors at the given editor state.
 *
 * @since TBD
 *
 * @param {boolean} isNewEvent Whether the editor holds a clean new post.
 * @param {Object}  meta       The post meta the editor returns.
 *
 * @return {void}
 */
const mockEditor = ( isNewEvent, meta ) => {
	useSelect.mockImplementation( ( callback ) =>
		callback( () => ( {
			isCleanNewPost: () => isNewEvent,
			getEditedPostAttribute: () => meta,
		} ) ),
	);
};

describe( 'EnableRsvp component', () => {
	const mockEditPost = jest.fn();
	const mockUnlockPostSaving = jest.fn();

	beforeEach( () => {
		jest.clearAllMocks();
		useDispatch.mockReturnValue( {
			editPost: mockEditPost,
			unlockPostSaving: mockUnlockPostSaving,
		} );
	} );

	describe( 'new events', () => {
		it( 'starts disabled and saves 0 when the RSVP mode is per_event_disabled', () => {
			mockEditor( true, {} );
			getFromSettings.mockReturnValue( 'per_event_disabled' );

			render( <EnableRsvp /> );

			expect( screen.getByLabelText( 'Enable RSVP' ) ).not.toBeChecked();
			expect( getFromSettings ).toHaveBeenCalledWith( 'rsvpMode' );
			expect( mockEditPost ).toHaveBeenCalledWith( {
				meta: { gatherpress_enable_rsvp: 0 },
			} );
			expect( mockUnlockPostSaving ).toHaveBeenCalledTimes( 1 );
		} );

		it( 'starts enabled and saves 1 for any other RSVP mode', () => {
			mockEditor( true, {} );
			getFromSettings.mockReturnValue( 'per_event_enabled' );

			render( <EnableRsvp /> );

			expect( screen.getByLabelText( 'Enable RSVP' ) ).toBeChecked();
			expect( mockEditPost ).toHaveBeenCalledWith( {
				meta: { gatherpress_enable_rsvp: 1 },
			} );
		} );

		it( 'starts enabled when no RSVP mode is set', () => {
			mockEditor( true, {} );
			getFromSettings.mockReturnValue( undefined );

			render( <EnableRsvp /> );

			expect( screen.getByLabelText( 'Enable RSVP' ) ).toBeChecked();
			expect( mockEditPost ).toHaveBeenCalledWith( {
				meta: { gatherpress_enable_rsvp: 1 },
			} );
		} );

		it( 'saves the default only once across re-renders', () => {
			mockEditor( true, {} );
			getFromSettings.mockReturnValue( 'enabled' );

			const { rerender } = render( <EnableRsvp /> );
			rerender( <EnableRsvp /> );

			expect( mockEditPost ).toHaveBeenCalledTimes( 1 );
		} );
	} );

	describe( 'existing events', () => {
		it( 'is enabled when the editor has no meta', () => {
			mockEditor( false, undefined );

			render( <EnableRsvp /> );

			expect( screen.getByLabelText( 'Enable RSVP' ) ).toBeChecked();
			expect( getFromSettings ).not.toHaveBeenCalled();
			expect( mockEditPost ).not.toHaveBeenCalled();
		} );

		it( 'is enabled when the meta value is null', () => {
			mockEditor( false, { gatherpress_enable_rsvp: null } );

			render( <EnableRsvp /> );

			expect( screen.getByLabelText( 'Enable RSVP' ) ).toBeChecked();
		} );

		it( 'is disabled when the meta value is 0', () => {
			mockEditor( false, { gatherpress_enable_rsvp: 0 } );

			render( <EnableRsvp /> );

			expect( screen.getByLabelText( 'Enable RSVP' ) ).not.toBeChecked();
		} );

		it( 'is enabled when the meta value is 1', () => {
			mockEditor( false, { gatherpress_enable_rsvp: 1 } );

			render( <EnableRsvp /> );

			expect( screen.getByLabelText( 'Enable RSVP' ) ).toBeChecked();
		} );
	} );

	it( 'saves 0 and unlocks saving when unchecked', () => {
		mockEditor( false, { gatherpress_enable_rsvp: 1 } );

		render( <EnableRsvp /> );
		fireEvent.click( screen.getByLabelText( 'Enable RSVP' ) );

		expect( screen.getByLabelText( 'Enable RSVP' ) ).not.toBeChecked();
		expect( mockEditPost ).toHaveBeenCalledWith( {
			meta: { gatherpress_enable_rsvp: 0 },
		} );
		expect( mockUnlockPostSaving ).toHaveBeenCalledTimes( 1 );
	} );

	it( 'saves 1 when checked', () => {
		mockEditor( false, { gatherpress_enable_rsvp: 0 } );

		render( <EnableRsvp /> );
		fireEvent.click( screen.getByLabelText( 'Enable RSVP' ) );

		expect( mockEditPost ).toHaveBeenCalledWith( {
			meta: { gatherpress_enable_rsvp: 1 },
		} );
	} );
} );
