/**
 * WordPress dependencies
 */
import { registerBlockVariation } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import './controls';
import { QUERY_NO_RESULTS_VARIATION } from '../query-no-results';
import { QUERY_PAGINATION_VARIATION } from '../query-pagination';

export const NAME = 'gatherpress-event-query';

const QUERY_ATTRIBUTES = {
	namespace: NAME,
	query: {
		perPage: 5,
		pages: 0,
		offset: 0,
		postType: 'gatherpress_event',
		gatherpress_event_query: 'upcoming',
		include_unfinished: 1,
		order: 'asc',
		orderBy: 'datetime',
		inherit: false,
	},
};

const VARIATION_ATTRIBUTES = {
	category: 'gatherpress',
	keywords: [ __( 'Events', 'gatherpress' ), __( 'Dates', 'gatherpress' ) ],
	isActive: [ 'namespace', 'query.postType' ],
	attributes: {
		...QUERY_ATTRIBUTES,
	},
	// Disabling irrelevant or unsupported query controls
	// @see https://developer.wordpress.org/block-editor/how-to-guides/block-tutorial/extending-the-query-loop-block/#disabling-irrelevant-or-unsupported-query-controls
	allowedControls: [ 'inherit', 'taxQuery', 'author', 'search' ],
	scope: [ 'block' ],
};

/**
 * Docs about the Query block.
 *
 * General information on how to modify the query loop block, that's worth reading and learning:
 *
 * @see https://developer.wordpress.org/block-editor/how-to-guides/block-tutorial/extending-the-query-loop-block/#extending-the-query
 * @see https://wpfieldwork.com/modify-query-loop-block-to-filter-by-custom-field/
 * @see https://developer.wordpress.org/block-editor/reference-guides/block-api/block-variations/
 * @see https://jeffreycarandang.com/restrict-wordpress-gutenberg-block-settings-based-on-post-type-user-roles-or-block-context/
 */

/**
 * This is the main query-block variation to list events exclusively.
 * A user can pick the block directly from the inserter or the left sidebar.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/block-api/block-variations/
 */
registerBlockVariation( 'core/query', {
	...VARIATION_ATTRIBUTES,
	name: NAME,
	title: __( 'Event Query', 'gatherpress' ),
	description: __( 'Create event queries', 'gatherpress' ),
	scope: [ 'inserter', 'transform' ],
	/*
	 * Having innerBlocks in THIS (visible) variation, essentially
	 * skips the setup phase of the Query Loop block with suggested starter patterns
	 * and the block is inserted with these inner blocks as its starting content.
	 *
	 * This is not what GatherPress wanted, so it is disabled.
	 *
	 * @see https://developer.wordpress.org/block-editor/how-to-guides/block-tutorial/extending-the-query-loop-block/#customize-your-variation-layout
	 *
	 * As long as GatherPress does not have any valid Starter Patterns, this 'innerBlocks' section is temporarily re-enabled
	 * to prevent the default 'core/query' block starter patterns to appear.
	 * As soon as #1124 is done, this part should be disabled again.
	 *
	 * @todo Add 'Start blank' patterns for the gatherpress query loop variation. https://github.com/GatherPress/gatherpress/issues/1124
	 */
	innerBlocks: [
		[
			'core/post-template',
			{
				metadata: {
					name: __( 'Events Template', 'gatherpress' ),
				},
			},
			[
				[ 'core/post-title' ],
				[ 'gatherpress/event-date' ],
				[ 'core/post-excerpt' ],
				[ 'core/post-terms', { term: '_gatherpress_venue' } ],
			],
		],
		QUERY_PAGINATION_VARIATION,
		QUERY_NO_RESULTS_VARIATION,
	],

	example: {
		attributes: {
			...QUERY_ATTRIBUTES,
		},
		innerBlocks: [
			{
				name: 'core/post-template',
				attributes: {},
				innerBlocks: [
					{
						name: 'core/post-title',
					},
				],
			},
		],
	},
} );
