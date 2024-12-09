/**
 * WordPress dependencies.
 */
import { __ } from '@wordpress/i18n';

const NOT_ATTENDING = [
	[
		'core/buttons',
		{
			align: 'center',
			layout: { type: 'flex', justifyContent: 'center' },
		},
		[
			[
				'core/button',
				{
					// text: initialLabel,
					text: __('RSVP', 'gatherpress'),
					tagName: 'button',
					className: 'gatherpress-rsvp--js-open-modal',
				},
			],
		],
	],
	[
		'core/paragraph',
		{
			content: __('Attending', 'gatherpress'),
		},
	],
	[
		'gatherpress/modal',
		{ className: 'gatherpress-rsvp-modal' },
		[
			[
				'gatherpress/modal-content',
				{ className: 'gatherpress-rsvp-modal-content' },
				[
					[
						'core/heading',
						{
							level: 3,
							content: __('Update your RSVP', 'gatherpress'),
						},
					],
					[
						'core/paragraph',
						{
							content: __(
								'To set or change your attending status, simply click the <strong>Not Attending</strong> button below.',
								'gatherpress'
							),
						},
					],
					[
						'core/buttons',
						{
							align: 'left',
							layout: {
								type: 'flex',
								justifyContent: 'flex-start',
							},
						},
						[
							[
								'core/button',
								{
									text: __('Attend', 'gatherpress'),
									tagName: 'button',
									className:
										'gatherpress-rsvp--js-status-attending',
								},
							],
							[
								'core/button',
								{
									text: __('Close', 'gatherpress'),
									tagName: 'button',
									className:
										'gatherpress-rsvp--js-close-modal',
								},
							],
						],
					],
				],
			],
		],
	],
];

export default NOT_ATTENDING;
