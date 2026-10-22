import { useSelect } from '@wordpress/data';
import { getSettings } from '@wordpress/date';
import { store as coreStore } from '@wordpress/core-data';

import { buildTaxQueryArgs, getPostsPerPage } from '../utils/calendar-utils';

const EMPTY_ARRAY = [];

/**
 * Hook to fetch posts and site settings for calendar rendering.
 *
 * @param {Object|null} query         Query Loop configuration.
 * @param {Object}      dateQuery     Date query parameters.
 * @param {boolean}     showScheduled Whether to include scheduled posts.
 *
 * @return {Object} Object containing:
 *   - {Array} posts - Array of post objects
 *   - {number} startOfWeek - Start of week setting
 */
export function useCalendarData( query, dateQuery, showScheduled = false ) {
	return useSelect(
		( select ) => {
			if ( ! query ) {
				return { posts: [], startOfWeek: 0 };
			}

			const { getEntityRecords, getTaxonomies } = select( coreStore );

			// Create a clean query object.
			const cleanQuery = { ...query };

			// Build REST API query arguments.
			const queryArgs = {
				per_page: getPostsPerPage(),
				_embed: 'wp:term',
			};

			// The REST API returns only published posts unless a status is given.
			if ( showScheduled ) {
				queryArgs.status = [ 'publish', 'future' ];
			}

			if ( dateQuery && dateQuery.startDate && dateQuery.endDate ) {
				queryArgs.gatherpress_calendar_query = true;
				queryArgs.start_date = dateQuery.startDate;
				queryArgs.end_date = dateQuery.endDate;
			}

			// Forward shadow source filter parameters to scope events by source.
			if ( cleanQuery.shadow_filter ) {
				queryArgs.shadow_filter = cleanQuery.shadow_filter;
			}
			if ( cleanQuery.gatherpress_shadow_source_post_id ) {
				queryArgs.gatherpress_shadow_source_post_id =
					cleanQuery.gatherpress_shadow_source_post_id;
			}
			if ( cleanQuery.gatherpress_shadow_source_post_type ) {
				queryArgs.gatherpress_shadow_source_post_type =
					cleanQuery.gatherpress_shadow_source_post_type;
			}

			// Add the taxonomy filters by their REST names, as core's Post Template does.
			if ( cleanQuery.taxQuery ) {
				Object.assign(
					queryArgs,
					buildTaxQueryArgs(
						cleanQuery.taxQuery,
						getTaxonomies( {
							type: query?.postType || 'post',
							per_page: -1,
							context: 'view',
						} ) ?? EMPTY_ARRAY,
					),
				);
			}

			// Add author query if present.
			if ( cleanQuery.author ) {
				queryArgs.author = cleanQuery.author;
			}

			// Add search query if present.
			if ( cleanQuery.search ) {
				queryArgs.search = cleanQuery.search;
			}

			if ( cleanQuery.orderBy ) {
				queryArgs.orderBy = cleanQuery.orderBy;
			}
			if ( cleanQuery.order ) {
				queryArgs.order = cleanQuery.order;
			}

			// Get site settings for start_of_week.
			const dateSettings = getSettings();
			const weekStartsOn = dateSettings?.l10n.startOfWeek || 0;

			const records = getEntityRecords(
				'postType',
				query?.postType || 'post',
				queryArgs,
			);

			return {
				posts: records ?? EMPTY_ARRAY,
				startOfWeek: weekStartsOn,
			};
		},
		[ query, dateQuery, showScheduled ],
	);
}
