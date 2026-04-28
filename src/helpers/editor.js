/**
 * WordPress dependencies.
 */
import { dispatch, select } from '@wordpress/data';
import { store as coreStore } from '@wordpress/core-data';

/**
 * Internal dependencies.
 */
import { isPostTypeSupporting } from './event';

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
 * Retrieves the current contextual post ID.
 *
 * If a `postId` argument is provided, that value is returned.
 * If not, falls back to the current post ID from the block editor's `core/editor` store.
 *
 * @since 1.0.0
 *
 * @param {number|null} postId Optional. A specific post ID to return instead of detecting the current one. Defaults to null.
 * @return {number|null}                 The post ID, or null if it cannot be determined.
 */
export function getCurrentContextualPostId( postId = null ) {
	return postId || select( 'core/editor' ).getCurrentPostId();
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
 * Determines if a block should display at full opacity based on its context.
 *
 * Use this helper to consistently apply dimming logic across GatherPress blocks.
 * A block is fully visible (not dimmed) when:
 * - It's in an FSE template (always visible for preview)
 * - It's in a Query Loop with valid post type support AND has data
 * - It's editing a post directly AND has data
 *
 * The supports check is intentionally a parameter (`hasSupport`) rather than
 * computed inside the helper. Reading post-type supports via `select(...)` is
 * non-reactive — the post-type definition often isn't cached on first render,
 * so the gate would resolve to `false` and the block would never re-render
 * once supports load. Callers should compute `hasSupport` reactively via the
 * `usePostTypeSupports` hook so the block re-renders the moment supports become known.
 *
 * Backwards compatible: if `hasSupport` is omitted, falls back to a non-reactive
 * `isPostTypeSupporting( support, postType )` check.
 *
 * @since 1.0.0
 *
 * @param {Object}  options                         Options for determining visibility.
 * @param {boolean} options.isDescendentOfQueryLoop Whether the block is inside a Query Loop.
 * @param {boolean} [options.hasSupport]            Whether the resolved post type has the required support.
 *                                                  Pass the result of `usePostTypeSupports` for reactivity.
 * @param {string}  [options.postType]              Legacy fallback when `hasSupport` is omitted.
 * @param {string}  [options.support]               Legacy fallback when `hasSupport` is omitted.
 * @param {boolean} [options.hasData=false]         Whether the block has its specific data.
 * @return {boolean} True if the block should be fully visible, false if it should be dimmed.
 */
export function hasValidBlockContext( {
	isDescendentOfQueryLoop,
	hasSupport,
	postType,
	support,
	hasData = false,
} ) {
	// Always visible in FSE templates for design/preview purposes.
	if ( isInFSETemplate() ) {
		return true;
	}

	// In Query Loop, require both valid post type support AND data.
	if ( isDescendentOfQueryLoop ) {
		const resolvedSupport =
			undefined !== hasSupport
				? hasSupport
				: isPostTypeSupporting( support, postType );

		return resolvedSupport && hasData;
	}

	// When editing directly, just check if we have data.
	return hasData;
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
