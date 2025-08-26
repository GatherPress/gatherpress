/**
 * WordPress dependencies.
 */
import { __, _x } from '@wordpress/i18n';

/**
 * Internal dependencies.
 */
import RSVP_FORM_TEMPLATE from '../../rsvp-form/template';

const NO_STATUS = [
	[
		'gatherpress/modal-manager',
		{},
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
								'RSVP',
								'Button label for editing RSVP',
								'gatherpress',
							),
							tagName: 'button',
							className: 'gatherpress--open-modal',
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
				'gatherpress/modal',
				{
					className: 'gatherpress--is-rsvp-modal',
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
										'<strong>RSVP to this event</strong>',
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
										'To set your attendance status, simply click the <strong>Attend</strong> button below.',
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
									fieldType: 'checkbox',
									fieldName: 'gatherpress_rsvp_anonymous',
									label: 'List me as anonymous.',
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
												'Attend',
												'RSVP button label for confirming event attendance',
												'gatherpress',
											),
											tagName: 'button',
											className:
												'gatherpress--update-rsvp',
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
												'is-style-outline gatherpress--close-modal',
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
			[
				'gatherpress/modal',
				{
					className: 'gatherpress--is-login-modal',
					metadata: {
						name: _x(
							'Login Modal',
							'Block title for the login modal',
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
										'<strong>Login Required</strong>',
										'Login modal header',
										'gatherpress',
									),
									metadata: {
										name: _x(
											'Login Heading',
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
										'This action requires an account. Please <a href="#gatherpress-login-url">Login</a> to RSVP to this event.',
										'gatherpress',
									),
									className: 'gatherpress--has-login-url',
									metadata: {
										name: _x(
											'Login Info',
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
										'Don\'t have an account? <a href="#gatherpress-registration-url">Register here</a> to create one.',
										'gatherpress',
									),
									className:
										'gatherpress--has-registration-url',
									metadata: {
										name: _x(
											'Register Info',
											'Block name displayed in the editor',
											'gatherpress',
										),
									},
								},
							],
							[
								'gatherpress/modal-manager',
								{},
								[
									[
										'core/paragraph',
										{
											content: __(
												'Don\'t have an account? <a href="#">RSVP here</a> as a guest.',
												'gatherpress',
											),
											className: 'gatherpress--open-modal',
										},
									],
									[
										'gatherpress/modal',
										{
											className: 'gatherpress--is-open-rsvp-modal',
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
																'<strong>Open RSVP</strong>',
																'Open RSVP modal header',
																'gatherpress',
															),
														},
													],
													[
														'gatherpress/rsvp-form',
														{},
														[
															...RSVP_FORM_TEMPLATE.slice(0, -1), // All items except the last buttons block
															[
																'core/buttons',
																{},
																[
																	...RSVP_FORM_TEMPLATE[RSVP_FORM_TEMPLATE.length - 1][2], // Get the Submit button from the original
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
								],
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
												'Close',
												'Button label for closing modal dialog',
												'gatherpress',
											),
											tagName: 'button',
											className:
												'gatherpress--close-modal',
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

export default NO_STATUS;
