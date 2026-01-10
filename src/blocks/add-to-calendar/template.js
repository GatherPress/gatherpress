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
			[ 'gatherpress/icon', { icon: 'calendar' } ],
			[
				'gatherpress/dropdown',
				{
					label: __( 'Add to calendar', 'gatherpress' ),
					metadata: {
						name: __( 'Add to calendar', 'gatherpress' ),
					},
				},
				[
					[
						'gatherpress/dropdown-item',
						{
							text: `<a href="#gatherpress-google-calendar" rel="noreferrer noopener nofollow" target="_blank">${ __( 'Google Calendar', 'gatherpress' ) }</a>`,
							metadata: {
								name: __( 'Google Calendar', 'gatherpress' ),
							},
						},
					],
					[
						'gatherpress/dropdown-item',
						{
							text: `<a href="#gatherpress-ical-calendar" rel="noreferrer noopener nofollow">${ __( 'iCal', 'gatherpress' ) }</a>`,
							metadata: {
								name: __( 'iCal', 'gatherpress' ),
							},
						},
					],
					[
						'gatherpress/dropdown-item',
						{
							text: `<a href="#gatherpress-outlook-calendar" rel="noreferrer noopener nofollow">${ __( 'Outlook', 'gatherpress' ) }</a>`,
							metadata: {
								name: __( 'Outlook', 'gatherpress' ),
							},
						},
					],
					[
						'gatherpress/dropdown-item',
						{
							text: `<a href="#gatherpress-yahoo-calendar" rel="noreferrer noopener nofollow" target="_blank">${ __( 'Yahoo Calendar', 'gatherpress' ) }</a>`,
							metadata: {
								name: __( 'Yahoo Calendar', 'gatherpress' ),
							},
						},
					],
				],
			],
		],
	],
];

export default TEMPLATE;
