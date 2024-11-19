/**
 * WordPress dependencies
 */
import { registerBlockVariation } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';
// import { queryPaginationNext, queryPaginationPrevious } from '@wordpress/icons';

/**
 * Internal dependencies
 */
// import GPQLIcon from '../components/icon';

/**
 * Update UI for pagination blocks to speak 'events', not 'posts'.
 */
registerBlockVariation('core/query-pagination-previous', {
	category: 'gatherpress',
	keywords: [
		__('Page numbers', 'default'),
		__('Pagination', 'default'),
	],
	// icon: GPQLIcon( queryPaginationPrevious ),
	isActive: ['className'],
	attributes: {
		className: 'gatherpress-query-pagination-previous',
		label: __('Previous Events', 'gatherpress'),
	},
	// scope: ['block'],
	name: 'gatherpress-query-pagination-previous',
	title: __('Previous Events', 'gatherpress'),
	description: __('Displays the previous events link.', 'gatherpress'),
});
registerBlockVariation('core/query-pagination-next', {
	category: 'gatherpress',
	keywords: [
		__('Page numbers', 'default'),
		__('Pagination', 'default'),
	],
	// icon: GPQLIcon( queryPaginationNext ),
	isActive: ['className'],
	attributes: {
		className: 'gatherpress-query-pagination-next',
		label: __('Next Events', 'gatherpress'),
	},
	// scope: ['block'],
	name: 'gatherpress-query-pagination-next',
	title: __('Next Events', 'gatherpress'),
	description: __('Displays the next events link.', 'gatherpress'),
});
