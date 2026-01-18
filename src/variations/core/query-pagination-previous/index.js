/**
 * WordPress dependencies
 */
import { registerBlockVariation } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';

/**
 * Update UI for pagination blocks to speak 'events', not 'posts'.
 */
const CORE_BLOCK = 'core/query-pagination-previous';
const CLASS_NAME = 'gatherpress-query-pagination-previous';
const LABEL = __( 'Previous Events', 'gatherpress' );

export const QUERY_PAGINATION_PREVIOUS_VARIATION = [
	CORE_BLOCK,
	{
		className: CLASS_NAME,
		label: LABEL,
	},
];

registerBlockVariation( CORE_BLOCK, {
	category: 'gatherpress',
	keywords: [ __( 'Page numbers', 'gatherpress' ), __( 'Pagination', 'gatherpress' ) ],
	isActive: [ 'className' ],
	attributes: {
		className: CLASS_NAME,
		label: LABEL,
	},
	scope: [ 'block', 'inserter', 'transform' ],
	name: CLASS_NAME,
	title: LABEL,
	description: __( 'Displays the previous events link.', 'gatherpress' ),
} );
