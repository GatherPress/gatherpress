/**
 * WordPress dependencies.
 */
import { dispatch, select } from '@wordpress/data';

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
 * Checks if the current post type is a GatherPress event or venue.
 *
 * This function determines if the post type being edited in the WordPress block editor
 * is either 'gatherpress_event' or 'gatherpress_venue', which are custom post types
 * related to GatherPress. It is used to ensure that specific actions or functionality
 * are applied only to these post types.
 *
 * @since 1.0.0
 *
 * @return {boolean} True if the current post type is 'gatherpress_event' or 'gatherpress_venue', false otherwise.
 */
export function isGatherPressPostType() {
	const postType = select('core/editor')?.getCurrentPostType();

	return 'gatherpress_event' === postType || 'gatherpress_venue' === postType;
}
