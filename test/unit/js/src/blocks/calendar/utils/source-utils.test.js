/**
 * External dependencies.
 */
import { describe, expect, it } from '@jest/globals';

/**
 * Internal dependencies.
 */
import { resolveSourceEventDates } from '@src/blocks/calendar/utils/source-utils';

/**
 * Build a select function for the stores that resolveSourceEventDates reads.
 *
 * @param {Object} options
 * @param {*}      options.editorPostId   ID of the post the editor edits.
 * @param {string} options.editorPostType Post type of the post the editor edits.
 * @param {Object} options.records        Saved posts by ID.
 *
 * @return {Function} Select function.
 */
const createSelect =
	( { editorPostId, editorPostType, records = {} } ) =>
		( storeName ) => {
			if ( 'core/editor' === storeName ) {
				return {
					getCurrentPostId: () => editorPostId,
					getCurrentPostType: () => editorPostType,
					getEditedPostAttribute: ( key ) =>
						'title' === key ? 'Edited Event' : {},
				};
			}
			if ( 'gatherpress/datetime' === storeName ) {
				// The datetime store always has dates, also in a template.
				return {
					getDateTimeStart: () => '2026-10-22 18:00:00',
					getDateTimeEnd: () => '2026-10-22 20:00:00',
				};
			}
			return {
				getPostType: ( type ) =>
					'gatherpress_event' === type
						? { supports: { 'gatherpress-event-date': true } }
						: null,
				getEntityRecord: ( kind, type, id ) => records[ id ] ?? null,
			};
		};

describe( 'resolveSourceEventDates', () => {
	it( 'gives no post for a site editor template in Current mode', () => {
		const select = createSelect( {
			editorPostId: 'twentytwentyfive//single-gatherpress_event',
			editorPostType: 'wp_template',
		} );

		expect(
			resolveSourceEventDates( select, { dateRangeSource: 'context' } ),
		).toMatchObject( { hasPost: false, effectivePostId: 0 } );
	} );

	it( 'reads the live dates only for the event that the editor edits', () => {
		const select = createSelect( {
			editorPostId: 7,
			editorPostType: 'gatherpress_event',
			records: {
				12: {
					meta: {
						gatherpress_datetime_start: '2026-11-03 10:00:00',
						gatherpress_datetime_end: '2026-11-04 12:00:00',
					},
					title: { rendered: 'Loop Event' },
				},
			},
		} );

		expect(
			resolveSourceEventDates( select, { dateRangeSource: 'context' } ),
		).toMatchObject( {
			startDate: '2026-10-22',
			postTitle: 'Edited Event',
			effectivePostId: 7,
		} );
		expect(
			resolveSourceEventDates( select, {
				dateRangeSource: 'context',
				contextPostId: 12,
				contextPostType: 'gatherpress_event',
			} ),
		).toMatchObject( {
			startDate: '2026-11-03',
			endDate: '2026-11-04',
			postTitle: 'Loop Event',
			effectivePostId: 12,
		} );
	} );
} );
