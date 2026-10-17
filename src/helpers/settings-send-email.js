/**
 * WordPress dependencies
 */
import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { REST_NAMESPACE } from './namespace';

const HIDDEN_CLASS = 'gatherpress--is-hidden';

/**
 * Show a status line under the Send Email form.
 *
 * @since TBD
 *
 * @param {HTMLElement} status The status output element.
 * @param {string}      text   The text to announce.
 * @param {boolean}     busy   Whether to show the spinner.
 *
 * @return {void}
 */
function showStatus( status, text, busy ) {
	status.classList.remove( HIDDEN_CLASS );
	status.querySelector( '.spinner' ).classList.toggle( 'is-active', busy );
	status.querySelector( '.gatherpress-send-email__status-text' ).textContent =
		text;
}

/**
 * Queue the message written in the Send Email form.
 *
 * @since TBD
 *
 * @param {Object}           elements         The Send Email form elements.
 * @param {HTMLElement}      elements.button  The Send button.
 * @param {HTMLInputElement} elements.subject The subject field.
 * @param {HTMLElement}      elements.message The message textarea.
 * @param {HTMLElement}      elements.status  The status output.
 *
 * @return {Promise<void>} Resolves once the request has settled.
 */
export async function sendMessage( { button, subject, message, status } ) {
	// aria-disabled keeps the button focusable for screen readers, so this
	// guard is what stops a second send.
	if ( 'true' === button.getAttribute( 'aria-disabled' ) ) {
		return;
	}

	if ( ! message.value.trim() ) {
		showStatus(
			status,
			__( 'Please write a message before sending.', 'gatherpress' ),
			false,
		);
		return;
	}

	if (
		// eslint-disable-next-line no-alert -- Confirmation required before sending mass emails.
		! window.confirm(
			__(
				'Send this message to every opted-in member? This cannot be undone.',
				'gatherpress',
			),
		)
	) {
		return;
	}

	button.setAttribute( 'aria-disabled', 'true' );
	showStatus( status, __( 'Queuing message…', 'gatherpress' ), true );

	try {
		const response = await apiFetch( {
			path: `/${ REST_NAMESPACE }/site-message`,
			method: 'POST',
			data: {
				subject: subject.value,
				message: message.value,
			},
		} );

		showStatus( status, response.message, false );
		message.value = '';
	} catch ( error ) {
		showStatus(
			status,
			error?.message ||
				__( 'The message could not be queued.', 'gatherpress' ),
			false,
		);
	} finally {
		button.setAttribute( 'aria-disabled', 'false' );
	}
}

/**
 * Wire up the send form on the Email settings page.
 *
 * Does nothing on settings pages without the Send Email form.
 *
 * @since TBD
 *
 * @return {void}
 */
export function initSendEmail() {
	const container = document.querySelector(
		'[data-gatherpress_component_name="send-email"]',
	);

	if ( ! container ) {
		return;
	}

	const elements = {
		button: container.querySelector( '.gatherpress-send-email__send' ),
		subject: container.querySelector( '#gatherpress-message-subject' ),
		message: container.querySelector( '#gatherpress-message-body' ),
		status: container.querySelector( '.gatherpress-send-email__status' ),
	};

	elements.button.addEventListener( 'click', () => sendMessage( elements ) );
}
