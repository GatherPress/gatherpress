/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';
/**
 * Internal dependencies
 */
// import { GPV_CLASS_NAME } from '../helpers/namespace';

// export const GPV_BLOCK = {
// 	name: 'core/group',
// 	attributes: {
// 		className: GPV_CLASS_NAME,
// 		// is necessary to make isActive work !!
// 		// @see https://github.com/WordPress/gutenberg/issues/41303#issuecomment-1526193087
// 		layout: { type: 'flex', orientation: 'nonsense' }, // works
// 	},
// 	innerBlocks: [
// 		[
// 			'core/pattern',
// 			{
// 				slug: 'gatherpress/venue-details',
// 			},
// 		],
// 	],
// }

const NO_RESULTS_TEMPLATE = [
	[
		'core/paragraph',
		{
			placeholder: __(
				'Add text or blocks that will display when a query returns no events.',
				'gatherpress'
			),
		},
	],
];

export const NO_RESULTS_BLOCK = [
	'core/query-no-results',
	{
		metadata: {
			name: __('No events', 'gatherpress'),
		},
	},
	NO_RESULTS_TEMPLATE,
];

const QUERY_PAGINATION_TEMPLATE = [
	[
		'core/query-pagination-previous',
		{
			label: __('Previous Events', 'gatherpress'),
			className: 'gatherpress-query-pagination-previous',
		},
	],
	['core/query-pagination-numbers'],
	[
		'core/query-pagination-next',
		{
			label: __('Next Events', 'gatherpress'),
			className: 'gatherpress-query-pagination-next',
		},
	],
];
export const QUERY_PAGINATION_BLOCK = [
	'core/query-pagination',
	{},
	QUERY_PAGINATION_TEMPLATE,
];
