/**
 * Toggle dependent settings field visibility based on the value of a
 * controlling field — the JS half of the Settings API's `show_if` feature.
 *
 * Server-side (`Settings::build_row_class()`) sets the initial visibility on
 * page load so there's no flash of unhidden content. This module wires up
 * the runtime side: every field with a `show_if` declaration emits a hidden
 * marker (`<input type="hidden" class="gatherpress-show-if-marker" data-show-if='{...}' />`)
 * inside its `<td>`. For each marker we walk up to the row's `<tr>`, locate
 * the controlling input(s) by name, attach `change` listeners, and re-toggle
 * the `gatherpress-settings-row--hidden` modifier whenever a value changes.
 *
 * Hidden rows still submit their values — visibility is CSS-only, never
 * `disabled`. The save path (`sanitize_page_settings`) additionally merges
 * submitted input with stored values, so even if a browser somehow omits a
 * hidden field, the previously saved value is preserved.
 *
 * @since 0.34.0
 */

const ROW_HIDDEN_CLASS = 'gatherpress--is-hidden';
const MARKER_SELECTOR = '.gatherpress-show-if-marker';
const NAME_TEMPLATE = ( key ) => `[name="gatherpress_settings[${ key }]"]`;

/**
 * Read the current submittable value from a form control.
 *
 * Checkboxes report their checked state as a boolean — everything else
 * (text, number, select, hidden) reports `value`. The string casts in
 * `matches()` smooth the boolean / number / string comparison.
 *
 * @param {HTMLInputElement|HTMLSelectElement} el The input or select element.
 *
 * @return {string|boolean} The control's current value.
 */
function readControlValue( el ) {
	if ( 'checkbox' === el.type ) {
		return el.checked;
	}

	return el.value;
}

/**
 * Test whether a current value satisfies an expected `show_if` value.
 *
 * Scalar expected → string equality. Array expected → membership (OR within
 * the same key). `{ not: value | [values] }` → negation (matches when the
 * current value is not among the given). All comparisons coerce to string so
 * checkbox booleans, select strings, and numeric values compare cleanly.
 * Mirrors `Settings::evaluate_show_if()` on the server.
 *
 * @param {string|boolean}                     current  The control's current value.
 * @param {string|number|boolean|Array|Object} expected The expected value(s) from the show_if declaration.
 *
 * @return {boolean} True when the current value satisfies the expectation.
 */
function matches( current, expected ) {
	if (
		expected &&
		'object' === typeof expected &&
		! Array.isArray( expected ) &&
		'not' in expected
	) {
		const excluded = ( Array.isArray( expected.not )
			? expected.not
			: [ expected.not ]
		).map( String );

		return ! excluded.includes( String( current ) );
	}

	if ( Array.isArray( expected ) ) {
		return expected.map( String ).includes( String( current ) );
	}

	return String( current ) === String( expected );
}

/**
 * Resolve the set of controlling inputs for a condition map.
 *
 * Returns `{ key, el }` pairs only for conditions whose controlling input
 * actually exists on the page — missing controllers are silently dropped
 * (the dependent stays hidden, which is the safe default for an unsatisfied
 * condition).
 *
 * The select and checkbox field templates render a hidden `<input>` fallback
 * BEFORE the real control with the same `name` (see select.php / checkbox.php
 * — required because `disabled` controls drop out of the POST, so the hidden
 * carries the inherited value). For repeated names PHP takes the LAST value,
 * so we mirror that here: when multiple elements share the name, pick the
 * last one. That naturally picks the live `<select>` / `<input type="checkbox">`
 * over the upstream hidden fallback.
 *
 * @param {Object} conditions Map of controlling option key → expected value(s).
 *
 * @return {Array<{key: string, el: HTMLElement}>} Resolved controller pairs.
 */
function resolveControllers( conditions ) {
	return Object.keys( conditions )
		.map( ( key ) => {
			const candidates = document.querySelectorAll( NAME_TEMPLATE( key ) );
			return {
				key,
				el: 0 < candidates.length ? candidates[ candidates.length - 1 ] : null,
			};
		} )
		.filter( ( entry ) => entry.el );
}

/**
 * Wire up the show/hide behavior for one marker.
 *
 * Extracted so each marker's setup is testable in isolation. The marker
 * itself stays in the DOM after wiring — it's a hidden input with no name,
 * so it has no effect on form submission.
 *
 * @param {HTMLElement} marker The `.gatherpress-show-if-marker` element.
 *
 * @return {void}
 */
function wireMarker( marker ) {
	const row = marker.closest( 'tr' );
	if ( ! row ) {
		return;
	}

	let conditions;
	try {
		conditions = JSON.parse( marker.dataset.showIf );
	} catch {
		// Malformed marker — leave the server-side initial visibility in place
		// rather than guess. This branch should be unreachable in practice;
		// the marker is produced by `wp_json_encode` server-side.
		return;
	}

	const controllers = resolveControllers( conditions );

	// If none of the controlling inputs exist on the page, the condition
	// can never become true at runtime. Leave the row in whatever state
	// the server-side initial render decided (hidden by default).
	if ( 0 === controllers.length ) {
		return;
	}

	const evaluate = () => {
		const allMatch = controllers.every( ( { key, el } ) =>
			matches( readControlValue( el ), conditions[ key ] )
		);

		row.classList.toggle( ROW_HIDDEN_CLASS, ! allMatch );
	};

	controllers.forEach( ( { el } ) => {
		el.addEventListener( 'change', evaluate );
	} );

	// Re-evaluate once on init so the JS-driven state agrees with the
	// server-side initial render. If a future change to `evaluate_show_if`
	// diverges from `matches()` here, this catches it on page load rather
	// than waiting for the first user interaction.
	evaluate();
}

/**
 * Initialize all `show_if` field dependencies on the current settings page.
 *
 * Idempotent — calling twice rewires the same markers but the duplicate
 * `change` listeners are deduped by re-using the same `evaluate` closure
 * reference per marker (a fresh closure is built on each call, so this is
 * NOT truly safe to call twice in the same load; callers should only call
 * once per page load).
 *
 * @return {void}
 */
export function initShowIfDependencies() {
	document
		.querySelectorAll( MARKER_SELECTOR )
		.forEach( ( marker ) => wireMarker( marker ) );
}

// Exported for unit testing — not part of the public surface.
export const __testables = {
	matches,
	readControlValue,
	resolveControllers,
	wireMarker,
};
