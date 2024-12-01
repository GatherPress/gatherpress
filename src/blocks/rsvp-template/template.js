const TEMPLATE = [
	[
		'core/group',
		{},
		[
			[
				'core/avatar',
				{
					isLink: true,
					align: 'center',
				},
			],
			[
				'core/comment-author-name',
				{
					textAlign: 'center',
					style: {
						spacing: {
							margin: {
								top: '0',
								bottom: '0',
							},
						},
					},
					fontSize: 'medium',
				},
			],
		],
	],
];

export default TEMPLATE;
