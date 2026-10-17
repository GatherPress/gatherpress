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
	setAllRoles,
	showAudienceFields,
	updateCount,
} from '@src/helpers/send-email';

jest.mock( '@wordpress/api-fetch' );

const HIDDEN_CLASS = 'gatherpress--is-hidden';

/**
 * Render the send form markup the Email page template outputs.
 *
 * @param {string}  message The message field value.
 * @param {boolean} rsvp    Whether RSVPs are on, which adds the Active users fields.
 *
 * @return {Object} The form elements, as initSendEmail() collects them.
 */
function renderForm( message = 'Hello everyone', rsvp = true ) {
	const active = rsvp
		? `<div class="${ HIDDEN_CLASS }" data-gatherpress_audience="active">
				<select id="gatherpress-message-days">
					<option value="30">30</option>
					<option value="90" selected>90</option>
				</select>
			</div>`
		: '';

	document.body.innerHTML = `
		<div data-gatherpress_component_name="send-email">
			<select id="gatherpress-message-audience">
				<option value="all">All</option>
				<option value="role">Role</option>
				<option value="active">Active</option>
			</select>
			<output class="gatherpress-send-email__count" data-gatherpress_count="2">This will email 2 users.</output>
			<div class="${ HIDDEN_CLASS }" data-gatherpress_audience="role">
				<button type="button" data-gatherpress_roles="all"></button>
				<button type="button" data-gatherpress_roles="none"></button>
				<input type="checkbox" name="gatherpress-message-roles" value="editor" />
				<input type="checkbox" name="gatherpress-message-roles" value="author" />
			</div>
			${ active }
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
		days: document.getElementById( 'gatherpress-message-days' ),
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
		it( 'reads the audience, checked roles and days', () => {
			const elements = renderForm();

			pick( elements, 'role' );
			elements.roles[ 1 ].checked = true;

			expect( readAudience( elements ) ).toEqual( {
				audience: 'role',
				roles: [ 'author' ],
				days: 90,
			} );
		} );

		it( 'leaves out days while RSVPs are off', () => {
			const elements = renderForm( 'Hello everyone', false );

			expect( readAudience( elements ) ).toEqual( {
				audience: 'all',
				roles: [],
			} );
		} );
	} );

	describe( 'getAudienceError', () => {
		it( 'asks for a role when none is checked', () => {
			expect( getAudienceError( { audience: 'role', roles: [] } ) ).toBe(
				'Choose at least one role.',
			);
		} );

		it( 'accepts complete audiences', () => {
			expect(
				getAudienceError( { audience: 'role', roles: [ 'editor' ] } ),
			).toBe( '' );
			expect( getAudienceError( { audience: 'all', roles: [] } ) ).toBe(
				'',
			);
		} );
	} );

	describe( 'setAllRoles', () => {
		it( 'checks and unchecks every role, then counts again', async () => {
			const elements = renderForm();

			pick( elements, 'role' );
			apiFetch.mockResolvedValue( { message: 'This will email 2 users.' } );
			await setAllRoles( elements, true );

			expect( elements.roles.every( ( role ) => role.checked ) ).toBe(
				true,
			);
			expect( apiFetch ).toHaveBeenCalledTimes( 1 );

			await setAllRoles( elements, false );

			expect( elements.roles.some( ( role ) => role.checked ) ).toBe(
				false,
			);
			expect( elements.count.textContent ).toBe(
				'Choose at least one role.',
			);
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
			).toEqual( [ true, false ] );
		} );
	} );

	describe( 'updateCount', () => {
		it( 'shows the count the server returns', async () => {
			const elements = renderForm();

			pick( elements, 'active' );
			apiFetch.mockResolvedValue( {
				message: 'This will email 5 users.',
				count: 5,
			} );
			await updateCount( elements );

			expect( apiFetch ).toHaveBeenCalledWith( {
				path: '/gatherpress/v1/site-message/count?audience=active&days=90',
			} );
			expect( elements.count.textContent ).toBe(
				'This will email 5 users.',
			);
			expect( elements.count.dataset.gatherpress_count ).toBe( '5' );
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

			apiFetch.mockRejectedValue( { message: 'RSVPs are turned off.' } );
			await updateCount( elements );

			expect( elements.count.textContent ).toBe( 'RSVPs are turned off.' );
		} );

		it( 'falls back to a generic error without a message', async () => {
			const elements = renderForm();

			apiFetch.mockRejectedValue( undefined );
			await updateCount( elements );

			expect( elements.count.textContent ).toBe(
				'The users could not be counted.',
			);
			expect( elements.count.dataset.gatherpress_count ).toBeUndefined();
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

			apiFetch.mockResolvedValue( { message: 'This will email 1 user.' } );
			initSendEmail();
			pick( elements, 'active' );
			elements.audience.dispatchEvent( new Event( 'change' ) );

			await new Promise( process.nextTick );

			expect(
				elements.audienceRows[ 1 ].classList.contains( HIDDEN_CLASS ),
			).toBe( false );
			expect( elements.count.textContent ).toBe(
				'This will email 1 user.',
			);
		} );

		it( 'checks every role from Select all', async () => {
			const elements = renderForm( 'Hello everyone', false );

			apiFetch.mockResolvedValue( { message: 'This will email 2 users.' } );
			initSendEmail();
			pick( elements, 'role' );
			document.querySelector( '[data-gatherpress_roles="all"]' ).click();

			await new Promise( process.nextTick );

			expect( elements.roles.every( ( role ) => role.checked ) ).toBe(
				true,
			);

			document.querySelector( '[data-gatherpress_roles="none"]' ).click();

			expect( elements.roles.some( ( role ) => role.checked ) ).toBe(
				false,
			);
		} );

		it( 'sends the message when the button is clicked', async () => {
			const { button } = renderForm();

			apiFetch.mockResolvedValue( { message: 'Queued for 2 users.' } );
			initSendEmail();
			button.click();

			await new Promise( process.nextTick );

			expect( apiFetch ).toHaveBeenCalledWith( {
				path: '/gatherpress/v1/site-message',
				method: 'POST',
				data: {
					subject: 'Hi',
					message: 'Hello everyone',
					audience: 'all',
					roles: [],
					days: 90,
				},
			} );
		} );
	} );

	describe( 'sendMessage', () => {
		it( 'queues the message and clears the field', async () => {
			const elements = renderForm();

			apiFetch.mockResolvedValue( { message: 'Queued for 2 users.' } );
			await sendMessage( elements );

			expect( statusText() ).toBe( 'Queued for 2 users.' );
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

		it( 'does not send when no users would get it', async () => {
			const elements = renderForm();

			elements.count.dataset.gatherpress_count = '0';
			await sendMessage( elements );

			expect( statusText() ).toBe(
				'There are no users to send this message to.',
			);
			expect( window.confirm ).not.toHaveBeenCalled();
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

			pick( elements, 'role' );
			await sendMessage( elements );

			expect( statusText() ).toBe( 'Choose at least one role.' );
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
				message: 'There are no users to send this message to.',
			} );
			await sendMessage( elements );

			expect( statusText() ).toBe(
				'There are no users to send this message to.',
			);
			expect( elements.message.value ).toBe( 'Hello everyone' );
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
