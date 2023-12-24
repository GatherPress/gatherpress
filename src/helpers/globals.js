import { dispatch } from '@wordpress/data';

// @todo hack approach to enabling Save buttons after update
// https://github.com/WordPress/gutenberg/issues/13774
export function enableSave() {
	dispatch('core/editor')?.editPost({ meta: { _non_existing_meta: true } });
}

/**
 * Helper to safely retrieve from the GatherPress global variable.
 *
 * @param {string} args
 * @return {undefined|*} Returns value of arguments provided.
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
 * Helper to safely set to the GatherPress global variable.
 *
 * @param {string} args
 * @param {any}    value
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
