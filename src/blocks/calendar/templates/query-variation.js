/**
 * WordPress dependencies
 */
import { __, _x } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import CALENDAR from './calendar';

/**
 * Inner blocks of the Event Calendar variation of core/query: the bound
 * Calendar Heading, the Previous and Next pagination, and the calendar.
 */
const QUERY_VARIATION = [
	[
		'core/heading',
		{
			level: 2,
			className: 'gatherpress-calendar__heading has-text-align-center',
			typography: {
				textAlign: 'center',
			},
			metadata: {
				bindings: {
					content: {
						source: 'gatherpress/calendar-heading',
					},
				},
				name: _x(
					'Calendar Heading',
					'Query template heading block name',
					'gatherpress',
				),
			},
		},
	],
	[
		'core/query-pagination',
		{
			className: 'gatherpress-calendar-pagination',
			paginationArrow: 'chevron',
			layout: {
				type: 'flex',
				justifyContent: 'space-between',
			},
			metadata: {
				name: _x(
					'Calendar Pagination',
					'Query template pagination block name',
					'gatherpress',
				),
			},
		},
		[
			[
				'core/query-pagination-previous',
				{
					className: 'gatherpress-calendar-pagination-previous',
					label: __( 'Previous Month', 'gatherpress' ),
					metadata: {
						name: _x(
							'Previous Calendar Period',
							'Query template previous pagination block name',
							'gatherpress',
						),
					},
				},
			],
			[
				'core/query-pagination-next',
				{
					className: 'gatherpress-calendar-pagination-next',
					label: __( 'Next Month', 'gatherpress' ),
					metadata: {
						name: _x(
							'Next Calendar Period',
							'Query template next pagination block name',
							'gatherpress',
						),
					},
				},
			],
		],
	],
	[
		'gatherpress/calendar',
		{
			style: {
				spacing: {
					padding: { top: '0', bottom: '0', left: '0', right: '0' },
				},
			},
		},
		CALENDAR,
	],
];

export default QUERY_VARIATION;
