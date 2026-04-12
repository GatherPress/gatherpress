/**
 * WordPress dependencies.
 */
import { __, _x, sprintf } from '@wordpress/i18n';

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
							'Call to Action',
							'Block name displayed in the editor',
							'gatherpress',
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
								'gatherpress',
							),
							tagName: 'button',
							className: 'gatherpress-modal--trigger-open',
							metadata: {
								name: _x(
									'RSVP Button',
									'Block name displayed in the editor',
									'gatherpress',
								),
							},
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
								'gatherpress',
							),
							metadata: {
								name: _x(
									'RSVP Status',
									'Block name displayed in the editor',
									'gatherpress',
								),
							},
						},
					],
				],
			],
			[
				'gatherpress/modal',
				{
					className: 'gatherpress-modal--type-rsvp',
					metadata: {
						name: _x(
							'RSVP Modal',
							'Modal title in editor',
							'gatherpress',
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
										'gatherpress',
									),
									metadata: {
										name: _x(
											'RSVP Heading',
											'Block name displayed in the editor',
											'gatherpress',
										),
									},
								},
							],
							[
								'core/paragraph',
								{
									content: __(
										'To change your attendance status, simply click the <strong>Attending</strong> button below.',
										'gatherpress',
									),
									metadata: {
										name: _x(
											'RSVP Info',
											'Block name displayed in the editor',
											'gatherpress',
										),
									},
								},
							],
							[
								'gatherpress/form-field',
								{
									className: 'gatherpress-rsvp-field-anonymous',
									fieldType: 'checkbox',
									fieldName: 'gatherpress_rsvp_anonymous',
									label: sprintf(
										/* translators: 1: tooltip text, 2: label text */
										'<span class="gatherpress-tooltip" data-gatherpress-tooltip="%1$s">%2$s</span>',
										__(
											'Only admins will see your identity.',
											'gatherpress'
										),
										__(
											'List me as anonymous',
											'gatherpress'
										)
									),
									autocomplete: 'off',
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
									metadata: {
										name: _x(
											'Call to Action',
											'Block name displayed in the editor',
											'gatherpress',
										),
									},
								},
								[
									[
										'core/button',
										{
											text: _x(
												'Attending',
												'RSVP button label for confirming event attendance',
												'gatherpress',
											),
											tagName: 'button',
											className:
												'gatherpress-rsvp--trigger-update',
											metadata: {
												name: _x(
													'RSVP Button',
													'Block name displayed in the editor',
													'gatherpress',
												),
											},
										},
									],
									[
										'core/button',
										{
											text: _x(
												'Close',
												'Button label for closing modal dialog',
												'gatherpress',
											),
											tagName: 'button',
											className:
												'is-style-outline gatherpress-modal--trigger-close',
											metadata: {
												name: _x(
													'Close Button',
													'Block name displayed in the editor',
													'gatherpress',
												),
											},
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
