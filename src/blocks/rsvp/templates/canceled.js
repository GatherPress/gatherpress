/**
 * WordPress dependencies
 */
import { _x } from '@wordpress/i18n';

const CANCELED = [
	[
		'core/buttons',
		{
			align: 'center',
			layout: { type: 'flex', justifyContent: 'center' },
			metadata: {
				name: _x(
					'Call to Action',
					'Block name displayed in the editor',
					'gatherpress',
				),
			},
		},
		[
			[
				'core/button',
				{
					text: _x(
						'Canceled',
						'Button label for canceled RSVP',
						'gatherpress',
					),
					tagName: 'button',
					className: 'gatherpress--is-disabled',
					metadata: {
						name: _x(
							'RSVP Button',
							'Block name displayed in the editor',
							'gatherpress',
						),
					},
				},
			],
		],
	],
];

export default CANCELED;
