/**
 * WordPress dependencies.
 */
import { dispatch, select } from '@wordpress/data';
import { store as coreStore } from '@wordpress/core-data';

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
	dispatch( 'core/editor' )?.editPost( { meta: { _non_existing_meta: true } } );
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
	const postType = select( 'core/editor' )?.getCurrentPostType();

	return 'gatherpress_event' === postType || 'gatherpress_venue' === postType;
}

/**
 * Get the appropriate document context for the block editor.
 *
 * In FSE (Full Site Editing) contexts, blocks are rendered within an iframe
 * with the name "editor-canvas". This function detects that iframe and returns
 * its document, otherwise falls back to the main document for regular editors.
 *
 * @return {Document} The document object containing the block editor content.
 */
export function getEditorDocument() {
	const iframe = document.querySelector(
		'iframe[name="editor-canvas"]',
	);

	if ( iframe?.contentDocument ) {
		return iframe.contentDocument;
	}

	return document;
}

/**
 * Checks if the current editor context is a Full Site Editor template.
 *
 * This function determines if the user is editing a template or template part
 * in the Full Site Editor, as opposed to editing a regular post or page.
 *
 * @since 1.0.0
 *
 * @return {boolean} True if editing an FSE template or template part, false otherwise.
 */
export function isInFSETemplate() {
	const postType = select( 'core/editor' )?.getCurrentPostType();

	return [ 'wp_template', 'wp_template_part' ].includes( postType );
}

/**
 * Gets the site's configured start of the week.
 *
 * This function retrieves the start of the week setting from the site's
 * configuration, which indicates which day is considered the first day of the week.
 *
 * @since 1.0.0
 *
 * @return {number} The start of the week (0 for Sunday, 1 for Monday, etc.).
 */
export function getStartOfWeek() {
	const { getSite } = select( coreStore );
	const site = getSite();
	return site?.start_of_week || 0;
}
