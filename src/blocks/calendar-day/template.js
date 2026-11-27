/**
 * WordPress dependencies
 */
import { _x } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import ENTRIES_TEMPLATE from '../calendar-entries/template';

/**
 * The Day Number paragraph, bound to the day of its calendar cell.
 * Shared by the template and the transform in ../calendar/transforms.js.
 */
export const DAY_NUMBER = [
	'core/paragraph',
	{
		className: 'gatherpress-calendar__day-number',
		fontSize: 'small',
		placeholder: 'DD',
		content: 'DD',
		style: {
			spacing: {
				margin: {
					top: '0',
					bottom: '0',
					left: '0',
					right: '0',
				},
			},
		},
		metadata: {
			bindings: {
				content: {
					source: 'gatherpress/calendar-day',
				},
			},
			name: _x(
				'Day Number',
				'Block variation name',
				'gatherpress',
			),
		},
	},
];

/**
 * Default template for the calendar-day block: the bound Day Number
 * paragraph and the Calendar Entries block.
 */
const TEMPLATE = [
	DAY_NUMBER,
	[
		'gatherpress/calendar-entries',
		{
			layout: { type: 'default' },
			className: 'is-style-default',
			style: {
				spacing: {
					blockGap: '0',
				},
				layout: {
					selfStretch: 'fill',
					flexSize: null,
				},
			},
		},
		ENTRIES_TEMPLATE,
	],
];

export default TEMPLATE;
