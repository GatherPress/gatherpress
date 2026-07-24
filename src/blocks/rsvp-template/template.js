/**
 * WordPress dependencies
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
					// Centering is declared both ways on purpose: newer
					// WordPress moved the block's text alignment from the
					// `textAlign` attribute to the typography block support
					// (`style.typography.textAlign`) and silently drops the
					// legacy attribute on insert, while our WP 6.7 floor
					// still reads the attribute. Each version keeps the one
					// it understands.
					textAlign: 'center',
					style: {
						typography: {
							textAlign: 'center',
						},
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
