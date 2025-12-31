/**
 * Determines if a block should be hidden based on visibility rules and form state.
 *
 * This function implements the visibility logic for RSVP Form blocks, supporting
 * conditional display based on form submission success and event past status.
 *
 * Visibility rules:
 * - onSuccess: 'show' | 'hide' | '' (empty = always visible)
 *   - 'show' = show on success, hide before success
 *   - 'hide' = hide on success, show before success
 * - whenPast: 'show' | 'hide' | '' (empty = always visible)
 *   - 'show' = show when past, hide when not past
 *   - 'hide' = hide when past, show when not past
 *
 * Precedence:
 * - When event is past: whenPast takes precedence
 * - When event is not past: onSuccess is used (if set)
 * - Blocks with ONLY whenPast (no onSuccess) are hidden when not past if set to 'show'
 *
 * @param {Object} visibility           The visibility configuration object.
 * @param {string} visibility.onSuccess Show/hide on form success ('show'|'hide'|'').
 * @param {string} visibility.whenPast  Show/hide when event has passed ('show'|'hide'|'').
 * @param {string} formState            Current form state ('default'|'success'|'past').
 * @return {boolean} True if block should be hidden, false if it should be shown.
 */
export const shouldHideBlock = ( visibility, formState ) => {
	const { onSuccess = '', whenPast = '' } = visibility;

	const isPast = 'past' === formState;
	const isSuccess = 'success' === formState;

	// When event is past, check whenPast (takes precedence when past).
	if ( isPast && whenPast ) {
		return 'show' !== whenPast;
	}

	// When not past but block has ONLY whenPast setting (no onSuccess).
	// This handles blocks that should only appear after event has passed.
	if ( ! isPast && whenPast && ! onSuccess ) {
		return 'show' === whenPast; // Hide if set to show (because not past yet).
	}

	// Check onSuccess.
	if ( onSuccess ) {
		if ( isSuccess ) {
			return 'show' !== onSuccess;
		}
		// Not success: hide if set to show on success.
		return 'show' === onSuccess;
	}

	return false; // Default: visible.
};
