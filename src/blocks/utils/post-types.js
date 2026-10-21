import { select, useSelect } from '@wordpress/data';

/**
 * Opacity value for disabled form fields and elements.
 *
 * This constant defines the opacity level applied to form fields and UI elements
 * when they are disabled due to event settings (e.g., when guest limits are 0
 * or anonymous RSVP is disabled).
 *
 * @since TBD
 * @type {number}
 */
export const DISABLED_FIELD_OPACITY = 0.3;

/**
 * Checks if a post type has a given GatherPress post type support.
 *
 * If a postType argument is provided, checks against that value.
 * Otherwise, queries the current post type using the `select` function from the `core/editor` package.
 * Uses the WordPress data store to check post type supports.
 *
 * @since TBD
 *
 * @param {string}      support  The post type support to check (e.g. 'gatherpress-event-date').
 * @param {string|null} postType Optional post type to check. If not provided, checks current editor post type.
 *
 * @return {boolean} True if the post type has the given support, false otherwise.
 */
export function isPostTypeSupporting( support, postType = null ) {
	const typeToCheck =
		postType ?? select( 'core/editor' )?.getCurrentPostType();

	if ( ! typeToCheck ) {
		return false;
	}

	const postTypeObject = select( 'core' ).getPostType( typeToCheck );

	return !! postTypeObject?.supports?.[ support ];
}

/**
 * Reactive variant of `isPostTypeSupporting` for use in React components.
 *
 * `isPostTypeSupporting` reads `getPostType()` non-reactively, so when the
 * post-type definition isn't yet cached at render time the support gate
 * resolves to `false` and the component never re-renders once it loads.
 * This hook subscribes via `useSelect` so the component re-renders the moment
 * the supports become known — which is the difference between a permanently
 * dimmed block in a Query Loop and one that lights up correctly.
 *
 * @since TBD
 *
 * @param {string}      support  The post type support to check.
 * @param {string|null} postType Optional post type to check. Falls back to the editor post type.
 *
 * @return {boolean} True if the resolved post type has the given support, false otherwise.
 */
export function usePostTypeSupports( support, postType = null ) {
	return useSelect(
		( wpSelect ) => {
			const typeToCheck =
				postType ?? wpSelect( 'core/editor' )?.getCurrentPostType();

			if ( ! typeToCheck ) {
				return false;
			}

			return !! wpSelect( 'core' ).getPostType( typeToCheck )?.supports?.[
				support
			];
		},
		[ support, postType ],
	);
}

/**
 * Checks if a post type supports event_date in the GatherPress application.
 *
 * @since TBD
 *
 * @param {string|null} postType Optional post type to check. If not provided, checks current editor post type.
 *
 * @return {boolean} True if the post type supports event_date, false otherwise.
 */
export function isEventPostType( postType = null ) {
	return isPostTypeSupporting( 'gatherpress-event-date', postType );
}
