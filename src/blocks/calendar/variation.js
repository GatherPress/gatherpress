/**
 * WordPress dependencies
 */
import { registerBlockVariation } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { QUERY_VARIATION_INNER_BLOCKS } from './constants';

registerBlockVariation( 'core/query', {
	name: 'gatherpress-calendar',
	title: __( 'Event Calendar', 'gatherpress' ),
	description: __(
		'Show GatherPress events in a monthly calendar format.',
		'gatherpress',
	),
	icon: 'calendar-alt',
	category: 'gatherpress',
	keywords: [
		__( 'calendar', 'gatherpress' ),
		__( 'event', 'gatherpress' ),
		__( 'query', 'gatherpress' ),
	],
	attributes: {
		// namespace: 'gatherpress-event-query',
		enhancedPagination: true,
		// className: 'gatherpress-event-query',
		query: {
			perPage: 5,
			pages: 0,
			offset: 0,
			postType: 'gatherpress_event',
			order: 'asc',
			orderBy: 'datetime',
			inherit: false,
			excludeCurrent: null,
			parents: [],
			sticky: '',
			format: [],
			gatherpress_event_query: 'upcoming',
			include_unfinished: 1,
		},
	},
	innerBlocks: QUERY_VARIATION_INNER_BLOCKS,
	scope: [ 'inserter' ],
} );
