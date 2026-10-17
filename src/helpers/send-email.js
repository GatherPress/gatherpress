/**
 * WordPress dependencies
 */
import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';
import { addQueryArgs } from '@wordpress/url';

/**
 * Internal dependencies
 */
import { REST_NAMESPACE } from './namespace';

const HIDDEN_CLASS = 'gatherpress--is-hidden';
const SITE_MESSAGE_PATH = `/${ REST_NAMESPACE }/site-message`;

// Each count request takes a number, so only the latest one is shown.
let latestCount = 0;

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
 * Read the audience picked in the Send Email form.
 *
 * @since TBD
 *
 * @param {Object}             elements          The Send Email form elements.
 * @param {HTMLSelectElement}  elements.audience The audience select.
 * @param {HTMLInputElement[]} elements.roles    The role checkboxes.
 * @param {HTMLSelectElement}  elements.months   The active member window select.
 * @param {HTMLSelectElement}  elements.event    The event select.
 *
 * @return {Object} The audience, as the site message REST routes take it.
 */
export function readAudience( { audience, roles, months, event } ) {
	return {
		audience: audience.value,
		roles: roles
			.filter( ( role ) => role.checked )
			.map( ( role ) => role.value ),
		months: Number( months.value ),
		event_id: Number( event.value ),
	};
}

/**
 * Explain what an audience is missing before it can be used.
 *
 * @since TBD
 *
 * @param {Object} params The audience, from readAudience().
 *
 * @return {string} What to fix, or an empty string when nothing is missing.
 */
export function getAudienceError( params ) {
	if ( 'role' === params.audience && ! params.roles.length ) {
		return __( 'Choose at least one role.', 'gatherpress' );
	}

	if ( 'no_rsvp' === params.audience && ! params.event_id ) {
		return __( 'Choose an event.', 'gatherpress' );
	}

	return '';
}

/**
 * Show only the fields of the picked audience.
 *
 * @since TBD
 *
 * @param {Object}            elements              The Send Email form elements.
 * @param {HTMLSelectElement} elements.audience     The audience select.
 * @param {HTMLElement[]}     elements.audienceRows The rows that belong to one audience.
 *
 * @return {void}
 */
export function showAudienceFields( { audience, audienceRows } ) {
	audienceRows.forEach( ( row ) =>
		row.classList.toggle(
			HIDDEN_CLASS,
			row.dataset.gatherpress_audience !== audience.value,
		),
	);
}

/**
 * Update how many members the picked audience would email.
 *
 * @since TBD
 *
 * @param {Object} elements The Send Email form elements.
 *
 * @return {Promise<void>} Resolves once the count is shown.
 */
export async function updateCount( elements ) {
	const params = readAudience( elements );
	const error = getAudienceError( params );
	const request = ++latestCount;

	if ( error ) {
		elements.count.textContent = error;
		return;
	}

	elements.count.textContent = __( 'Counting members…', 'gatherpress' );

	let text;

	try {
		const response = await apiFetch( {
			path: addQueryArgs( `${ SITE_MESSAGE_PATH }/count`, params ),
		} );

		text = response.message;
	} catch ( apiError ) {
		text =
			apiError?.message ||
			__( 'The members could not be counted.', 'gatherpress' );
	}

	if ( request === latestCount ) {
		elements.count.textContent = text;
	}
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
export async function sendMessage( elements ) {
	const { button, subject, message, status } = elements;

	// aria-disabled keeps the button focusable for screen readers, so this
	// guard is what stops a second send.
	if ( 'true' === button.getAttribute( 'aria-disabled' ) ) {
		return;
	}

	const audience = readAudience( elements );
	const error = message.value.trim()
		? getAudienceError( audience )
		: __( 'Please write a message before sending.', 'gatherpress' );

	if ( error ) {
		showStatus( status, error, false );
		return;
	}

	if (
		// eslint-disable-next-line no-alert -- Confirmation required before sending mass emails.
		! window.confirm(
			__(
				'Send this message to the selected members? This cannot be undone.',
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
			path: SITE_MESSAGE_PATH,
			method: 'POST',
			data: {
				subject: subject.value,
				message: message.value,
				...audience,
			},
		} );

		showStatus( status, response.message, false );
		message.value = '';
	} catch ( apiError ) {
		showStatus(
			status,
			apiError?.message ||
				__( 'The message could not be queued.', 'gatherpress' ),
			false,
		);
	} finally {
		button.setAttribute( 'aria-disabled', 'false' );
	}
}

/**
 * Wire up the send form on the Email page.
 *
 * Does nothing on pages without the send form.
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
		count: container.querySelector( '.gatherpress-send-email__count' ),
		audience: container.querySelector( '#gatherpress-message-audience' ),
		roles: Array.from(
			container.querySelectorAll( '[name="gatherpress-message-roles"]' ),
		),
		months: container.querySelector( '#gatherpress-message-months' ),
		event: container.querySelector( '#gatherpress-message-event' ),
		audienceRows: Array.from(
			container.querySelectorAll( '[data-gatherpress_audience]' ),
		),
	};

	[
		elements.audience,
		elements.months,
		elements.event,
		...elements.roles,
	].forEach( ( field ) =>
		field.addEventListener( 'change', () => {
			showAudienceFields( elements );
			updateCount( elements );
		} ),
	);

	elements.button.addEventListener( 'click', () => sendMessage( elements ) );
}
