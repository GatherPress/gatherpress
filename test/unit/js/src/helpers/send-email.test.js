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
import {
	getAudienceError,
	initSendEmail,
	readAudience,
	sendMessage,
	showAudienceFields,
	updateCount,
} from '@src/helpers/send-email';

jest.mock( '@wordpress/api-fetch' );

const HIDDEN_CLASS = 'gatherpress--is-hidden';

/**
 * Render the send form markup the Email page template outputs.
 *
 * @param {string} message The message field value.
 *
 * @return {Object} The form elements, as initSendEmail() collects them.
 */
function renderForm( message = 'Hello members' ) {
	document.body.innerHTML = `
		<div data-gatherpress_component_name="send-email">
			<select id="gatherpress-message-audience">
				<option value="all">All</option>
				<option value="role">Role</option>
				<option value="active">Active</option>
				<option value="no_rsvp">No RSVP</option>
			</select>
			<output class="gatherpress-send-email__count">This will email 2 members.</output>
			<div class="${ HIDDEN_CLASS }" data-gatherpress_audience="role">
				<input type="checkbox" name="gatherpress-message-roles" value="editor" />
				<input type="checkbox" name="gatherpress-message-roles" value="author" />
			</div>
			<div class="${ HIDDEN_CLASS }" data-gatherpress_audience="active">
				<select id="gatherpress-message-months">
					<option value="1">1</option>
					<option value="3" selected>3</option>
				</select>
			</div>
			<div class="${ HIDDEN_CLASS }" data-gatherpress_audience="no_rsvp">
				<select id="gatherpress-message-event">
					<option value="">Choose</option>
					<option value="42">Event</option>
				</select>
			</div>
			<input id="gatherpress-message-subject" value="Hi" />
			<textarea id="gatherpress-message-body"></textarea>
			<output class="gatherpress-send-email__status ${ HIDDEN_CLASS }">
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
		count: document.querySelector( '.gatherpress-send-email__count' ),
		audience: document.getElementById( 'gatherpress-message-audience' ),
		roles: Array.from(
			document.querySelectorAll( '[name="gatherpress-message-roles"]' ),
		),
		months: document.getElementById( 'gatherpress-message-months' ),
		event: document.getElementById( 'gatherpress-message-event' ),
		audienceRows: Array.from(
			document.querySelectorAll( '[data-gatherpress_audience]' ),
		),
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

/**
 * Pick an audience in the form.
 *
 * @param {Object} elements The form elements.
 * @param {string} audience The audience to pick.
 *
 * @return {void}
 */
function pick( elements, audience ) {
	elements.audience.value = audience;
}

describe( 'send-email', () => {
	beforeEach( () => {
		window.confirm = jest.fn( () => true );
	} );

	afterEach( () => {
		jest.resetAllMocks();
		document.body.innerHTML = '';
	} );

	describe( 'readAudience', () => {
		it( 'reads the audience, checked roles, months and event', () => {
			const elements = renderForm();

			pick( elements, 'role' );
			elements.roles[ 1 ].checked = true;
			elements.event.value = '42';

			expect( readAudience( elements ) ).toEqual( {
				audience: 'role',
				roles: [ 'author' ],
				months: 3,
				event_id: 42,
			} );
		} );
	} );

	describe( 'getAudienceError', () => {
		it( 'asks for a role when none is checked', () => {
			expect(
				getAudienceError( { audience: 'role', roles: [], event_id: 0 } ),
			).toBe( 'Choose at least one role.' );
		} );

		it( 'asks for an event when none is chosen', () => {
			expect(
				getAudienceError( { audience: 'no_rsvp', roles: [], event_id: 0 } ),
			).toBe( 'Choose an event.' );
		} );

		it( 'accepts complete audiences', () => {
			expect(
				getAudienceError( {
					audience: 'role',
					roles: [ 'editor' ],
					event_id: 0,
				} ),
			).toBe( '' );
			expect(
				getAudienceError( { audience: 'no_rsvp', roles: [], event_id: 42 } ),
			).toBe( '' );
			expect(
				getAudienceError( { audience: 'all', roles: [], event_id: 0 } ),
			).toBe( '' );
		} );
	} );

	describe( 'showAudienceFields', () => {
		it( 'shows only the picked audience fields', () => {
			const elements = renderForm();

			pick( elements, 'active' );
			showAudienceFields( elements );

			expect(
				elements.audienceRows.map( ( row ) =>
					row.classList.contains( HIDDEN_CLASS ),
				),
			).toEqual( [ true, false, true ] );
		} );
	} );

	describe( 'updateCount', () => {
		it( 'shows the count the server returns', async () => {
			const elements = renderForm();

			pick( elements, 'active' );
			apiFetch.mockResolvedValue( { message: 'This will email 5 members.' } );
			await updateCount( elements );

			expect( apiFetch ).toHaveBeenCalledWith( {
				path: '/gatherpress/v1/site-message/count?audience=active&months=3&event_id=0',
			} );
			expect( elements.count.textContent ).toBe(
				'This will email 5 members.',
			);
		} );

		it( 'shows what is missing without asking the server', async () => {
			const elements = renderForm();

			pick( elements, 'role' );
			await updateCount( elements );

			expect( apiFetch ).not.toHaveBeenCalled();
			expect( elements.count.textContent ).toBe(
				'Choose at least one role.',
			);
		} );

		it( 'shows the error the server returns', async () => {
			const elements = renderForm();

			apiFetch.mockRejectedValue( { message: 'Choose an event.' } );
			await updateCount( elements );

			expect( elements.count.textContent ).toBe( 'Choose an event.' );
		} );

		it( 'falls back to a generic error without a message', async () => {
			const elements = renderForm();

			apiFetch.mockRejectedValue( undefined );
			await updateCount( elements );

			expect( elements.count.textContent ).toBe(
				'The members could not be counted.',
			);
		} );

		it( 'only shows the latest count', async () => {
			const elements = renderForm();
			let resolveFirst;

			apiFetch
				.mockImplementationOnce(
					() =>
						new Promise( ( resolve ) => {
							resolveFirst = resolve;
						} ),
				)
				.mockResolvedValueOnce( { message: 'Latest.' } );

			const first = updateCount( elements );

			await updateCount( elements );
			resolveFirst( { message: 'Stale.' } );
			await first;

			expect( elements.count.textContent ).toBe( 'Latest.' );
		} );
	} );

	describe( 'initSendEmail', () => {
		it( 'does nothing without the send form', () => {
			document.body.innerHTML = '<button></button>';

			expect( () => initSendEmail() ).not.toThrow();
		} );

		it( 'updates the fields and count when the audience changes', async () => {
			const elements = renderForm();

			apiFetch.mockResolvedValue( { message: 'This will email 1 member.' } );
			initSendEmail();
			pick( elements, 'no_rsvp' );
			elements.event.value = '42';
			elements.audience.dispatchEvent( new Event( 'change' ) );

			await new Promise( process.nextTick );

			expect(
				elements.audienceRows[ 2 ].classList.contains( HIDDEN_CLASS ),
			).toBe( false );
			expect( elements.count.textContent ).toBe(
				'This will email 1 member.',
			);
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
				data: {
					subject: 'Hi',
					message: 'Hello members',
					audience: 'all',
					roles: [],
					months: 3,
					event_id: 0,
				},
			} );
		} );
	} );

	describe( 'sendMessage', () => {
		it( 'queues the message and clears the field', async () => {
			const elements = renderForm();

			apiFetch.mockResolvedValue( { message: 'Queued for 2 members.' } );
			await sendMessage( elements );

			expect( statusText() ).toBe( 'Queued for 2 members.' );
			expect( elements.status.classList ).not.toContain( HIDDEN_CLASS );
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

		it( 'asks for a complete audience before sending', async () => {
			const elements = renderForm();

			pick( elements, 'no_rsvp' );
			await sendMessage( elements );

			expect( statusText() ).toBe( 'Choose an event.' );
			expect( window.confirm ).not.toHaveBeenCalled();
		} );

		it( 'does not send when the confirmation is cancelled', async () => {
			const elements = renderForm();

			window.confirm = jest.fn( () => false );
			await sendMessage( elements );

			expect( apiFetch ).not.toHaveBeenCalled();
			expect( elements.status.classList ).toContain( HIDDEN_CLASS );
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
