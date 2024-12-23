/**
 * WordPress dependencies.
 */
import { __ } from '@wordpress/i18n';

const ATTENDING = [
	[
		'gatherpress/modal-manager',
		{},
		[
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
							text: __('Edit RSVP', 'gatherpress'),
							tagName: 'button',
							className: 'gatherpress--open-modal',
						},
					],
				],
			],
			[
				'gatherpress/modal',
				{
					className: 'gatherpress-rsvp-modal',
					metadata: {
						name: __('RSVP Modal', 'gatherpress'),
					},
				},
				[
					[
						'gatherpress/modal-content',
						{ className: 'gatherpress-rsvp-modal-content' },
						[
							[
								'core/heading',
								{
									level: 3,
									content: __(
										'Update your RSVP',
										'gatherpress'
									),
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
											text: __(
												'Not Attending',
												'gatherpress'
											),
											tagName: 'button',
											className:
												'gatherpress--update-rsvp',
										},
									],
									[
										'core/button',
										{
											text: __('Close', 'gatherpress'),
											tagName: 'button',
											className:
												'gatherpress--close-modal',
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

export default ATTENDING;
