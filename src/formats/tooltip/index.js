/**
 * WordPress dependencies.
 */
import { registerFormatType } from '@wordpress/rich-text';

/**
 * Internal dependencies.
 */
import { FORMAT_NAME } from './constants';
import { TooltipEdit } from './edit';
import './style.scss';

/**
 * Register the tooltip format type.
 *
 * This format type allows users to add hover-based tooltips to selected text
 * in the block editor. The tooltip content and colors are customizable.
 *
 * @since 1.0.0
 */
registerFormatType( FORMAT_NAME, {
	title: 'Tooltip',
	tagName: 'span',
	className: 'gatherpress-tooltip',
	attributes: {
		'data-gatherpress-tooltip': 'data-gatherpress-tooltip',
		'data-gatherpress-tooltip-text-color': 'data-gatherpress-tooltip-text-color',
		'data-gatherpress-tooltip-bg-color': 'data-gatherpress-tooltip-bg-color',
	},
	edit: TooltipEdit,
} );
