/**
 * Default template for the venue block.
 */

// Post title block (for events).
const POST_TITLE = [
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
];

// Base venue details template shared between both variants.
const VENUE_DETAILS = [
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
				justifyContent: 'left',
			},
		},
		[
			[
				'core/icon',
				{
					icon: 'core/map-marker',
					style: { dimensions: { width: '24px' } },
				},
			],
			[
				'gatherpress/venue-detail',
				{
					placeholder: 'Venue address…',
					fieldType: 'address',
				},
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
				justifyContent: 'left',
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
					[
						'core/icon',
						{
							icon: 'core/mobile',
							style: { dimensions: { width: '24px' } },
						},
					],
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
					[
						'core/icon',
						{
							icon: 'core/external',
							style: { dimensions: { width: '24px' } },
						},
					],
					[
						'gatherpress/venue-detail',
						{
							placeholder: 'Venue website URL…',
							fieldType: 'url',
						},
					],
				],
			],
		],
	],
	[ 'gatherpress/venue-map' ],
];

// Template variants for the Venue Details with Map pattern. Both share the
// same address + phone + website + map block tree; the with-title variant
// prepends a post title (used in event posts) while the without-title variant
// drops it (used in venue posts where the host's title already names the
// venue).
export const TEMPLATE_WITH_TITLE = [ POST_TITLE, ...VENUE_DETAILS ];
export const TEMPLATE_WITHOUT_TITLE = VENUE_DETAILS;
