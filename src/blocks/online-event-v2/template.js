/**
 * WordPress dependencies.
 */
import { __, sprintf } from '@wordpress/i18n';

/**
 * Default template for the online-event-v2 block.
 */
const TEMPLATE = [
	[
		'core/group',
		{
			style: {
				spacing: {
					blockGap: 'var:preset|spacing|20',
					margin: {
						top: '0',
						bottom: '0',
					},
				},
			},
			layout: {
				type: 'flex',
				flexWrap: 'nowrap',
			},
		},
		[
			[ 'gatherpress/icon', { icon: 'video-alt2' } ],
			[
				'gatherpress/online-event-link',
				{
					linkText: sprintf(
						/* translators: %1$s: tooltip text, %2$s: label text */
						'<span class="gatherpress-tooltip" data-gatherpress-tooltip="%1$s">%2$s</span>',
						__( 'Link available for attendees only.', 'gatherpress' ),
						__( 'Online event', 'gatherpress' )
					),
				},
			],
		],
	],
];

export default TEMPLATE;
