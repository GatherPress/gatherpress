import { __, _x } from '@wordpress/i18n';

/**
 * The start time of an entry, as plain text.
 * Shared by ENTRIES_TEMPLATE and the transform in ../transforms.js.
 */
export const ENTRY_START_TIME = [
	'gatherpress/event-date',
	{
		displayType: 'start',
		startDateFormat: 'G:i',
		style: {
			spacing: {
				padding: { top: '0', bottom: '0', left: '0', right: '0' },
				margin: { top: '0', bottom: '0', left: '0', right: '0' },
			},
		},
		fontSize: 'small',
	},
];

/**
 * The linked event title that opens the modal.
 * Shared by ENTRIES_TEMPLATE and the transform in ../transforms.js.
 */
/**
 * Attributes of the Modal Manager in an entry: the time above the title,
 * with no gap between them. The default flex row with the theme's block gap
 * leaves a large space in a narrow day cell.
 * Shared by ENTRIES_TEMPLATE and the transform in ../transforms.js.
 */
export const ENTRY_MODAL_MANAGER_ATTRIBUTES = {
	layout: {
		type: 'flex',
		orientation: 'vertical',
		justifyContent: 'center',
	},
	style: {
		spacing: {
			blockGap: '0',
		},
	},
};

export const ENTRY_TITLE_TRIGGER = [
	'core/post-title',
	{
		level: 0,
		isLink: true,
		className: 'gatherpress-modal--trigger-open',
		style: {
			spacing: {
				padding: { top: '0', bottom: '0', left: '0', right: '0' },
				margin: { top: '0', bottom: '0', left: '0', right: '0' },
			},
		},
		fontSize: 'small',
	},
];

/**
 * The inner template inside gatherpress/calendar-entries:
 * Modal Manager holding the start time, the linked event title that opens
 * the modal, and the popover modal content.
 *
 * The title is the trigger, so every event shows and announces its name.
 * The trigger class goes on core/post-title: GatherPress's Modal Manager
 * binds the element with the class or the tag right after it, and here
 * that tag is the title's link.
 */
export const ENTRIES_TEMPLATE = [
	[
		'gatherpress/modal-manager',
		ENTRY_MODAL_MANAGER_ATTRIBUTES,
		[
			ENTRY_START_TIME,
			ENTRY_TITLE_TRIGGER,
			[
				'gatherpress/modal',
				{},
				[
					[
						'gatherpress/modal-content',
						{
							style: {
								dimensions: { maxWidth: '400px' },
								spacing: {
									padding: {
										top: 'var:preset|spacing|30',
										bottom: 'var:preset|spacing|30',
										left: 'var:preset|spacing|30',
										right: 'var:preset|spacing|30',
									},
								},
							},
							backgroundColor: 'base',
						},
						[
							[
								'core/group',
								{
									layout: {
										type: 'flex',
										flexWrap: 'nowrap',
									},
								},
								[
									[
										'gatherpress/event-date',
										{
											style: {
												spacing: {
													padding: {
														top: '0',
														bottom: '0',
														left: '0',
														right: '0',
													},
													margin: {
														top: '0',
														bottom: '0',
														left: '0',
														right: '0',
													},
												},
											},
											fontSize: 'small',
										},
									],
									[
										'core/post-title',
										{
											level: 3,
											isLink: true,
											style: {
												spacing: {
													padding: {
														top: '0',
														bottom: '0',
														left: '0',
														right: '0',
													},
													margin: {
														top: '0',
														bottom: '0',
														left: '0',
														right: '0',
													},
												},
											},
											fontSize: 'small',
										},
									],
								],
							],
							[ 'core/post-excerpt', {} ],
							[
								'core/buttons',
								{
									align: 'center',
									layout: {
										type: 'flex',
										justifyContent: 'center',
									},
								},
								[
									[
										'core/button',
										{
											tagName: 'button',
											className:
												'gatherpress-modal--trigger-close',
											text: __(
												'Close',
												'gatherpress',
											),
										},
									],
								],
							],
						],
					],
				],
			],
		],
	],
];

/**
 * The Day Number paragraph, bound to the day of its calendar cell.
 * Shared by DAY_TEMPLATE and the transform in ../transforms.js.
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
 * The inner template inside gatherpress/calendar-day:
 * Bound Day Number paragraph + Calendar Entries block.
 */
export const DAY_TEMPLATE = [
	[
		'gatherpress/calendar-day',
		{},
		[
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
		],
	],
];

/**
 * The inner template inside gatherpress/calendar:
 * Week container holding the day template.
 */
export const CALENDAR_TEMPLATE = [
	[ 'gatherpress/calendar-week', {}, DAY_TEMPLATE ],
];

/**
 * The complete InnerBlocks tree for the core/query block variation:
 * Bound Month Heading + Pagination (Previous/Next Month) + Calendar Block.
 */
export const QUERY_VARIATION_INNER_BLOCKS = [
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
		CALENDAR_TEMPLATE,
	],
];

/**
 * The date format used throughout the component.
 *
 * @type {string}
 * @since 0.1.0
 */
export const DATE_FORMAT = 'Y-m-d';
