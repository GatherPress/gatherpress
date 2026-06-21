/**
 * WordPress dependencies
 */
import { dispatch, select, useSelect } from '@wordpress/data';
import { store as coreStore } from '@wordpress/core-data';

/**
 * Internal dependencies
 */
import { isPostTypeSupporting } from './event';

/**
 * Module-level cache of resolved post-type labels, keyed by
 * `<postType>::<labelKey>`. Post-type labels are set during
 * `register_post_type()` and don't change at runtime, so once we've
 * resolved a non-fallback label we serve it from the cache for the rest
 * of the editor session.
 *
 * This doesn't change how often `usePostTypeLabel`'s selector runs —
 * `useSelect` re-invokes the selector on every dispatch to the stores it
 * subscribes to (`core/editor`, `core`), which is many times during
 * editor init (REST resolutions for post types, taxonomies, etc.). With
 * the cache the selector body is a Map lookup instead of two store
 * reads. See issue #1646.
 *
 * Exported only for tests; production callers should go through
 * `getPostTypeLabel` / `usePostTypeLabel`.
 *
 * @ignore
 */
export const __postTypeLabelCache = new Map();

/**
 * Look up `<postType>::<labelKey>` in the module cache without performing
 * any store reads. Callers must have already resolved a truthy `postType`
 * (both `getPostTypeLabel` and `usePostTypeLabel` short-circuit to the
 * fallback before this is invoked when no post type is available).
 *
 * @param {string} postType Post type slug.
 * @param {string} key      Label key.
 *
 * @return {string|undefined} The cached label, or `undefined` if absent.
 */
function getCachedLabel( postType, key ) {
	return __postTypeLabelCache.get( `${ postType }::${ key }` );
}

/**
 * Cache a non-empty resolved label.
 *
 * No-op for empty / falsy labels so the cache only ever serves real values
 * — a missed lookup still falls through to the live store read on the next
 * call so we pick up the label as soon as `core` finishes resolving it.
 *
 * @param {string} postType Post type slug.
 * @param {string} key      Label key.
 * @param {string} label    Resolved label string.
 *
 * @return {void}
 */
function rememberLabel( postType, key, label ) {
	if ( label ) {
		__postTypeLabelCache.set( `${ postType }::${ key }`, label );
	}
}

/**
 * Resolve a single label from a post type's registered labels.
 *
 * JS counterpart to `Utility::post_type_label()` — wraps `getPostType()` so
 * call sites don't have to defend against unregistered post types or missing
 * label keys. Lets UI strings reflect whatever a site builder filtered the
 * labels to, and lets extenders' event-supporting post types surface their
 * own labels instead of GatherPress's defaults (#1612).
 *
 * Reads non-reactively via `select( 'core' ).getPostType()`. For React
 * components whose render output depends on the resolved label (variation
 * titles, panel headings, etc.) prefer the reactive `usePostTypeLabel`
 * hook below — without subscription the label resolves to the fallback at
 * first render and never updates.
 *
 * @since 0.33.0
 *
 * @param {string}      key      Label key to read (e.g. `singular_name`, `name`, `add_new_item`).
 * @param {string|null} postType Optional post type slug. Falls back to the editor post type.
 * @param {string}      fallback Optional fallback returned when the label can't be resolved.
 *
 * @return {string} The resolved label, or the fallback when unresolvable.
 */
export function getPostTypeLabel( key, postType = null, fallback = '' ) {
	const typeToCheck =
		postType ?? select( 'core/editor' )?.getCurrentPostType();

	if ( ! typeToCheck ) {
		return fallback;
	}

	const cached = getCachedLabel( typeToCheck, key );
	if ( cached !== undefined ) {
		return cached;
	}

	const label = select( 'core' ).getPostType( typeToCheck )?.labels?.[ key ];
	rememberLabel( typeToCheck, key, label );

	return label || fallback;
}

/**
 * Reactive variant of `getPostTypeLabel` for use in React components.
 *
 * `getPostTypeLabel` reads `getPostType()` non-reactively, so when the
 * post-type definition isn't yet cached at render time the label resolves
 * to the fallback and the component never re-renders once it loads. This
 * hook subscribes via `useSelect` so the component re-renders the moment
 * the labels become known — which is the difference between a variation
 * permanently titled "Event" and one that picks up the registered label.
 *
 * The selector body re-runs on every dispatch to the subscribed stores —
 * that's how `useSelect` works, and `core` dispatches many times during
 * editor init (REST resolutions for post types, taxonomies, settings).
 * Once a label has resolved, subsequent selector invocations short-circuit
 * to the module-level `__postTypeLabelCache` so each re-run is an O(1)
 * Map lookup rather than two store reads (issue #1646). React still only
 * re-renders the component when the resolved string actually changes.
 *
 * @since 0.33.0
 *
 * @param {string}      key      Label key to read.
 * @param {string|null} postType Optional post type slug. Falls back to the editor post type.
 * @param {string}      fallback Optional fallback returned when the label can't be resolved.
 *
 * @return {string} The resolved label, or the fallback when unresolvable.
 */
export function usePostTypeLabel( key, postType = null, fallback = '' ) {
	return useSelect(
		( wpSelect ) => {
			const typeToCheck =
				postType ?? wpSelect( 'core/editor' )?.getCurrentPostType();

			if ( ! typeToCheck ) {
				return fallback;
			}

			const cached = getCachedLabel( typeToCheck, key );
			if ( cached !== undefined ) {
				return cached;
			}

			const label = wpSelect( 'core' ).getPostType( typeToCheck )
				?.labels?.[ key ];
			rememberLabel( typeToCheck, key, label );

			return label || fallback;
		},
		[ key, postType, fallback ]
	);
}

/**
 * Enable the Save buttons after making an update.
 *
 * This function uses a hacky approach to trigger a change in the post's meta, which prompts
 * Gutenberg to recognize that changes have been made and enables the Save buttons.
 * It dispatches an editPost action with a non-existing meta key.
 *
 * @since 0.33.0
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
 * @since 0.33.0
 *
 * @param {number|null} postId Optional. A specific post ID to return instead of detecting the current one. Defaults to null.
 *
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
 * @since 0.33.0
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
 * @since 0.33.0
 *
 * @param {Object}  options                         Options for determining visibility.
 * @param {boolean} options.isDescendentOfQueryLoop Whether the block is inside a Query Loop.
 * @param {boolean} [options.hasSupport]            Whether the resolved post type has the required support.
 *                                                  Pass the result of `usePostTypeSupports` for reactivity.
 * @param {string}  [options.postType]              Deprecated. Legacy fallback when `hasSupport` is omitted —
 *                                                  prefer `hasSupport` so the supports gate stays reactive.
 * @param {string}  [options.support]               Deprecated. Legacy fallback when `hasSupport` is omitted —
 *                                                  prefer `hasSupport` so the supports gate stays reactive.
 * @param {boolean} [options.hasData=false]         Whether the block has its specific data.
 *
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
			undefined === hasSupport
				? isPostTypeSupporting( support, postType )
				: hasSupport;

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
 * @since 0.33.0
 *
 * @return {number} The start of the week (0 for Sunday, 1 for Monday, etc.).
 */
export function getStartOfWeek() {
	const { getSite } = select( coreStore );
	const site = getSite();
	return site?.start_of_week || 0;
}
