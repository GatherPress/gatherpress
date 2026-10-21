/**
 * Hook to discover registered post types supporting gatherpress-event-date.
 *
 * @package
 * @since TBD
 */

import { useSelect } from '@wordpress/data';
import { store as coreStore } from '@wordpress/core-data';

const EMPTY_TYPES = [];

/**
 * Returns an array of post type objects that declare gatherpress-event-date support.
 *
 * Queries with context: 'edit' so WordPress REST API includes the supports dictionary.
 *
 * @return {Array<Object>} Event-supporting post type objects.
 */
export function useEventPostTypes() {
	return useSelect( ( select ) => {
		const { getPostTypes } = select( coreStore );
		const types = getPostTypes( { per_page: -1, context: 'edit' } );

		if ( ! Array.isArray( types ) ) {
			return EMPTY_TYPES;
		}

		return types.filter(
			( postType ) => !! postType.supports?.[ 'gatherpress-event-date' ],
		);
	}, [] );
}
