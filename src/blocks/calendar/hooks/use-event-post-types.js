/**
 * Hook to discover registered post types supporting gatherpress-event-date.
 *
 * @package
 * @since 0.37.0
 */

import { useSelect } from '@wordpress/data';

import { selectEventPostTypes } from '../utils/source-utils';

/**
 * Returns an array of post type objects that declare gatherpress-event-date support.
 *
 * @return {Array<Object>} Event-supporting post type objects.
 */
export function useEventPostTypes() {
	return useSelect( ( select ) => selectEventPostTypes( select ), [] );
}
