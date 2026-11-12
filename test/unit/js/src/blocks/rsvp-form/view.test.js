/**
 * External dependencies
 */
import {
	describe,
	expect,
	it,
	jest,
	beforeEach,
	afterEach,
} from '@jest/globals';
import '@testing-library/jest-dom';

/**
 * Mock the Interactivity API with a namespace-merging store, so the RSVP
 * form view and the helpers share one `gatherpress` registry, as they do
 * at runtime.
 */
jest.mock(
	'@wordpress/interactivity',
	() => {
		const registries = {};

		return {
			store: ( name, config = {} ) => {
				if ( ! registries[ name ] ) {
					registries[ name ] = {
						state: {},
						actions: {},
						callbacks: {},
					};
				}

				const registry = registries[ name ];

				Object.assign( registry.state, config.state );
				Object.assign( registry.actions, config.actions );
				Object.assign( registry.callbacks, config.callbacks );

				return registry;
			},
			getElement: jest.fn(),
			getContext: jest.fn(),
		};
	},
	{ virtual: true },
);

/**
 * WordPress dependencies
 */
import { store, getElement, getContext } from '@wordpress/interactivity';

/**
 * Internal dependencies
 */
import { getNonce } from '@src/helpers/interactivity';
import { FORM_ERROR_CLASS } from '@src/blocks/rsvp-form/form-errors';
import '@src/blocks/rsvp-form/view';

/**
 * Builds the Standard RSVP Form as the server renders it: a hidden success
 * group, a hidden past-event group, the fields, and the submit button, each
 * with its visibility rules.
 *
 * @return {HTMLFormElement} The form, attached to the document so focus works.
 */
const buildForm = () => {
	document.body.innerHTML = `
		<form id="rsvp-form" data-gatherpress-error-message="Sorry, there was an issue processing your RSVP. Please try again.">
			<div class="success" aria-hidden="true" style="display: none;" data-gatherpress-rsvp-form-visibility='{"onSuccess":"show","whenPast":"hide"}'>
				<h3>Thank you for your RSVP!</h3>
				<p>Please check your email for a confirmation link to complete your registration.</p>
			</div>
			<div class="past" aria-hidden="true" style="display: none;" data-gatherpress-rsvp-form-visibility='{"whenPast":"show"}'>
				<p>This event has already occurred.</p>
			</div>
			<div class="field" aria-hidden="false" data-gatherpress-rsvp-form-visibility='{"onSuccess":"hide","whenPast":"hide"}'>
				<label for="author">Name</label>
				<input type="text" id="author" name="author" value="Sam Visitor" />
			</div>
			<div class="field" aria-hidden="false" data-gatherpress-rsvp-form-visibility='{"onSuccess":"hide","whenPast":"hide"}'>
				<label for="email">Email</label>
				<input type="email" id="email" name="email" value="sam@example.test" />
			</div>
			<div class="buttons" aria-hidden="false" data-gatherpress-rsvp-form-visibility='{"onSuccess":"hide","whenPast":"hide"}'>
				<div class="gatherpress-submit-button"><button type="submit">Submit</button></div>
			</div>
		</form>
	`;

	return document.getElementById( 'rsvp-form' );
};

describe( 'RSVP form submission success', () => {
	let state;
	let actions;
	let rsvpFormResponse;

	beforeEach( () => {
		( { state, actions } = store( 'gatherpress' ) );

		state.eventApiUrl = 'https://example.test/wp-json/gatherpress/v1';
		state.rsvpForm.isSubmitting = false;

		getNonce.clearCache();

		rsvpFormResponse = {
			success: true,
			message: 'Your RSVP has been submitted successfully!',
		};

		global.fetch = jest.fn( ( url ) => {
			if ( url.endsWith( '/nonce' ) ) {
				return Promise.resolve( {
					json: () => Promise.resolve( { nonce: 'test-nonce' } ),
				} );
			}

			// POST /rsvp-form.
			return Promise.resolve( {
				status: 200,
				json: () => Promise.resolve( rsvpFormResponse ),
			} );
		} );
	} );

	/**
	 * Submits the form through the store action with the submit button focused.
	 *
	 * @param {HTMLFormElement} form The RSVP form.
	 *
	 * @return {Promise<void>} Resolves when the submission has been handled.
	 */
	const submit = async ( form ) => {
		getElement.mockReturnValue( { ref: form } );
		getContext.mockReturnValue( { postId: 123 } );
		form.querySelector( 'button[type="submit"]' ).focus();

		await actions.handleRsvpFormSubmit( { preventDefault: jest.fn() } );
	};

	afterEach( () => {
		jest.restoreAllMocks();
		delete global.fetch;
	} );

	it( 'moves focus to the success message when the submit button is hidden', async () => {
		const form = buildForm();
		const submitButton = form.querySelector( 'button[type="submit"]' );

		getElement.mockReturnValue( { ref: form } );
		getContext.mockReturnValue( { postId: 123 } );

		submitButton.focus();
		expect( submitButton ).toHaveFocus();

		await actions.handleRsvpFormSubmit( { preventDefault: jest.fn() } );

		const success = form.querySelector( '.success' );

		expect( form.querySelector( '.buttons' ) ).not.toBeVisible();
		expect( success ).toBeVisible();
		expect( success ).toHaveFocus();
	} );

	it( 'leaves focus with the error message when the submission fails', async () => {
		const form = buildForm();

		rsvpFormResponse = {
			success: false,
			message: "You've already RSVP'd to this event.",
		};

		await submit( form );

		expect( form.querySelector( `.${ FORM_ERROR_CLASS }` ) ).toHaveFocus();
		expect( form.querySelector( '.success' ) ).not.toHaveAttribute( 'tabindex' );
	} );

	it( 'makes nothing focusable when no block is shown on success', async () => {
		const form = buildForm();

		form.querySelector( '.success' ).remove();

		await submit( form );

		expect( form.querySelectorAll( '[tabindex]' ) ).toHaveLength( 0 );
	} );

	it( 'focuses the first block shown on success', async () => {
		const form = buildForm();
		const second = form.querySelector( '.success' ).cloneNode( true );

		second.classList.replace( 'success', 'success-second' );
		form.querySelector( '.past' ).before( second );

		await submit( form );

		expect( form.querySelector( '.success' ) ).toHaveFocus();
		expect( form.querySelector( '.success-second' ) ).toBeVisible();
		expect( form.querySelector( '.success-second' ) ).not.toHaveAttribute( 'tabindex' );
	} );

	it( 'submits the form normally and shows no error when no nonce arrives', async () => {
		const form = buildForm();
		const submitButton = form.querySelector( 'button[type="submit"]' );

		// The nonce request fails, as a 500 response without JSON does.
		global.fetch = jest.fn( () => Promise.reject( new Error( 'Server error' ) ) );
		form.submit = jest.fn();

		await submit( form );

		expect( form.submit ).toHaveBeenCalledTimes( 1 );
		expect( form.querySelector( `.${ FORM_ERROR_CLASS }` ) ).toBeNull();
		expect( form.querySelector( '[role="alert"]' ) ).toBeNull();
		expect( submitButton ).toHaveFocus();
		// Only the nonce was requested; the RSVP itself goes with the form.
		expect( global.fetch ).toHaveBeenCalledTimes( 1 );
	} );

	it( 'does not send the form again while the fallback submission loads', async () => {
		const form = buildForm();

		global.fetch = jest.fn( () => Promise.reject( new Error( 'Server error' ) ) );
		form.submit = jest.fn();

		await submit( form );
		await submit( form );

		expect( form.submit ).toHaveBeenCalledTimes( 1 );
		expect( state.rsvpForm.isSubmitting ).toBe( true );
		expect(
			form.querySelector( '.gatherpress-submit-button' ),
		).toHaveClass( 'gatherpress--is-loading' );
	} );

	it( 'keeps the guard on when a failed RSVP request falls back', async () => {
		const form = buildForm();

		global.fetch = jest.fn( ( url ) => {
			if ( url.endsWith( '/nonce' ) ) {
				return Promise.resolve( {
					json: () => Promise.resolve( { nonce: 'test-nonce' } ),
				} );
			}

			// The RSVP request fails on the network.
			return Promise.reject( new Error( 'Network error' ) );
		} );
		form.submit = jest.fn();

		await submit( form );
		await submit( form );

		expect( console ).toHaveWarned();
		expect( form.submit ).toHaveBeenCalledTimes( 1 );
		expect( state.rsvpForm.isSubmitting ).toBe( true );
	} );

	it( 'falls back without an error when the nonce retry after a 403 fails', async () => {
		const form = buildForm();
		let nonceRequests = 0;

		global.fetch = jest.fn( ( url ) => {
			if ( url.endsWith( '/nonce' ) ) {
				nonceRequests++;

				// The first nonce works; the server rejects it, and the
				// fresh nonce cannot be fetched.
				return 1 === nonceRequests
					? Promise.resolve( {
						json: () => Promise.resolve( { nonce: 'stale-nonce' } ),
					} )
					: Promise.reject( new Error( 'Server error' ) );
			}

			return Promise.resolve( {
				status: 403,
				json: () => Promise.resolve( { code: 'rest_cookie_invalid_nonce' } ),
			} );
		} );
		form.submit = jest.fn();

		await submit( form );

		expect( nonceRequests ).toBe( 2 );
		expect( form.submit ).toHaveBeenCalledTimes( 1 );
		expect( form.querySelector( `.${ FORM_ERROR_CLASS }` ) ).toBeNull();
		expect( form.querySelector( '[role="alert"]' ) ).toBeNull();
	} );

	it( 'makes the form usable again when the page returns from the back/forward cache', async () => {
		const form = buildForm();
		const wrapper = form.querySelector( '.gatherpress-submit-button' );
		const { callbacks } = store( 'gatherpress' );

		getElement.mockReturnValue( { ref: form } );
		getContext.mockReturnValue( { postId: 123 } );
		callbacks.initRsvpForm();

		global.fetch = jest.fn( () => Promise.reject( new Error( 'Server error' ) ) );
		form.submit = jest.fn();

		await submit( form );

		expect( wrapper ).toHaveClass( 'gatherpress--is-loading' );

		// A normal page load does not change the state.
		const fresh = new Event( 'pageshow' );
		Object.defineProperty( fresh, 'persisted', { value: false } );
		window.dispatchEvent( fresh );

		expect( state.rsvpForm.isSubmitting ).toBe( true );

		const restored = new Event( 'pageshow' );
		Object.defineProperty( restored, 'persisted', { value: true } );
		window.dispatchEvent( restored );

		expect( state.rsvpForm.isSubmitting ).toBe( false );
		expect( wrapper ).not.toHaveClass( 'gatherpress--is-loading' );
	} );
} );

describe( 'RSVP form initial visibility', () => {
	let callbacks;

	beforeEach( () => {
		( { callbacks } = store( 'gatherpress' ) );
	} );

	afterEach( () => {
		document.body.innerHTML = '';
	} );

	/**
	 * Builds the form for an event in the given state, with a canceled notice.
	 *
	 * @param {string} eventState The form's data-gatherpress-event-state value.
	 *
	 * @return {HTMLFormElement} The form.
	 */
	const buildStateForm = ( eventState ) => {
		const form = buildForm();
		const notice = document.createElement( 'div' );

		notice.className = 'canceled';
		notice.dataset.gatherpressRsvpFormVisibility = '{"whenCanceled":"show"}';
		form.prepend( notice );

		if ( eventState ) {
			form.dataset.gatherpressEventState = eventState;
		}

		getElement.mockReturnValue( { ref: form } );
		getContext.mockReturnValue( { postId: 123 } );
		callbacks.initRsvpForm();

		return form;
	};

	it( 'shows only the canceled notice when the event is canceled', () => {
		const form = buildStateForm( 'canceled' );

		expect( form.querySelector( '.canceled' ) ).toBeVisible();
		expect( form.querySelector( '.past' ) ).not.toBeVisible();
		expect( form.querySelector( '.field' ) ).not.toBeVisible();
		expect( form.querySelector( '.buttons' ) ).not.toBeVisible();
	} );

	it( 'hides the canceled notice when the event is not canceled', () => {
		const form = buildStateForm( '' );

		expect( form.querySelector( '.canceled' ) ).not.toBeVisible();
		expect( form.querySelector( '.field' ) ).toBeVisible();
	} );

	it( 'follows whenCanceled set to hide on a canceled event', () => {
		const form = buildForm();

		form.querySelector( '.past' ).dataset.gatherpressRsvpFormVisibility = '{"whenCanceled":"hide"}';
		form.dataset.gatherpressEventState = 'canceled';
		getElement.mockReturnValue( { ref: form } );
		getContext.mockReturnValue( { postId: 123 } );
		callbacks.initRsvpForm();

		expect( form.querySelector( '.past' ) ).not.toBeVisible();
	} );
} );
