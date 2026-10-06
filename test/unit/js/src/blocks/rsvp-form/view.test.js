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
} );
