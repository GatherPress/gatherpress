/**
 * WordPress dependencies
 */
import { registerBlockVariation } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';

/**
 * Update UI for pagination blocks to speak 'events', not 'posts'.
 */
const CORE_BLOCK = 'core/query-no-results';
const CLASS_NAME = 'gatherpress-query-no-results';

const QUERY_NO_RESULTS_TEMPLATE = [
	[
		'core/paragraph',
		{
			placeholder: __(
				'Add text or blocks that will display when a query returns no events.',
				'gatherpress',
			),
		},
	],
];

export const QUERY_NO_RESULTS_VARIATION = [
	CORE_BLOCK,
	{
		className: CLASS_NAME,
	},
	QUERY_NO_RESULTS_TEMPLATE,
];

/**
 * Event Pagination
 * A user can pick the block directly from the inserter, the left sidebar or choose to transform it from a regular "core/query-pagination" block.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/block-api/block-variations/
 */
registerBlockVariation( CORE_BLOCK, {
	category: 'gatherpress',
	isActive: [ 'className' ],
	attributes: {
		className: CLASS_NAME,
	},
	scope: [ 'block', 'inserter', 'transform' ],
	name: CLASS_NAME,
	title: __( 'No Event Results', 'gatherpress' ),
	description: __(
		'Contains the block elements used to render content when no event query results are found.',
		'gatherpress',
	),

	innerBlocks: [ ...QUERY_NO_RESULTS_TEMPLATE ],
} );
