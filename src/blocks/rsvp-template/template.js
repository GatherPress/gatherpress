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
					blockGap: 0,
				},
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
						name: __('Display Name', 'gatherpress'),
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
		],
	],
];

export default TEMPLATE;
