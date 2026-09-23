/**
 * Class marking a field the server rejected.
 *
 * @since TBD
 * @type {string}
 */
export const FIELD_ERROR_CLASS = 'gatherpress-field-error';

/**
 * Class marking the form-level message, used when no single field is at fault.
 *
 * @since TBD
 * @type {string}
 */
export const FORM_ERROR_CLASS = 'gatherpress-form-error';

/**
 * Finds the inputs a schema field name refers to.
 *
 * A radio group shares one name across several inputs, so this returns a list
 * rather than a single element.
 *
 * @since TBD
 *
 * @param {HTMLFormElement} form      The RSVP form.
 * @param {string}          fieldName The field's name attribute.
 *
 * @return {HTMLElement[]} The matching inputs, empty when the field is absent.
 */
const getFieldInputs = ( form, fieldName ) => {
	// CSS.escape keeps a field name with unusual characters from breaking the
	// selector. It exists in every browser the block editor supports.
	const escaped = CSS.escape( fieldName );

	return Array.from( form.querySelectorAll( `[name="${ escaped }"]` ) );
};

/**
 * Finds the element an error message should be announced against.
 *
 * For a radio group that is the fieldset wrapping it, so the message is read
 * once with the group rather than on every option.
 *
 * @since TBD
 *
 * @param {HTMLElement[]} inputs The inputs sharing the field's name.
 *
 * @return {HTMLElement} The element to mark invalid.
 */
const getDescribedElement = ( inputs ) => {
	if ( 1 < inputs.length ) {
		return inputs[ 0 ].closest( 'fieldset' ) ?? inputs[ 0 ];
	}

	return inputs[ 0 ];
};

/**
 * Adds an id to an element's aria-describedby without losing what is there.
 *
 * A field with help text is already described by it, and the error has to join
 * that rather than replace it.
 *
 * @since TBD
 *
 * @param {HTMLElement} element The element being described.
 * @param {string}      id      The id to add.
 *
 * @return {void}
 */
const addDescribedBy = ( element, id ) => {
	const existing = ( element.getAttribute( 'aria-describedby' ) || '' )
		.split( ' ' )
		.filter( Boolean );

	if ( ! existing.includes( id ) ) {
		existing.push( id );
	}

	element.setAttribute( 'aria-describedby', existing.join( ' ' ) );
};

/**
 * Removes an id from an element's aria-describedby, leaving the rest.
 *
 * @since TBD
 *
 * @param {HTMLElement} element The element being described.
 * @param {string}      id      The id to remove.
 *
 * @return {void}
 */
const removeDescribedBy = ( element, id ) => {
	const remaining = ( element.getAttribute( 'aria-describedby' ) || '' )
		.split( ' ' )
		.filter( ( value ) => value && value !== id );

	if ( remaining.length ) {
		element.setAttribute( 'aria-describedby', remaining.join( ' ' ) );
	} else {
		element.removeAttribute( 'aria-describedby' );
	}
};

/**
 * Clears every error the last submission left behind.
 *
 * Runs before each submission so a fixed field stops being reported, and so a
 * second failure does not stack messages on top of the first.
 *
 * @since TBD
 *
 * @param {HTMLFormElement} form The RSVP form.
 *
 * @return {void}
 */
export const clearFormErrors = ( form ) => {
	if ( ! form ) {
		return;
	}

	const messages = Array.from(
		form.querySelectorAll( `.${ FIELD_ERROR_CLASS }` ),
	);

	// Every message is dropped from every marked field before any of them is
	// removed, so a form reporting two fields does not leave the second one
	// described by an element that is already gone.
	form.querySelectorAll( '[aria-invalid="true"]' ).forEach( ( described ) => {
		messages.forEach( ( element ) =>
			removeDescribedBy( described, element.id ),
		);
		described.removeAttribute( 'aria-invalid' );
	} );

	messages.forEach( ( element ) => element.remove() );

	form.querySelectorAll( `.${ FORM_ERROR_CLASS }` ).forEach( ( element ) =>
		element.remove(),
	);
};

/**
 * Shows a message that belongs to the form rather than to a field.
 *
 * A duplicate RSVP or a closed event fails the whole submission, so there is
 * no input to attach it to.
 *
 * @since TBD
 *
 * @param {HTMLFormElement} form    The RSVP form.
 * @param {string}          message The message to show.
 *
 * @return {HTMLElement|null} The element created, or null when there is nothing to show.
 */
export const showFormError = ( form, message ) => {
	// An empty alert announces nothing and leaves a stray box on the form, so
	// there is nothing worth inserting when no message reached us.
	if ( ! form || ! message ) {
		return null;
	}

	const element = document.createElement( 'div' );

	element.className = FORM_ERROR_CLASS;
	element.setAttribute( 'role', 'alert' );
	element.setAttribute( 'tabindex', '-1' );
	element.textContent = message;

	form.prepend( element );
	element.focus();

	return element;
};

/**
 * Shows the server's per-field messages against the fields they belong to.
 *
 * Each message is placed after its input, associated with `aria-describedby`,
 * and the input is marked `aria-invalid`. Focus moves to the first failing
 * field so a keyboard or screen reader user lands on something actionable
 * rather than having to hunt for it.
 *
 * A message whose field is not in the form, which can happen when a stored
 * schema names a field the rendered form no longer has, falls back to the
 * form-level message so nothing is silently dropped.
 *
 * @since TBD
 *
 * @param {HTMLFormElement}       form   The RSVP form.
 * @param {Object<string,string>} errors Messages keyed by field name.
 *
 * @return {string[]} The field names that were shown against an input.
 */
export const showFieldErrors = ( form, errors ) => {
	if ( ! form || ! errors ) {
		return [];
	}

	const shown = [];
	const orphaned = [];
	let firstInvalid = null;

	Object.entries( errors ).forEach( ( [ fieldName, message ] ) => {
		// A hidden input, the schema id among them, has nothing on screen a
		// person could fix, and assistive technology ignores it. Leaving it
		// out sends the message to the form-level alert instead.
		const inputs = getFieldInputs( form, fieldName ).filter(
			( input ) => 'hidden' !== input.type,
		);

		if ( ! inputs.length ) {
			orphaned.push( message );
			return;
		}

		const described = getDescribedElement( inputs );
		const anchor = inputs.at( -1 );
		// An input carries an id unique to its form, but the fieldset around a
		// radio group does not, so the form's own id keeps the fallback from
		// colliding with the same field on a second form on the page.
		const errorId = described.id
			? `${ described.id }-error`
			: `${ form.id }-${ fieldName }-error`;
		const element = document.createElement( 'p' );

		element.className = FIELD_ERROR_CLASS;
		element.id = errorId;
		element.textContent = message;

		// At the end of the field's own block, so the message sits below the
		// input, its help text, and every option of a radio group. Anchoring
		// to the last input instead drops it between an option and the label
		// that belongs to it.
		const container =
			anchor.closest( '.wp-block-gatherpress-form-field' ) ??
			anchor.closest( 'fieldset' );

		if ( container ) {
			container.append( element );
		} else {
			anchor.insertAdjacentElement( 'afterend', element );
		}

		described.setAttribute( 'aria-invalid', 'true' );
		addDescribedBy( described, errorId );

		if ( ! firstInvalid ) {
			firstInvalid = described;
		}

		// Clear this field's error as soon as the person edits it, so a
		// corrected answer stops being reported while they are still filling
		// the form in. One controller covers every input of the group, so the
		// first edit detaches the rest rather than leaving them to fire
		// against a message that is already gone.
		const listening = new AbortController();
		const clear = () => {
			removeDescribedBy( described, errorId );
			described.removeAttribute( 'aria-invalid' );
			element.remove();
			listening.abort();
		};

		inputs.forEach( ( input ) => {
			input.addEventListener( 'input', clear, {
				signal: listening.signal,
			} );
			input.addEventListener( 'change', clear, {
				signal: listening.signal,
			} );
		} );

		shown.push( fieldName );
	} );

	if ( orphaned.length ) {
		showFormError( form, orphaned.join( ' ' ) );
	} else if ( firstInvalid ) {
		// A fieldset is not focusable on its own, so send focus to the first
		// option inside it instead.
		const target = 'FIELDSET' === firstInvalid.tagName
			? firstInvalid.querySelector( 'input, select, textarea' )
			: firstInvalid;

		target?.focus();
	}

	return shown;
};

/**
 * Reports a rejected submission in the form.
 *
 * Prefers the per-field messages, and falls back to the summary when the
 * failure does not belong to a field.
 *
 * @since TBD
 *
 * @param {HTMLFormElement} form   The RSVP form.
 * @param {Object}          result The server's response.
 *
 * @return {void}
 */
export const reportSubmissionError = ( form, result ) => {
	clearFormErrors( form );

	const errors = result?.errors;

	if ( errors && Object.keys( errors ).length ) {
		showFieldErrors( form, errors );
		return;
	}

	// The fallback is rendered onto the form server-side, because this file
	// is bundled as an Interactivity module and a module cannot import
	// @wordpress/i18n.
	showFormError(
		form,
		result?.message || form?.dataset?.gatherpressErrorMessage,
	);
};
