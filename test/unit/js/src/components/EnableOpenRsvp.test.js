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
import EnableOpenRsvp from '@src/components/EnableOpenRsvp';

jest.mock( '@wordpress/data', () => ( {
	useDispatch: jest.fn(),
	useSelect: jest.fn(),
} ) );

jest.mock( '@wordpress/components', () => ( {
	CheckboxControl: ( { label, checked, onChange, help } ) => (
		<div>
			<input
				id="mock-enable-open-rsvp"
				type="checkbox"
				checked={ checked }
				onChange={ ( e ) => onChange( e.target.checked ) }
			/>
			<label htmlFor="mock-enable-open-rsvp">{ label }</label>
			<p>{ help }</p>
		</div>
	),
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

describe( 'EnableOpenRsvp component', () => {
	const mockEditPost = jest.fn();
	const mockUnlockPostSaving = jest.fn();

	beforeEach( () => {
		jest.clearAllMocks();
		useDispatch.mockReturnValue( {
			editPost: mockEditPost,
			unlockPostSaving: mockUnlockPostSaving,
		} );
	} );

	it( 'starts enabled and saves 1 for a new event, whatever the meta says', () => {
		mockEditor( true, { gatherpress_enable_open_rsvp: 0 } );

		render( <EnableOpenRsvp /> );

		expect( screen.getByLabelText( 'Enable Open RSVP' ) ).toBeChecked();
		expect( mockEditPost ).toHaveBeenCalledWith( {
			meta: { gatherpress_enable_open_rsvp: 1 },
		} );
		expect( mockUnlockPostSaving ).toHaveBeenCalledTimes( 1 );
	} );

	describe( 'existing events', () => {
		it( 'is enabled when the editor has no meta', () => {
			mockEditor( false, undefined );

			render( <EnableOpenRsvp /> );

			expect( screen.getByLabelText( 'Enable Open RSVP' ) ).toBeChecked();
			expect( mockEditPost ).not.toHaveBeenCalled();
		} );

		it( 'is enabled when the meta value is null', () => {
			mockEditor( false, { gatherpress_enable_open_rsvp: null } );

			render( <EnableOpenRsvp /> );

			expect( screen.getByLabelText( 'Enable Open RSVP' ) ).toBeChecked();
		} );

		it( 'is disabled when the meta value is 0', () => {
			mockEditor( false, { gatherpress_enable_open_rsvp: 0 } );

			render( <EnableOpenRsvp /> );

			expect(
				screen.getByLabelText( 'Enable Open RSVP' ),
			).not.toBeChecked();
		} );

		it( 'is enabled when the meta value is 1', () => {
			mockEditor( false, { gatherpress_enable_open_rsvp: 1 } );

			render( <EnableOpenRsvp /> );

			expect( screen.getByLabelText( 'Enable Open RSVP' ) ).toBeChecked();
		} );
	} );

	it( 'saves 0 and unlocks saving when unchecked', () => {
		mockEditor( false, { gatherpress_enable_open_rsvp: 1 } );

		render( <EnableOpenRsvp /> );
		fireEvent.click( screen.getByLabelText( 'Enable Open RSVP' ) );

		expect( screen.getByLabelText( 'Enable Open RSVP' ) ).not.toBeChecked();
		expect( mockEditPost ).toHaveBeenCalledWith( {
			meta: { gatherpress_enable_open_rsvp: 0 },
		} );
		expect( mockUnlockPostSaving ).toHaveBeenCalledTimes( 1 );
	} );

	it( 'saves 1 when checked', () => {
		mockEditor( false, { gatherpress_enable_open_rsvp: 0 } );

		render( <EnableOpenRsvp /> );
		fireEvent.click( screen.getByLabelText( 'Enable Open RSVP' ) );

		expect( mockEditPost ).toHaveBeenCalledWith( {
			meta: { gatherpress_enable_open_rsvp: 1 },
		} );
	} );
} );
