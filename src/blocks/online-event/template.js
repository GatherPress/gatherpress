/**
 * Default template for the online-event block.
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
			[
				'core/icon',
				{
					icon: 'core/capture-video',
					style: { dimensions: { width: '24px' } },
				},
			],
			[ 'gatherpress/online-event-link' ],
		],
	],
];

export default TEMPLATE;
