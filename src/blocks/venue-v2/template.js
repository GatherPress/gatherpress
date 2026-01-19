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
							metadata: {
								bindings: {
									content: {
										source: 'core/post-meta',
										args: {
											key: 'gatherpress_venue_address',
										},
									},
								},
							},
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
							metadata: {
								bindings: {
									content: {
										source: 'core/post-meta',
										args: {
											key: 'gatherpress_venue_phone',
										},
									},
								},
							},
						},
					],
				],
			],
			[
				'core/group',
				{
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
							metadata: {
								bindings: {
									content: {
										source: 'core/post-meta',
										args: {
											key: 'gatherpress_venue_website',
										},
									},
								},
							},
						},
					],
				],
			],
		],
	],
	[ 'gatherpress/venue-map' ],
];

export default TEMPLATE;
