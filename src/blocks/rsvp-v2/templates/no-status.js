/**
 * WordPress dependencies.
 */
import { __, sprintf } from '@wordpress/i18n';

/**
 * Internal dependencies.
 */
import { getFromGlobal } from '../../../helpers/globals';

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
						name: __('RSVP Buttons', 'gatherpress'),
					},
				},
				[
					[
						'core/button',
						{
							text: __('RSVP', 'gatherpress'),
							tagName: 'button',
							className: 'gatherpress--open-modal',
						},
					],
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
										'<strong>RSVP to this event</strong>',
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
												'Attending',
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
			[
				'gatherpress/modal',
				{
					className: 'gatherpress--is-login-modal',
					metadata: {
						name: __('Login Modal', 'gatherpress'),
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
										'<strong>Login Required</strong>',
										'gatherpress'
									),
								},
							],
							[
								'core/paragraph',
								{
									content: sprintf(
										/* translators: %s: Login URL. */
										__(
											'This action requires an account. Please <a href="%s">Login</a> to RSVP to this event.',
											'gatherpress'
										),
										getFromGlobal('urls.loginUrl')
									),
									className: 'gatherpress--has-login-url',
								},
							],
							[
								'core/paragraph',
								{
									content: sprintf(
										/* translators: %s: Registration URL. */
										__(
											'Don\'t have an account? <a href="%s">Register here</a> to create one.',
											'gatherpress'
										),
										getFromGlobal('urls.registrationUrl')
									),
									className:
										'gatherpress--has-registration-url',
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
								},
								[
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

export default NO_STATUS;
