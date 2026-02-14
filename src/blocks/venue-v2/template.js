/**
 * Default template for the venue-v2 block.
 */

// Template with post-title (for events).
export const TEMPLATE_WITH_TITLE = [
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
						top: '0',
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

// Template without post-title (for venues).
export const TEMPLATE_WITHOUT_TITLE = [
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
						top: '0',
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

// Template for online events (icon + online-event-v2 block).
export const TEMPLATE_ONLINE_EVENT = [
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
			[ 'gatherpress/icon', { icon: 'video-alt2' } ],
			[ 'gatherpress/online-event-v2' ],
		],
	],
];

// Default export is with title for backward compatibility.
export default TEMPLATE_WITH_TITLE;
