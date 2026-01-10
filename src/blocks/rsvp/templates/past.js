/**
 * WordPress dependencies.
 */
import { _x } from '@wordpress/i18n';

const PAST = [
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
						'Past Event',
						'Button label for past RSVP',
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

export default PAST;
