/**
 * WordPress dependencies
 */
import { registerBlockVariation } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';

/**
 * Update UI for pagination blocks to speak 'events', not 'posts'.
 */
const CORE_BLOCK = 'core/query-pagination-numbers';
const CLASS_NAME = 'gatherpress-query-pagination-numbers';

export const QUERY_PAGINATION_NUMBERS_VARIATION = [
	CORE_BLOCK,
	{
		className: CLASS_NAME,
	},
];

registerBlockVariation( CORE_BLOCK, {
	category: 'gatherpress',
	keywords: [ __( 'Page numbers', 'gatherpress' ), __( 'Pagination', 'gatherpress' ) ],
	isActive: [ 'className' ],
	attributes: {
		className: CLASS_NAME,
	},
	scope: [ 'block', 'inserter', 'transform' ],
	name: CLASS_NAME,
	title: __( 'Event Numbers', 'gatherpress' ),
	description: __(
		'Displays a list of event numbers for pagination.',
		'gatherpress',
	),
} );
