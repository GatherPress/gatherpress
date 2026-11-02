/**
 * External dependencies
 */
import { describe, expect, it } from '@jest/globals';
import '@testing-library/jest-dom';

/**
 * Internal dependencies
 */
import {
	FIELD_ERROR_CLASS,
	FORM_ERROR_CLASS,
	clearFormErrors,
	reportSubmissionError,
	showFieldErrors,
	showFormError,
} from '@src/blocks/rsvp-form/form-errors';

/**
 * Builds a form with a text field, a field carrying help text, and a radio group.
 *
 * @return {HTMLFormElement} The form, attached to the document so focus works.
 */
const buildForm = () => {
	document.body.innerHTML = `
		<form
			id="rsvp-form"
			data-gatherpress-error-message="Sorry, there was an issue processing your RSVP. Please try again."
		>
			<div class="wp-block-gatherpress-form-field">
				<label for="field_dietary">Dietary needs</label>
				<input type="text" id="field_dietary" name="dietary" />
			</div>
			<div class="wp-block-gatherpress-form-field">
				<label for="field_contact">Backup email</label>
				<input
					type="email"
					id="field_contact"
					name="contact"
					aria-describedby="field_contact-help"
				/>
				<p class="gatherpress-help-text" id="field_contact-help">We only use this if the first bounces.</p>
			</div>
			<fieldset id="field_tshirt">
				<legend>T-shirt size</legend>
				<input type="radio" id="field_tshirt_s" name="tshirt" value="S" />
				<input type="radio" id="field_tshirt_m" name="tshirt" value="M" />
			</fieldset>
			<div class="wp-block-gatherpress-form-field">
				<input type="radio" name="carpool" value="yes" />
				<input type="radio" name="carpool" value="no" />
			</div>
			<div class="wp-block-gatherpress-form-field">
				<input type="text" name="nickname" />
			</div>
			<input type="hidden" name="gatherpress_form_schema_id" value="form_0" />
		</form>
	`;

	return document.getElementById( 'rsvp-form' );
};

describe( 'showFieldErrors', () => {
	it( 'places each message against its own field', () => {
		const form = buildForm();

		showFieldErrors( form, {
			dietary: 'Dietary needs is required.',
			contact: 'Backup email must be a valid email address.',
		} );

		const dietary = document.getElementById( 'field_dietary-error' );
		const contact = document.getElementById( 'field_contact-error' );

		expect( dietary ).toHaveTextContent( 'Dietary needs is required.' );
		expect( contact ).toHaveTextContent(
			'Backup email must be a valid email address.',
		);
		expect( dietary.parentElement ).toBe(
			document.getElementById( 'field_dietary' ).parentElement,
		);
		expect( dietary.parentElement.lastElementChild ).toBe( dietary );
	} );

	it( 'marks the input invalid and describes it with the message', () => {
		const form = buildForm();

		showFieldErrors( form, { dietary: 'Dietary needs is required.' } );

		const input = document.getElementById( 'field_dietary' );

		expect( input ).toHaveAttribute( 'aria-invalid', 'true' );
		expect( input ).toHaveAttribute(
			'aria-describedby',
			'field_dietary-error',
		);
	} );

	it( 'keeps existing help text in aria-describedby', () => {
		const form = buildForm();

		showFieldErrors( form, { contact: 'Backup email must be valid.' } );

		expect( document.getElementById( 'field_contact' ) ).toHaveAttribute(
			'aria-describedby',
			'field_contact-help field_contact-error',
		);
	} );

	it( 'focuses the first failing field', () => {
		const form = buildForm();

		showFieldErrors( form, {
			dietary: 'Dietary needs is required.',
			contact: 'Backup email must be valid.',
		} );

		expect( document.activeElement.id ).toBe( 'field_dietary' );
	} );

	it( 'reports a radio group once, on its fieldset', () => {
		const form = buildForm();

		showFieldErrors( form, { tshirt: 'T-shirt size is required.' } );

		const fieldset = document.getElementById( 'field_tshirt' );

		expect( fieldset ).toHaveAttribute( 'aria-invalid', 'true' );
		expect( fieldset ).toHaveAttribute(
			'aria-describedby',
			'field_tshirt-error',
		);
		expect(
			form.querySelectorAll( `.${ FIELD_ERROR_CLASS }` ),
		).toHaveLength( 1 );
		// The message closes the group rather than landing between an option
		// and the label that belongs to it.
		expect( fieldset.lastElementChild.id ).toBe( 'field_tshirt-error' );
	} );

	it( 'focuses into a fieldset rather than the fieldset itself', () => {
		const form = buildForm();

		showFieldErrors( form, { tshirt: 'T-shirt size is required.' } );

		expect( document.activeElement.id ).toBe( 'field_tshirt_s' );
	} );

	it( 'clears a field error once the person edits that field', () => {
		const form = buildForm();

		showFieldErrors( form, { dietary: 'Dietary needs is required.' } );

		const input = document.getElementById( 'field_dietary' );

		input.dispatchEvent( new Event( 'input', { bubbles: true } ) );

		expect( document.getElementById( 'field_dietary-error' ) ).toBeNull();
		expect( input ).not.toHaveAttribute( 'aria-invalid' );
		expect( input ).not.toHaveAttribute( 'aria-describedby' );
	} );

	it( 'leaves other fields marked when one is corrected', () => {
		const form = buildForm();

		showFieldErrors( form, {
			dietary: 'Dietary needs is required.',
			contact: 'Backup email must be valid.',
		} );

		document
			.getElementById( 'field_dietary' )
			.dispatchEvent( new Event( 'input', { bubbles: true } ) );

		expect( document.getElementById( 'field_contact-error' ) ).not.toBeNull();
		expect( document.getElementById( 'field_contact' ) ).toHaveAttribute(
			'aria-invalid',
			'true',
		);
	} );

	it( 'restores help text when a field error clears', () => {
		const form = buildForm();

		showFieldErrors( form, { contact: 'Backup email must be valid.' } );

		document
			.getElementById( 'field_contact' )
			.dispatchEvent( new Event( 'input', { bubbles: true } ) );

		expect( document.getElementById( 'field_contact' ) ).toHaveAttribute(
			'aria-describedby',
			'field_contact-help',
		);
	} );

	it( 'falls back to a form message when the field is not on the form', () => {
		const form = buildForm();

		const shown = showFieldErrors( form, {
			gone: 'Answer is required.',
		} );

		expect( shown ).toEqual( [] );
		expect( form.querySelector( `.${ FORM_ERROR_CLASS }` ) ).toHaveTextContent(
			'Answer is required.',
		);
	} );

	it( 'returns an empty list when there is no form or no errors', () => {
		expect( showFieldErrors( null, { a: 'b' } ) ).toEqual( [] );
		expect( showFieldErrors( buildForm(), null ) ).toEqual( [] );
	} );

	it( 'marks the input itself when a group has no fieldset', () => {
		const form = buildForm();

		showFieldErrors( form, { carpool: 'Carpooling is required.' } );

		const first = form.querySelector( '[name="carpool"]' );

		expect( first ).toHaveAttribute( 'aria-invalid', 'true' );
		expect( first.parentElement.lastElementChild.id ).toBe(
			'rsvp-form-carpool-error',
		);
		expect( form.querySelectorAll( `.${ FIELD_ERROR_CLASS }` ) ).toHaveLength(
			1,
		);
	} );

	it( 'falls back to the field name when the input has no id', () => {
		const form = buildForm();

		showFieldErrors( form, { nickname: 'Nickname is not valid.' } );

		expect(
			document.getElementById( 'rsvp-form-nickname-error' ),
		).toHaveTextContent( 'Nickname is not valid.' );
	} );

	it( 'escapes a field name that would otherwise break the selector', () => {
		const form = buildForm();
		const input = document.createElement( 'input' );

		input.type = 'text';
		input.id = 'field_odd';
		input.name = 'odd"name';
		form.append( input );

		expect( () =>
			showFieldErrors( form, { 'odd"name': 'Odd is required.' } ),
		).not.toThrow();
		// Nothing wraps this input, so the message goes straight after it.
		expect( input.nextElementSibling.id ).toBe( 'field_odd-error' );
	} );

	it( 'describes a field once when the same error is reported twice', () => {
		const form = buildForm();

		showFieldErrors( form, { dietary: 'Dietary needs is required.' } );
		showFieldErrors( form, { dietary: 'Dietary needs is required.' } );

		expect( document.getElementById( 'field_dietary' ) ).toHaveAttribute(
			'aria-describedby',
			'field_dietary-error',
		);
	} );

	it( 'reports a hidden field against the form, not the input', () => {
		const form = buildForm();

		const shown = showFieldErrors( form, {
			gatherpress_form_schema_id:
				'This form could not be verified. Please reload the page and try again.',
		} );

		expect( shown ).toEqual( [] );
		expect( form.querySelector( `.${ FORM_ERROR_CLASS }` ) ).toHaveTextContent(
			'This form could not be verified. Please reload the page and try again.',
		);
		expect(
			form.querySelector( '[name="gatherpress_form_schema_id"]' ),
		).not.toHaveAttribute( 'aria-invalid' );
	} );

	it( 'focuses the visible field when a hidden one failed too', () => {
		const form = buildForm();

		const shown = showFieldErrors( form, {
			gatherpress_form_schema_id: 'This form could not be verified.',
			dietary: 'Dietary needs is required.',
		} );

		expect( shown ).toEqual( [ 'dietary' ] );
		expect( form.querySelector( `.${ FORM_ERROR_CLASS }` ) ).toHaveTextContent(
			'This form could not be verified.',
		);
		expect( document.activeElement.id ).toBe( 'field_dietary' );
	} );

	it( 'moves focus nowhere when there is nothing to report', () => {
		const form = buildForm();

		expect( showFieldErrors( form, {} ) ).toEqual( [] );
		expect( form.querySelectorAll( `.${ FIELD_ERROR_CLASS }` ) ).toHaveLength(
			0,
		);
		expect( form.querySelector( `.${ FORM_ERROR_CLASS }` ) ).toBeNull();
	} );
} );

describe( 'showFormError', () => {
	it( 'prepends an alert to the form and focuses it', () => {
		const form = buildForm();

		showFormError( form, "You've already RSVP'd to this event." );

		const element = form.querySelector( `.${ FORM_ERROR_CLASS }` );

		expect( element ).toHaveTextContent(
			"You've already RSVP'd to this event.",
		);
		expect( element ).toHaveAttribute( 'role', 'alert' );
		expect( form.firstElementChild ).toBe( element );
		expect( document.activeElement ).toBe( element );
	} );

	it( 'returns null without a form', () => {
		expect( showFormError( null, 'Nope.' ) ).toBeNull();
	} );

	it( 'shows nothing rather than an empty alert', () => {
		const form = buildForm();

		expect( showFormError( form, '' ) ).toBeNull();
		expect( form.querySelector( `.${ FORM_ERROR_CLASS }` ) ).toBeNull();
	} );
} );

describe( 'clearFormErrors', () => {
	it( 'removes every message and its markings', () => {
		const form = buildForm();

		showFieldErrors( form, { dietary: 'Dietary needs is required.' } );
		showFormError( form, 'Something else went wrong.' );
		clearFormErrors( form );

		expect( form.querySelectorAll( `.${ FIELD_ERROR_CLASS }` ) ).toHaveLength(
			0,
		);
		expect( form.querySelectorAll( `.${ FORM_ERROR_CLASS }` ) ).toHaveLength(
			0,
		);
		expect( form.querySelectorAll( '[aria-invalid]' ) ).toHaveLength( 0 );
	} );

	it( 'leaves help text in place', () => {
		const form = buildForm();

		showFieldErrors( form, { contact: 'Backup email must be valid.' } );
		clearFormErrors( form );

		expect( document.getElementById( 'field_contact' ) ).toHaveAttribute(
			'aria-describedby',
			'field_contact-help',
		);
		expect( document.getElementById( 'field_contact-help' ) ).not.toBeNull();
	} );

	it( 'unmarks every field when two were reported', () => {
		const form = buildForm();

		showFieldErrors( form, {
			dietary: 'Dietary needs is required.',
			contact: 'Backup email must be valid.',
		} );
		clearFormErrors( form );

		expect( document.getElementById( 'field_contact' ) ).toHaveAttribute(
			'aria-describedby',
			'field_contact-help',
		);
		expect( form.querySelectorAll( '[aria-invalid]' ) ).toHaveLength( 0 );
	} );

	it( 'clears a field the server marked invalid on the page itself', () => {
		const form = buildForm();
		const input = document.getElementById( 'field_dietary' );

		// The no-JS submission path marks the field server-side, so there is
		// no aria-describedby to strip when the next attempt clears it.
		input.setAttribute( 'aria-invalid', 'true' );

		clearFormErrors( form );

		expect( input ).not.toHaveAttribute( 'aria-invalid' );
		expect( input ).not.toHaveAttribute( 'aria-describedby' );
	} );

	it( 'does nothing without a form', () => {
		expect( () => clearFormErrors( null ) ).not.toThrow();
	} );
} );

describe( 'reportSubmissionError', () => {
	it( 'prefers the per-field messages', () => {
		const form = buildForm();

		reportSubmissionError( form, {
			success: false,
			message: 'Dietary needs is required.',
			errors: { dietary: 'Dietary needs is required.' },
		} );

		expect( document.getElementById( 'field_dietary-error' ) ).not.toBeNull();
		expect( form.querySelector( `.${ FORM_ERROR_CLASS }` ) ).toBeNull();
	} );

	it( 'falls back to the summary when no field is named', () => {
		const form = buildForm();

		reportSubmissionError( form, {
			success: false,
			message: "You've already RSVP'd to this event.",
		} );

		expect( form.querySelector( `.${ FORM_ERROR_CLASS }` ) ).toHaveTextContent(
			"You've already RSVP'd to this event.",
		);
	} );

	it( 'falls back to the message the server rendered on the form', () => {
		const form = buildForm();

		reportSubmissionError( form, {} );

		expect( form.querySelector( `.${ FORM_ERROR_CLASS }` ) ).toHaveTextContent(
			'Sorry, there was an issue processing your RSVP. Please try again.',
		);
	} );

	it( 'shows nothing when the form carries no fallback message', () => {
		const form = buildForm();

		form.removeAttribute( 'data-gatherpress-error-message' );
		reportSubmissionError( form, {} );

		expect( form.querySelector( `.${ FORM_ERROR_CLASS }` ) ).toBeNull();
	} );

	it( 'replaces the previous attempt rather than stacking', () => {
		const form = buildForm();

		reportSubmissionError( form, {
			errors: { dietary: 'Dietary needs is required.' },
		} );
		reportSubmissionError( form, {
			errors: { contact: 'Backup email must be valid.' },
		} );

		expect( document.getElementById( 'field_dietary-error' ) ).toBeNull();
		expect( document.getElementById( 'field_contact-error' ) ).not.toBeNull();
		expect(
			form.querySelectorAll( `.${ FIELD_ERROR_CLASS }` ),
		).toHaveLength( 1 );
	} );
} );
