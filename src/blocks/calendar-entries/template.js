/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';

/**
 * The start time of an entry, as plain text.
 * Shared by the template and the transform in ../calendar/transforms.js.
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
 * Attributes of the Modal Manager in an entry: the time above the title,
 * with no gap between them. The default flex row with the theme's block gap
 * leaves a large space in a narrow day cell.
 * Shared by the template and the transform in ../calendar/transforms.js.
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

/**
 * The linked event title that opens the modal.
 * Shared by the template and the transform in ../calendar/transforms.js.
 */
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
 * Default template for the calendar-entries block: a Modal Manager holding
 * the start time, the linked event title that opens the modal, and the
 * popover modal content.
 *
 * The title is the trigger, so every event shows and announces its name.
 * The trigger class goes on core/post-title: GatherPress's Modal Manager
 * binds the element with the class or the tag right after it, and here
 * that tag is the title's link.
 */
const TEMPLATE = [
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
										orientation: 'vertical',
									},
									style: {
										spacing: {
											blockGap: '0.25rem',
										},
									},
								},
								[
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
											fontSize: 'medium',
										},
									],
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
								],
							],
							[ 'core/post-excerpt', {} ],
							[
								'core/buttons',
								{},
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

export default TEMPLATE;
