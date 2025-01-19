/**
 * WordPress dependencies.
 */
import { __, _x } from '@wordpress/i18n';

const NOT_ATTENDING = [
	[
		'gatherpress/modal-manager',
		{
			style: {
				spacing: {
					blockGap: 'var:preset|spacing|40',
				},
			},
		},
		[
			[
				'core/buttons',
				{
					align: 'center',
					layout: { type: 'flex', justifyContent: 'center' },
					metadata: {
						name: _x(
							'RSVP Buttons',
							'Section title in editor',
							'gatherpress'
						),
					},
				},
				[
					[
						'core/button',
						{
							text: _x(
								'Edit RSVP',
								'Button label for editing RSVP',
								'gatherpress'
							),
							tagName: 'button',
							className: 'gatherpress--open-modal',
						},
					],
				],
			],
			[
				'core/group',
				{
					style: {
						spacing: {
							blockGap: 'var:preset|spacing|20',
						},
					},
					layout: {
						type: 'flex',
						flexWrap: 'nowrap',
					},
				},
				[
					[
						'gatherpress/icon',
						{
							icon: 'dismiss',
							iconSize: 24,
						},
					],
					[
						'core/paragraph',
						{
							style: {
								spacing: {
									margin: {
										top: '0',
									},
									padding: {
										top: '0',
									},
								},
							},
							content: _x(
								'<strong>Not Attending</strong>',
								'RSVP status indicator',
								'gatherpress'
							),
						},
					],
				],
			],
			[
				'gatherpress/modal',
				{
					className: 'gatherpress--is-rsvp-modal',
					metadata: {
						name: _x(
							'RSVP Modal',
							'Modal title in editor',
							'gatherpress'
						),
					},
				},
				[
					[
						'gatherpress/modal-content',
						{},
						[
							[
								'core/paragraph',
								{
									style: {
										spacing: {
											margin: {
												top: '0',
											},
											padding: {
												top: '0',
											},
										},
									},
									content: _x(
										"<strong>You're Not Attending</strong>",
										'RSVP modal header',
										'gatherpress'
									),
								},
							],
							[
								'core/paragraph',
								{
									content: __(
										'To set or change your attending status, simply click the <strong>Attending</strong> button below.',
										'gatherpress'
									),
								},
							],
							['gatherpress/rsvp-anonymous-checkbox', {}],
							[
								'core/buttons',
								{
									align: 'left',
									layout: {
										type: 'flex',
										justifyContent: 'flex-start',
									},
									style: {
										spacing: {
											margin: {
												bottom: '0',
											},
											padding: {
												bottom: '0',
											},
										},
									},
								},
								[
									[
										'core/button',
										{
											text: _x(
												'Attending',
												'RSVP button label for confirming event attendance',
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
											text: _x(
												'Close',
												'Button label for closing modal dialog',
												'gatherpress'
											),
											tagName: 'button',
											className:
												'is-style-outline gatherpress--close-modal',
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

export default NOT_ATTENDING;
