/**
 * WordPress dependencies.
 */
import { __ } from '@wordpress/i18n';

const TEMPLATE = [
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
					text: __( 'Click Me!', 'gatherpress' ),
					className: 'gatherpress-modal--trigger-open',
					tagName: 'button',
				},
			],
		],
	],
	[
		'gatherpress/modal',
		{},
		[
			[
				'gatherpress/modal-content',
				{},
				[
					[
						'core/paragraph',
						{
							content: __(
								'Hello! This is a modal. You can customize this content or add blocks here.',
								'gatherpress',
							),
						},
					],
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
									text: __( 'Close', 'gatherpress' ),
									className: 'gatherpress-modal--trigger-close',
									tagName: 'button',
								},
							],
						],
					],
				],
			],
		],
	],
];

export default TEMPLATE;
