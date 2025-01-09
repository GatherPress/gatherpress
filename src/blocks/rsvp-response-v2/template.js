/**
 * WordPress dependencies.
 */
import { __ } from '@wordpress/i18n';

/* translators: %d is the count of attendees */
const attending = __('Attending (%d)', 'gatherpress');

/* translators: %d is the count of users on the waiting list */
const waitingList = __('Waiting List (%d)', 'gatherpress');

/* translators: %d is the count of users not attending */
const notAttending = __('Not Attending (%d)', 'gatherpress');

const translations = {
	attending,
	waitingList,
	notAttending,
	noOne: __('No one is attending this event yet.', 'gatherpress'),
};

const TEMPLATE = [
	[
		'core/group',
		{
			style: {
				spacing: {
					blockGap: 'var:preset|spacing|20',
					margin: { bottom: 'var:preset|spacing|30' },
				},
			},
			layout: { type: 'flex', flexWrap: 'nowrap' },
		},
		[
			['gatherpress/icon', { icon: 'groups' }],
			[
				'gatherpress/dropdown',
				{
					actAsSelect: true,
					dropdownId: 'dropdown-7968ad05-cf12-41ae-8392-7fb01e166188',
					label: translations.attending,
					metadata: { name: translations.attending },
				},
				[
					[
						'gatherpress/dropdown-item',
						{
							text: `<a href="#">${translations.attending}</a>`,
							metadata: { name: translations.attending },
							className: 'gatherpress--rsvp-attending',
						},
					],
					[
						'gatherpress/dropdown-item',
						{
							text: `<a href="#">${translations.waitingList}</a>`,
							metadata: { name: translations.waitingList },
							className: 'gatherpress--rsvp-waiting-list',
						},
					],
					[
						'gatherpress/dropdown-item',
						{
							text: `<a href="#">${translations.notAttending}</a>`,
							metadata: { name: translations.notAttending },
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
			metadata: { name: __('Empty RSVP', 'gatherpress') },
			className: 'gatherpress--empty-rsvp gatherpress--is-not-visible',
		},
		[['core/paragraph', { content: translations.noOne }]],
	],
];

export default TEMPLATE;
