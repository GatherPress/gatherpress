/**
 * WordPress dependencies.
 */
import { __, _x } from '@wordpress/i18n';

/* translators: %d is the count of attendees */
const attending = _x(
	'Attending (%d)',
	'Filter option to view list of confirmed attendees',
	'gatherpress',
);

/* translators: %d is the count of users on the waiting list */
const waitingList = _x(
	'Waiting List (%d)',
	'Filter option to view list of waitlisted attendees',
	'gatherpress',
);

/* translators: %d is the count of users not attending */
const notAttending = _x(
	'Not Attending (%d)',
	'Filter option to view list of declined attendees',
	'gatherpress',
);

const translations = {
	attending,
	waitingList,
	notAttending,
	noOne: __( 'No one is attending this event yet.', 'gatherpress' ),
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
			layout: {
				type: 'flex',
				flexWrap: 'nowrap',
				justifyContent: 'space-between',
			},
		},
		[
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
					[ 'gatherpress/icon', { icon: 'groups' } ],
					[
						'gatherpress/dropdown',
						{
							actAsSelect: true,
							label: translations.attending,
							metadata: { name: translations.attending },
						},
						[
							[
								'gatherpress/dropdown-item',
								{
									text: `<a href="#">${ translations.attending }</a>`,
									metadata: { name: translations.attending },
									className: 'gatherpress--is-attending',
								},
							],
							[
								'gatherpress/dropdown-item',
								{
									text: `<a href="#">${ translations.waitingList }</a>`,
									metadata: {
										name: translations.waitingList,
									},
									className: 'gatherpress--is-waiting-list',
								},
							],
							[
								'gatherpress/dropdown-item',
								{
									text: `<a href="#">${ translations.notAttending }</a>`,
									metadata: {
										name: translations.notAttending,
									},
									className:
										'gatherpress--is-not-attending',
								},
							],
						],
					],
				],
			],
			[ 'gatherpress/rsvp-response-toggle' ],
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
		[ [ 'gatherpress/rsvp-template', {} ] ],
	],
	[
		'core/group',
		{
			metadata: {
				name: _x(
					'Empty RSVP',
					'Block name displayed in the editor',
					'gatherpress',
				),
			},
			className: 'gatherpress-rsvp-response--no-responses',
		},
		[
			[
				'core/paragraph',
				{
					content: translations.noOne,
					metadata: {
						name: _x(
							'Empty RSVP Text',
							'Block name displayed in the editor',
							'gatherpress',
						),
					},
				},
			],
		],
	],
];

export default TEMPLATE;
