/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { NAME } from './name';

export const QUERY_ATTRIBUTES = {
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

export const VARIATION_ATTRIBUTES = {
	category: 'gatherpress',
	keywords: [ __( 'Events', 'gatherpress' ), __( 'Dates', 'gatherpress' ) ],
	// Gate on `namespace` only. Including `query.postType` here would
	// drop the variation match the moment a user picks a custom event-
	// supporting post type, which in turn drops the "Event Card with
	// RSVP" starter pattern out of the Change design picker since that
	// pattern is scoped to this variation.
	isActive: [ 'namespace' ],
	attributes: {
		...QUERY_ATTRIBUTES,
		className: 'gatherpress-event-query',
	},
	// Disabling irrelevant or unsupported query controls
	// @see https://developer.wordpress.org/block-editor/how-to-guides/block-tutorial/extending-the-query-loop-block/#disabling-irrelevant-or-unsupported-query-controls
	allowedControls: [ 'inherit', 'postType', 'taxQuery', 'author', 'search' ],
};
