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
					'RSVP Buttons',
					'Section title in editor',
					'gatherpress'
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
						'gatherpress'
					),
					tagName: 'button',
					className: 'gatherpress--is-disabled',
				},
			],
		],
	],
];

export default PAST;
