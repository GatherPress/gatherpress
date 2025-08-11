/**
 * WordPress dependencies.
 */
import { _x } from '@wordpress/i18n';

const TEMPLATE = [
	[
		'core/group',
		{
			style: {
				spacing: {
					blockGap: 0,
				},
			},
			metadata: {
				name: _x(
					'RSVP User Info',
					'Block name displayed in the editor',
					'gatherpress',
				),
			},
		},
		[
			[
				'core/avatar',
				{
					isLink: true,
					align: 'center',
				},
			],
			[
				'core/comment-author-name',
				{
					metadata: {
						name: _x(
							'Display Name',
							'Block name displayed in the editor',
							'gatherpress',
						),
					},
					textAlign: 'center',
					style: {
						spacing: {
							margin: {
								top: '0',
								bottom: '0',
							},
						},
					},
					fontSize: 'medium',
				},
			],
			[
				'gatherpress/rsvp-guest-count-display',
				{
					align: 'center',
					style: {
						spacing: {
							margin: {
								top: '0',
								bottom: '0',
							},
						},
					},
					fontSize: 'small',
				},
			],
		],
	],
];

export default TEMPLATE;
