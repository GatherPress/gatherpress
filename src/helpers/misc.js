import { dispatch } from '@wordpress/data';

// @todo hack approach to enabling Save buttons after update
// https://github.com/WordPress/gutenberg/issues/13774
export function enableSave() {
	dispatch('core/editor').editPost({ meta: { _non_existing_meta: true } });
}
