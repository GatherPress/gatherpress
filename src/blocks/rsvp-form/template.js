/**
 * WordPress dependencies.
 */
import { __ } from '@wordpress/i18n';

const TEMPLATE = [
	[
		'core/group',
		{
			className: 'gatherpress-rsvp-form-message',
			style: {
				display: {
					display: 'none',
				},
			},
		},
		[
			[
				'core/heading',
				{
					content: __(
						'Thank you for your RSVP! Please check your email for a confirmation link to complete your registration.',
						'gatherpress',
					),
					level: 3,
					style: {
						typography: {
							fontWeight: '600',
						},
						color: {
							text: '#16a085',
						},
					},
				},
			],
		],
	],
	[
		'gatherpress/form-field',
		{
			fieldName: 'author',
			label: __( 'Name', 'gatherpress' ),
			placeholder: __( "Name as you'd like it to appear", 'gatherpress' ),
			required: true,
			autocomplete: 'name',
		},
	],
	[
		'gatherpress/form-field',
		{
			fieldType: 'email',
			fieldName: 'email',
			label: __( 'Email', 'gatherpress' ),
			placeholder: __( 'your@email.com', 'gatherpress' ),
			required: true,
			autocomplete: 'email',
		},
	],
	[
		'gatherpress/form-field',
		{
			fieldType: 'checkbox',
			fieldName: 'gatherpress_event_email_updates',
			fieldValue: false,
			label: __( 'Email me updates about this event', 'gatherpress' ),
		},
	],
	[
		'core/buttons',
		{},
		[
			[
				'core/button',
				{
					className: 'gatherpress-submit-button',
					tagName: 'button',
					text: __( 'Submit', 'gatherpress' ),
				},
			],
		],
	],
];

export default TEMPLATE;
