/**
 * External dependencies
 */
import { describe, expect, it, jest, beforeEach } from '@jest/globals';
import { render } from '@testing-library/react';
import '@testing-library/jest-dom';

/**
 * WordPress dependencies
 */
import { useDispatch, useSelect } from '@wordpress/data';

/**
 * Internal dependencies
 */
import EmailNotificationManager from '@src/components/EmailNotificationManager';
import { hasEventPast, isRsvpPostType } from '@src/helpers/event';

jest.mock( '@wordpress/data', () => ( {
	useDispatch: jest.fn(),
	useSelect: jest.fn(),
} ) );

jest.mock( '@src/helpers/event', () => ( {
	hasEventPast: jest.fn(),
	isRsvpPostType: jest.fn(),
} ) );

describe( 'EmailNotificationManager', () => {
	const createNotice = jest.fn();
	const removeNotice = jest.fn();
	const openModal = jest.fn();
	const change = {
		keys: [ 'venue' ],
		headline: 'The venue changed.',
		attendees: 12,
		show_notice: true,
	};
	let editor;

	/**
	 * Point the mocked `core/editor` store at new state and render again.
	 *
	 * @param {Function} rerender The render result's rerender.
	 * @param {Object}   state    Editor state to merge in.
	 */
	const update = ( rerender, state ) => {
		editor = { ...editor, ...state };
		rerender( <EmailNotificationManager /> );
	};

	/**
	 * Render, then go through one save that ends with the given change.
	 *
	 * @param {Object|null} savedChange The `gatherpress_change` the save returns.
	 *
	 * @return {Function} The rerender function.
	 */
	const renderAndSave = ( savedChange ) => {
		const { rerender } = render( <EmailNotificationManager /> );

		update( rerender, { saving: true } );
		update( rerender, { saving: false, change: savedChange } );

		return rerender;
	};

	beforeEach( () => {
		jest.clearAllMocks();

		editor = {
			status: 'publish',
			saving: false,
			autosaving: false,
			dirty: false,
			change: null,
		};

		hasEventPast.mockReturnValue( false );
		isRsvpPostType.mockReturnValue( true );

		useDispatch.mockImplementation( ( store ) =>
			'core/notices' === store
				? { createNotice, removeNotice }
				: { openModal },
		);
		useSelect.mockImplementation( ( callback ) =>
			callback( () => ( {
				getEditedPostAttribute: () => editor.status,
				isSavingPost: () => editor.saving,
				isAutosavingPost: () => editor.autosaving,
				isEditedPostDirty: () => editor.dirty,
				getCurrentPostAttribute: () => editor.change,
			} ) ),
		);
	} );

	it( 'renders nothing', () => {
		const { container } = render( <EmailNotificationManager /> );

		expect( container ).toBeEmptyDOMElement();
	} );

	it( 'offers to tell attendees after a save that changed something', () => {
		renderAndSave( change );

		expect( createNotice ).toHaveBeenCalledTimes( 1 );
		expect( createNotice ).toHaveBeenCalledWith(
			'info',
			'The venue changed. Tell 12 attendees?',
			expect.objectContaining( {
				id: 'gatherpress_event_communication',
				isDismissible: true,
			} ),
		);

		// No `type: 'snackbar'`, so the notice stays until it is closed.
		expect( createNotice.mock.calls[ 0 ][ 2 ] ).not.toHaveProperty( 'type' );

		const [ action ] = createNotice.mock.calls[ 0 ][ 2 ].actions;

		expect( action.label ).toBe( 'Compose Message' );

		action.onClick();

		expect( openModal ).toHaveBeenCalledWith( change );
	} );

	it( 'shows nothing after a save that changed nothing', () => {
		renderAndSave( null );

		expect( createNotice ).not.toHaveBeenCalled();
	} );

	it( 'shows nothing when the server turned the notice off', () => {
		renderAndSave( { ...change, show_notice: false } );

		expect( createNotice ).not.toHaveBeenCalled();
	} );

	it( 'shows nothing for a draft, an event without RSVPs, or a past event', () => {
		editor.status = 'draft';
		renderAndSave( change );

		editor.status = 'publish';
		isRsvpPostType.mockReturnValue( false );
		renderAndSave( change );

		isRsvpPostType.mockReturnValue( true );
		hasEventPast.mockReturnValue( true );
		renderAndSave( change );

		expect( createNotice ).not.toHaveBeenCalled();
	} );

	it( 'ignores autosaves', () => {
		const { rerender } = render( <EmailNotificationManager /> );

		update( rerender, { saving: true, autosaving: true } );
		update( rerender, { saving: false, autosaving: false, change } );

		expect( createNotice ).not.toHaveBeenCalled();
	} );

	it( 'removes the notice on the next edit', () => {
		const rerender = renderAndSave( change );

		update( rerender, { dirty: true } );

		expect( removeNotice ).toHaveBeenCalledWith(
			'gatherpress_event_communication',
		);
	} );

	it( 'removes the notice after a later save that changed nothing', () => {
		const rerender = renderAndSave( change );

		update( rerender, { saving: true } );
		removeNotice.mockClear();
		update( rerender, { saving: false, change: null } );

		// Removed when the save started; nothing new is offered after it.
		expect( createNotice ).toHaveBeenCalledTimes( 1 );
	} );

	it( 'removes the notice when it no longer applies', () => {
		const rerender = renderAndSave( change );

		update( rerender, { status: 'draft' } );

		expect( removeNotice ).toHaveBeenCalledTimes( 1 );

		update( rerender, { status: 'publish' } );

		// Nothing left to remove.
		expect( removeNotice ).toHaveBeenCalledTimes( 1 );
	} );
} );
