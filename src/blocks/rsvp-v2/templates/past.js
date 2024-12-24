/**
 * WordPress dependencies.
 */
import { __ } from '@wordpress/i18n';

const PAST = [
	[
		'core/buttons',
		{
			align: 'center',
			layout: { type: 'flex', justifyContent: 'center' },
			metadata: {
				name: __('RSVP Buttons', 'gatherpress'),
			},
		},
		[
			[
				'core/button',
				{
					text: __('Past Event', 'gatherpress'),
					tagName: 'button',
					className: 'gatherpress--is-disabled',
				},
			],
		],
	],
];

export default PAST;
