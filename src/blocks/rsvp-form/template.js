/**
 * WordPress dependencies.
 */
import { __ } from '@wordpress/i18n';

const TEMPLATE = [
	[
		'core/group',
		{
			metadata: {
				gatherpressRsvpFormVisibility: {
					onSuccess: 'show',
					whenPast: 'hide',
				},
			},
			style: {
				spacing: {
					margin: {
						bottom: '1rem',
					},
				},
			},
		},
		[
			[
				'core/heading',
				{
					content: __(
						'Thank you for your RSVP!',
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
			[
				'core/paragraph',
				{
					content: __(
						'Please check your email for a confirmation link to complete your registration.',
						'gatherpress',
					),
				},
			],
		],
	],
	[
		'core/group',
		{
			metadata: {
				gatherpressRsvpFormVisibility: {
					whenPast: 'show',
				},
			},
			style: {
				spacing: {
					margin: {
						bottom: '1rem',
					},
				},
			},
		},
		[
			[
				'core/heading',
				{
					content: __(
						'This event has already occurred.',
						'gatherpress',
					),
					level: 3,
					style: {
						typography: {
							fontWeight: '600',
						},
					},
				},
			],
			[
				'core/paragraph',
				{
					content: __(
						'Registration for this event is now closed.',
						'gatherpress',
					),
				},
			],
		],
	],
	[
		'gatherpress/form-field',
		{
			metadata: {
				gatherpressRsvpFormVisibility: {
					onSuccess: 'hide',
					whenPast: 'hide',
				},
			},
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
			metadata: {
				gatherpressRsvpFormVisibility: {
					onSuccess: 'hide',
					whenPast: 'hide',
				},
			},
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
			metadata: {
				gatherpressRsvpFormVisibility: {
					onSuccess: 'hide',
					whenPast: 'hide',
				},
			},
			className: 'gatherpress-rsvp-field-guest-count',
			fieldType: 'number',
			fieldName: 'gatherpress_rsvp_guest_count',
			'data-gatherpress-field-type': 'guest-count',
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
			metadata: {
				gatherpressRsvpFormVisibility: {
					onSuccess: 'hide',
					whenPast: 'hide',
				},
			},
			className: 'gatherpress-rsvp-field-anonymous',
			fieldType: 'checkbox',
			fieldName: 'gatherpress_rsvp_anonymous',
			'data-gatherpress-field-type': 'anonymous',
			fieldValue: false,
			label: __( 'List me as anonymous', 'gatherpress' ),
		},
	],
	[
		'gatherpress/form-field',
		{
			metadata: {
				gatherpressRsvpFormVisibility: {
					onSuccess: 'hide',
					whenPast: 'hide',
				},
			},
			fieldType: 'checkbox',
			fieldName: 'gatherpress_event_updates_opt_in',
			fieldValue: false,
			label: __( 'Email me updates about this event', 'gatherpress' ),
		},
	],
	[
		'core/buttons',
		{
			metadata: {
				gatherpressRsvpFormVisibility: {
					onSuccess: 'hide',
					whenPast: 'hide',
				},
			},
		},
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
