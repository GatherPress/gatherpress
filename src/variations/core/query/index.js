/**
 * WordPress dependencies
 */
import { registerBlockVariation } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import './controls';
import './patterns';
import './start-blank';

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
	// Gate on `namespace` only. Including `query.postType` here would
	// drop the variation match the moment a user picks a custom event-
	// supporting post type (e.g. `production`), which in turn drops the
	// "Event Card with RSVP" starter pattern out of the Change design
	// picker since that pattern is scoped to this variation.
	isActive: [ 'namespace' ],
	attributes: {
		...QUERY_ATTRIBUTES,
		className: 'gatherpress-event-query',
	},
	// Disabling irrelevant or unsupported query controls
	// @see https://developer.wordpress.org/block-editor/how-to-guides/block-tutorial/extending-the-query-loop-block/#disabling-irrelevant-or-unsupported-query-controls
	allowedControls: [ 'inherit', 'postType', 'taxQuery', 'author', 'search' ],
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
	title: __( 'Event Query Loop', 'gatherpress' ),
	description: __( 'Create event queries', 'gatherpress' ),
	scope: [ 'inserter', 'transform' ],
	/*
	 * Intentionally no `innerBlocks` here.
	 *
	 * Omitting the field lets core/query show its placeholder modal on insert
	 * with "Choose" and "Start blank" — the Choose flow surfaces the starter
	 * patterns registered in `./patterns/index.js`, which are scoped via the
	 * `gatherpress-event-query` pattern category.
	 *
	 * @see https://developer.wordpress.org/block-editor/how-to-guides/block-tutorial/extending-the-query-loop-block/#customize-your-variation-layout
	 */

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
