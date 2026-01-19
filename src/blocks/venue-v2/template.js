/**
 * Default template for the venue-v2 block.
 */

const TEMPLATE = [
	[
		'core/post-title',
		{
			style: {
				spacing: {
					margin: {
						bottom: 'var:preset|spacing|30',
					},
				},
			},
		},
	],
	[
		'core/group',
		{
			layout: {
				type: 'constrained',
			},
		},
		[
			[
				'core/group',
				{
					className: 'gatherpress--has-venue-address',
					style: {
						spacing: {
							blockGap: 'var:preset|spacing|20',
						},
					},
					layout: {
						type: 'flex',
						flexWrap: 'nowrap',
					},
				},
				[
					[ 'gatherpress/icon', { icon: 'location' } ],
					[
						'gatherpress/venue-detail',
						{
							placeholder: 'Venue address…',
							fieldType: 'address',
						},
					],
				],
			],
		],
	],
	[
		'core/group',
		{
			style: {
				spacing: {
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
			[
				'core/group',
				{
					className: 'gatherpress--has-venue-phone',
					style: {
						spacing: {
							blockGap: 'var:preset|spacing|20',
						},
					},
					layout: {
						type: 'flex',
						flexWrap: 'nowrap',
					},
				},
				[
					[ 'gatherpress/icon', { icon: 'phone' } ],
					[
						'gatherpress/venue-detail',
						{
							placeholder: 'Venue phone…',
							fieldType: 'phone',
						},
					],
				],
			],
			[
				'core/group',
				{
					className: 'gatherpress--has-venue-website',
					style: {
						spacing: {
							blockGap: 'var:preset|spacing|20',
						},
					},
					layout: {
						type: 'flex',
						flexWrap: 'nowrap',
					},
				},
				[
					[ 'gatherpress/icon', { icon: 'admin-site-alt3' } ],
					[
						'gatherpress/venue-detail',
						{
							placeholder: 'Website URL…',
							fieldType: 'url',
						},
					],
				],
			],
		],
	],
	[ 'gatherpress/venue-map' ],
];

export default TEMPLATE;
