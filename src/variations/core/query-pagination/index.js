/**
 * WordPress dependencies
 */
import { registerBlockVariation } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';

import { QUERY_PAGINATION_PREVIOUS_VARIATION } from '../query-pagination-previous';
import { QUERY_PAGINATION_NUMBERS_VARIATION } from '../query-pagination-numbers';
import { QUERY_PAGINATION_NEXT_VARIATION } from '../query-pagination-next';

/**
 * Update UI for pagination blocks to speak 'events', not 'posts'.
 */

const CORE_BLOCK = 'core/query-pagination';
const CLASS_NAME = 'gatherpress-query-pagination';

const QUERY_PAGINATION_TEMPLATE = [
	QUERY_PAGINATION_PREVIOUS_VARIATION,
	QUERY_PAGINATION_NUMBERS_VARIATION,
	QUERY_PAGINATION_NEXT_VARIATION,
];

export const QUERY_PAGINATION_VARIATION = [
	CORE_BLOCK,
	{
		className: CLASS_NAME,
	},
	QUERY_PAGINATION_TEMPLATE,
];

/**
 * Event Pagination
 * A user can pick the block directly from the inserter, the left sidebar or choose to transform it from a regular "core/query-pagination" block.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/block-api/block-variations/
 */
registerBlockVariation( CORE_BLOCK, {
	category: 'gatherpress',
	keywords: [ __( 'Page numbers', 'gatherpress' ), __( 'Pagination', 'gatherpress' ) ],
	isActive: [ 'className' ],
	attributes: {
		className: CLASS_NAME,
	},
	scope: [ 'block', 'inserter', 'transform' ],
	name: CLASS_NAME,
	title: __( 'Event Pagination', 'gatherpress' ),
	description: __(
		'Displays a paginated navigation to next/previous set of events, when applicable.',
		'gatherpress',
	),

	innerBlocks: [ ...QUERY_PAGINATION_TEMPLATE ],
} );
