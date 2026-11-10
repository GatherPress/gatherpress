/**
 * External dependencies
 */
import { act, fireEvent, screen } from '@testing-library/react';
import { describe, expect, it, jest, beforeEach } from '@jest/globals';
import '@testing-library/jest-dom';

/**
 * Internal dependencies
 */
jest.mock( '@wordpress/dom-ready', () => jest.fn() );
jest.mock( '@wordpress/api-fetch', () => jest.fn() );
let mockIsOpen = true;
jest.mock( '@wordpress/data', () => {
	const stores = {
		'gatherpress/email-modal': {
			isModalOpen: () => mockIsOpen,
			isSaving: () => false,
		},
		'core/editor': {
			getEditedPostAttribute: () => 'Event',
			getCurrentPostId: () => 1,
		},
	};

	return {
		select: ( store ) => stores[ store ],
		useSelect: ( callback ) => callback( ( store ) => stores[ store ] ),
		useDispatch: () => ( { closeModal: jest.fn() } ),
	};
} );
// The real components import rich-text, which registers a data store at load
// time. The modal does not use it, and the data mock above has no stores.
jest.mock( '@wordpress/rich-text', () => ( {} ) );
// The real controls, so focus, group names and the disabled Send button are
// what the browser gets. Only the Modal shell is replaced.
jest.mock( '@wordpress/components', () => ( {
	...jest.requireActual( '@wordpress/components' ),
	Modal: ( { children } ) => <div>{ children }</div>,
} ) );
jest.mock( '@src/helpers/editor-settings', () => ( {
	getFromConfig: jest.fn(),
} ) );

import apiFetch from '@wordpress/api-fetch';
import domReady from '@wordpress/dom-ready';
import { getFromConfig } from '@src/helpers/editor-settings';
import '@src/modals/email-communication';

// Captured once, when the module above registers its mount callback. It is
// the last module imported, so its call comes after those the real
// components make.
const mountModal = domReady.mock.calls.at( -1 )[ 0 ];

/**
 * Mount the modal into a fresh wrapper element.
 *
 * @since 0.36.1
 */
async function renderModal() {
	document.body.innerHTML =
		'<div id="gatherpress-event-communication-modal"></div>';

	await act( async () => {
		mountModal();
	} );
}

/**
 * Coverage for the event communication modal.
 */
describe( 'EventCommunicationModal', () => {
	beforeEach( () => {
		jest.clearAllMocks();
		mockIsOpen = true;
	} );

	it( 'renders nothing and moves no focus while closed', async () => {
		getFromConfig.mockReturnValue( true );
		mockIsOpen = false;

		await renderModal();

		expect( screen.queryByRole( 'textbox' ) ).not.toBeInTheDocument();
		expect( document.body ).toHaveFocus();
	} );

	it( 'offers All Members when the user can email every member', async () => {
		getFromConfig.mockReturnValue( true );

		await renderModal();

		expect( screen.getByLabelText( 'All Members' ) ).toBeInTheDocument();
		expect( getFromConfig ).toHaveBeenCalledWith( 'canEmailAllUsers' );
		expect(
			screen.getByText( /"All Members" includes site users only\./ ),
		).toBeInTheDocument();
	} );

	it( 'hides All Members when the user cannot email every member', async () => {
		getFromConfig.mockReturnValue( false );

		await renderModal();

		expect( screen.queryByLabelText( 'All Members' ) ).not.toBeInTheDocument();
		expect( screen.getByLabelText( 'Attending' ) ).toBeInTheDocument();
		expect( screen.queryByText( /All Members/ ) ).not.toBeInTheDocument();
		expect(
			screen.getByText( /RSVP status options include both site users/ ),
		).toBeInTheDocument();
	} );

	it( 'focuses Subject, the first field, when it opens', async () => {
		getFromConfig.mockReturnValue( true );

		await renderModal();

		expect( screen.getByRole( 'textbox', { name: 'Subject' } ) ).toHaveFocus();
	} );

	it( 'groups the recipient checkboxes under a Recipients legend', async () => {
		getFromConfig.mockReturnValue( true );

		await renderModal();

		const group = screen.getByRole( 'group', { name: 'Recipients' } );

		expect( group.tagName ).toBe( 'FIELDSET' );
		expect( group ).toHaveAccessibleDescription(
			/Select the recipients for your message/,
		);
		expect(
			[ ...group.querySelectorAll( 'input[type="checkbox"]' ) ].map(
				( input ) => input.labels[ 0 ].textContent,
			),
		).toEqual( [ 'All Members', 'Attending', 'Waiting List', 'Not Attending' ] );
	} );

	it( 'keeps Send focusable and says why when no group is chosen', async () => {
		getFromConfig.mockReturnValue( true );
		const confirm = jest.spyOn( window, 'confirm' ).mockReturnValue( true );

		await renderModal();

		const send = screen.getByRole( 'button', { name: 'Send Email' } );

		expect( send ).not.toHaveAttribute( 'disabled' );
		expect( send ).toHaveAttribute( 'aria-disabled', 'true' );
		expect( send ).toHaveAccessibleDescription(
			'Choose at least one group of recipients.',
		);
		expect(
			screen.getByText( 'Choose at least one group of recipients.' ),
		).toBeVisible();

		send.focus();
		expect( send ).toHaveFocus();

		fireEvent.click( send );
		expect( confirm ).not.toHaveBeenCalled();

		confirm.mockRestore();
	} );

	it( 'enables Send and drops the hint once a group is chosen', async () => {
		getFromConfig.mockReturnValue( true );
		// Dismiss the confirmation, so nothing is sent.
		const confirm = jest.spyOn( window, 'confirm' ).mockReturnValue( false );

		await renderModal();

		await act( async () => {
			fireEvent.click( screen.getByLabelText( 'Attending' ) );
		} );

		const send = screen.getByRole( 'button', { name: 'Send Email' } );

		expect( send ).not.toHaveAttribute( 'aria-disabled' );
		expect( send ).not.toHaveAttribute( 'aria-describedby' );
		expect(
			screen.queryByText( 'Choose at least one group of recipients.' ),
		).not.toBeInTheDocument();

		fireEvent.click( send );
		expect( confirm ).toHaveBeenCalledTimes( 1 );
		expect( apiFetch ).not.toHaveBeenCalled();

		confirm.mockRestore();
	} );

	it( 'posts the subject, message and chosen groups once confirmed', async () => {
		getFromConfig.mockReturnValue( true );
		const confirm = jest.spyOn( window, 'confirm' ).mockReturnValue( true );
		// The request is mocked, so nothing leaves the test.
		apiFetch.mockResolvedValue( { success: true } );

		await renderModal();

		fireEvent.change( screen.getByRole( 'textbox', { name: 'Subject' } ), {
			target: { value: 'Room change' },
		} );
		fireEvent.change(
			screen.getByRole( 'textbox', { name: 'Optional message' } ),
			{ target: { value: 'We moved to room 2.' } },
		);
		fireEvent.click( screen.getByLabelText( 'Attending' ) );

		await act( async () => {
			fireEvent.click( screen.getByRole( 'button', { name: 'Send Email' } ) );
		} );

		expect( apiFetch ).toHaveBeenCalledWith(
			expect.objectContaining( {
				method: 'POST',
				data: {
					post_id: 1,
					subject: 'Room change',
					message: 'We moved to room 2.',
					send: {
						all: false,
						attending: true,
						waiting_list: false,
						not_attending: false,
					},
				},
			} ),
		);

		confirm.mockRestore();
	} );
} );
