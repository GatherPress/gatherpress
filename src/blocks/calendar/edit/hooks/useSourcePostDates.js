/**
 * Hook to resolve start and end dates from a target event post.
 *
 * @package
 * @since 0.8.0
 */

import { useSelect } from '@wordpress/data';
import { useEventPostTypes } from './useEventPostTypes';
import {
	getDefaultEventPostType,
	resolveSourceEventDates,
} from '../utils/source-utils';

/**
 * Hook to resolve start and end dates from a target event post.
 *
 * @param {Object} props                 Parameters object.
 * @param {string} props.dateRangeSource Source mode ('default', 'context', 'selected').
 * @param {number} props.postId          Explicit post ID.
 * @param {string} props.sourcePostType  Explicit post type.
 * @param {number} props.contextPostId   Post ID from block context.
 * @param {string} props.contextPostType Post type from block context.
 *
 * @return {Object} Resolved post dates object { startDate, endDate, postTitle, hasPost, effectivePostId }.
 */
export function useSourcePostDates( props ) {
	const eventPostTypes = useEventPostTypes();
	const defaultPostType = getDefaultEventPostType( eventPostTypes );

	const {
		dateRangeSource = 'default',
		postId = 0,
		sourcePostType = '',
		contextPostId = 0,
		contextPostType = '',
	} = props;

	return useSelect(
		( select ) => {
			return resolveSourceEventDates( select, {
				dateRangeSource,
				postId,
				sourcePostType,
				contextPostId,
				contextPostType,
				defaultPostType,
			} );
		},
		[
			dateRangeSource,
			postId,
			sourcePostType,
			contextPostId,
			contextPostType,
			defaultPostType,
		],
	);
}
