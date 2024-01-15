import { dispatch } from '@wordpress/data';

/**
 * Enable the Save buttons after making an update.
 *
 * This function uses a hacky approach to trigger a change in the post's meta, which prompts
 * Gutenberg to recognize that changes have been made and enables the Save buttons.
 * It dispatches an editPost action with a non-existing meta key.
 *
 * @since 1.0.0
 *
 * @todo This is a hacky approach and relies on the behavior described in
 *       https://github.com/WordPress/gutenberg/issues/13774.
 *       Monitor the issue for any updates or changes in the Gutenberg behavior.
 */
export function enableSave() {
	dispatch('core/editor')?.editPost({ meta: { _non_existing_meta: true } });
}

/**
 * Get a value from the global GatherPress object based on the provided dot-separated path.
 *
 * This function is designed to retrieve values from the global GatherPress object.
 * It takes a dot-separated path as an argument and traverses the object to return the specified value.
 * If the object or any level along the path is undefined, it returns undefined.
 *
 * @since 1.0.0
 *
 * @param {string} args - Dot-separated path to the desired property in the GatherPress global object.
 * @return {*} The value at the specified path in the GatherPress global object or undefined if not found.
 */
export function getFromGlobal(args) {
	// eslint-disable-next-line no-undef
	if ('object' !== typeof GatherPress) {
		return undefined;
	}

	return args.split('.').reduce(
		// eslint-disable-next-line no-undef
		(GatherPress, level) => GatherPress && GatherPress[level],
		// eslint-disable-next-line no-undef
		GatherPress
	);
}

/**
 * Set a value to a global object based on the provided path.
 *
 * This function allows setting values within a nested global object using a dot-separated path.
 * If the global object (GatherPress) does not exist, it will be initialized.
 *
 * @since 1.0.0
 *
 * @param {string} args  - Dot-separated path to the property.
 * @param {*}      value - The value to set.
 *
 * @return {void}
 */
export function setToGlobal(args, value) {
	// eslint-disable-next-line no-undef
	if ('object' !== typeof GatherPress) {
		return;
	}
	const properties = args.split('.');
	const last = properties.pop();

	// eslint-disable-next-line no-undef
	properties.reduce((all, item) => (all[item] ??= {}), GatherPress)[last] =
		value;
}
