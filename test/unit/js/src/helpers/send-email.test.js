/**
 * External dependencies
 */
import {
	afterEach,
	beforeEach,
	describe,
	expect,
	it,
	jest,
} from '@jest/globals';

/**
 * WordPress dependencies
 */
import apiFetch from '@wordpress/api-fetch';

/**
 * Internal dependencies
 */
import { initSendEmail, sendMessage } from '@src/helpers/send-email';

jest.mock( '@wordpress/api-fetch' );

/**
 * Render the send form markup the Email page template outputs.
 *
 * @param {string} message The message field value.
 *
 * @return {Object} The form elements, as sendMessage() receives them.
 */
function renderForm( message = 'Hello members' ) {
	document.body.innerHTML = `
		<div data-gatherpress_component_name="send-email">
			<input id="gatherpress-message-subject" value="Hi" />
			<textarea id="gatherpress-message-body"></textarea>
			<output class="gatherpress-send-email__status gatherpress--is-hidden">
				<span class="spinner"></span>
				<span class="gatherpress-send-email__status-text"></span>
			</output>
			<button type="button" class="gatherpress-send-email__send"></button>
		</div>
	`;

	document.getElementById( 'gatherpress-message-body' ).value = message;

	return {
		button: document.querySelector( '.gatherpress-send-email__send' ),
		subject: document.getElementById( 'gatherpress-message-subject' ),
		message: document.getElementById( 'gatherpress-message-body' ),
		status: document.querySelector( '.gatherpress-send-email__status' ),
	};
}

/**
 * Read the status line text.
 *
 * @return {string} The status text.
 */
function statusText() {
	return document.querySelector( '.gatherpress-send-email__status-text' )
		.textContent;
}

describe( 'send-email', () => {
	beforeEach( () => {
		window.confirm = jest.fn( () => true );
	} );

	afterEach( () => {
		jest.resetAllMocks();
		document.body.innerHTML = '';
	} );

	describe( 'initSendEmail', () => {
		it( 'does nothing without the Send Email form', () => {
			document.body.innerHTML = '<button></button>';

			expect( () => initSendEmail() ).not.toThrow();
		} );

		it( 'sends the message when the button is clicked', async () => {
			const { button } = renderForm();

			apiFetch.mockResolvedValue( { message: 'Queued for 2 members.' } );
			initSendEmail();
			button.click();

			await new Promise( process.nextTick );

			expect( apiFetch ).toHaveBeenCalledWith( {
				path: '/gatherpress/v1/site-message',
				method: 'POST',
				data: { subject: 'Hi', message: 'Hello members' },
			} );
		} );
	} );

	describe( 'sendMessage', () => {
		it( 'queues the message and clears the field', async () => {
			const elements = renderForm();

			apiFetch.mockResolvedValue( { message: 'Queued for 2 members.' } );
			await sendMessage( elements );

			expect( statusText() ).toBe( 'Queued for 2 members.' );
			expect( elements.status.classList ).not.toContain(
				'gatherpress--is-hidden',
			);
			expect(
				elements.status.querySelector( '.spinner' ).classList,
			).not.toContain( 'is-active' );
			expect( elements.message.value ).toBe( '' );
			expect( elements.button.getAttribute( 'aria-disabled' ) ).toBe(
				'false',
			);
		} );

		it( 'marks the button busy while the request is in flight', async () => {
			const elements = renderForm();
			let busy = null;

			apiFetch.mockImplementation( () => {
				busy = elements.button.getAttribute( 'aria-disabled' );

				return Promise.resolve( { message: 'Queued.' } );
			} );
			await sendMessage( elements );

			expect( busy ).toBe( 'true' );
		} );

		it( 'ignores a click while a send is in flight', async () => {
			const elements = renderForm();

			elements.button.setAttribute( 'aria-disabled', 'true' );
			await sendMessage( elements );

			expect( window.confirm ).not.toHaveBeenCalled();
			expect( apiFetch ).not.toHaveBeenCalled();
		} );

		it( 'asks for a message before sending a blank one', async () => {
			const elements = renderForm( '   ' );

			await sendMessage( elements );

			expect( statusText() ).toBe(
				'Please write a message before sending.',
			);
			expect( apiFetch ).not.toHaveBeenCalled();
		} );

		it( 'does not send when the confirmation is cancelled', async () => {
			const elements = renderForm();

			window.confirm = jest.fn( () => false );
			await sendMessage( elements );

			expect( apiFetch ).not.toHaveBeenCalled();
			expect( elements.status.classList ).toContain(
				'gatherpress--is-hidden',
			);
		} );

		it( 'shows the error the server returns', async () => {
			const elements = renderForm();

			apiFetch.mockRejectedValue( {
				message: 'There are no members to send this message to.',
			} );
			await sendMessage( elements );

			expect( statusText() ).toBe(
				'There are no members to send this message to.',
			);
			expect( elements.message.value ).toBe( 'Hello members' );
			expect( elements.button.getAttribute( 'aria-disabled' ) ).toBe(
				'false',
			);
		} );

		it( 'falls back to a generic error without a message', async () => {
			const elements = renderForm();

			apiFetch.mockRejectedValue( undefined );
			await sendMessage( elements );

			expect( statusText() ).toBe( 'The message could not be queued.' );
		} );
	} );
} );
