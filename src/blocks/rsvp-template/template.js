const TEMPLATE = [
	[
		'core/group',
		{
			style: {
				border: {
					radius: '10px',
					width: '1px',
				},
				spacing: {
					padding: {
						top: 'var:preset|spacing|20',
						bottom: 'var:preset|spacing|20',
					},
				},
			},
		},
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
