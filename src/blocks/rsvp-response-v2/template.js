/**
 * WordPress dependencies.
 */
import { __ } from '@wordpress/i18n';

const TEMPLATE = [
	[
		'core/group',
		{
			style: {
				spacing: {
					blockGap: 'var:preset|spacing|20',
					margin: {
						bottom: 'var:preset|spacing|30',
					},
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
					icon: 'groups',
				},
			],
			[
				'gatherpress/dropdown',
				{
					actAsSelect: true,
					dropdownId: 'dropdown-7968ad05-cf12-41ae-8392-7fb01e166188',
					// Translators: %d is the count of attendees.
					label: __('Attending (%d)', 'gatherpress'),
					metadata: {
						// Translators: %d is the count of attendees.
						name: __('Attending (%d)', 'gatherpress'),
					},
				},
				[
					[
						'gatherpress/dropdown-item',
						{
							text:
								'<a href="#">' +
								// Translators: %d is the count of attendees.
								__('Attending (%d)', 'gatherpress') +
								'</a>',
							metadata: {
								// Translators: %d is the count of attendees.
								name: __('Attending (%d)', 'gatherpress'),
							},
							className: 'gatherpress--rsvp-attending',
						},
					],
					[
						'gatherpress/dropdown-item',
						{
							text:
								'<a href="#">' +
								// Translators: %d is the count of users on the waiting list.
								__('Waiting List (%d)', 'gatherpress') +
								'</a>',
							metadata: {
								// Translators: %d is the count of users on the waiting list.
								name: __('Waiting List (%d)', 'gatherpress'),
							},
							className: 'gatherpress--rsvp-waiting-list',
						},
					],
					[
						'gatherpress/dropdown-item',
						{
							text:
								'<a href="#">' +
								// Translators: %d is the count of users not attending.
								__('Not Attending (%d)', 'gatherpress') +
								'</a>',
							metadata: {
								// Translators: %d is the count of users not attending.
								name: __('Not Attending (%d)', 'gatherpress'),
							},
							className: 'gatherpress--rsvp-not-attending',
						},
					],
				],
			],
		],
	],
	[
		'core/group',
		{
			layout: {
				type: 'grid',
				columns: 3,
				justifyContent: 'center',
				alignContent: 'space-around',
				minimumColumnWidth: '8rem',
			},
			className: 'gatherpress--rsvp-responses',
		},
		[['gatherpress/rsvp-template', {}]],
	],
	[
		'core/group',
		{
			metadata: {
				name: __('Empty RSVP', 'gatherpress'),
			},
			className: 'gatherpress--empty-rsvp',
		},
		[
			[
				'core/paragraph',
				{
					content: __(
						'No one is attending this event yet.',
						'gatherpress'
					),
				},
			],
		],
	],
];

export default TEMPLATE;
