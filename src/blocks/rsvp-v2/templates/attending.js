/**
 * WordPress dependencies.
 */
import { __ } from '@wordpress/i18n';

const ATTENDING = [
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
						name: __('RSVP Buttons', 'gatherpress'),
					},
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
				'core/group',
				{
					style: {
						spacing: {
							blockGap: '0',
						},
					},
				},
				[
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
									icon: 'yes-alt',
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
									content: __(
										'<strong>Attending</strong>',
										'gatherpress'
									),
								},
							],
						],
					],
					['gatherpress/rsvp-guest-count-display', {}],
				],
			],
			[
				'gatherpress/modal',
				{
					className: 'gatherpress--is-rsvp-modal',
					metadata: {
						name: __('RSVP Modal', 'gatherpress'),
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
									content: __(
										"<strong>You're Attending</strong>",
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
							['gatherpress/rsvp-guest-count-input', {}],
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

export default ATTENDING;
