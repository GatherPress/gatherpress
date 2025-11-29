/**
 * WordPress dependencies.
 */
import { __, _x } from '@wordpress/i18n';

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
									content: _x(
										'<strong>Attending</strong>',
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
					[ 'gatherpress/rsvp-guest-count-display', {} ],
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
										"<strong>You're Attending</strong>",
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
										'To change your attendance status, simply click the <strong>Not Attending</strong> button below.',
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
									className: 'gatherpress-rsvp-field-guests',
									fieldType: 'number',
									fieldName: 'gatherpress_rsvp_guests',
									label: __( 'Number of guests?', 'gatherpress' ),
									placeholder: __( '0', 'gatherpress' ),
									minValue: 0,
									inlineLayout: true,
									fieldWidth: 10,
									inputPadding: 5,
									autocomplete: 'off',
								},
							],
							[
								'gatherpress/form-field',
								{
									className: 'gatherpress-rsvp-field-anonymous',
									fieldType: 'checkbox',
									fieldName: 'gatherpress_rsvp_anonymous',
									label: __( 'List me as anonymous', 'gatherpress' ),
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
												'Not Attending',
												'RSVP button label for declining event attendance',
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

export default ATTENDING;
