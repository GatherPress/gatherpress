/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { QUERY_NO_RESULTS_VARIATION } from '../../../query-no-results';
import { QUERY_PAGINATION_VARIATION } from '../../../query-pagination';

/**
 * Event Card with RSVP starter template for the Event Query Loop.
 *
 * Mirrors the layout that previously lived inline on the variation as
 * temporary `innerBlocks` (pre-#1124): featured image, date, title, venue,
 * online event link, RSVP avatar grid + count, and the RSVP button. Picking
 * this pattern from the chooser produces the same shape users have today.
 */
const EVENT_CARD_WITH_RSVP_TEMPLATE = [
	[
		'core/post-template',
		{
			metadata: {
				name: __( 'Events Template', 'gatherpress' ),
			},
			style: {
				spacing: {
					blockGap: 'var:preset|spacing|50',
				},
			},
		},
		[
			[
				'core/media-text',
				{
					mediaPosition: 'right',
					mediaType: 'image',
					mediaWidth: 40,
					imageFill: false,
					useFeaturedImage: true,
					style: {
						spacing: {
							margin: {
								bottom: 'var:preset|spacing|30',
							},
						},
					},
				},
				[
					[
						'gatherpress/event-date',
						{
							displayType: 'start',
							startDateFormat: ' D, M j, Y, g:i a ',
							style: {
								typography: {
									textTransform: 'uppercase',
								},
							},
							fontSize: 'medium',
						},
					],
					[ 'core/post-title', { isLink: true } ],
					[
						'gatherpress/venue',
						{},
						[
							[
								'core/group',
								{
									className:
										'gatherpress--has-venue-address',
									style: {
										spacing: {
											blockGap:
												'var:preset|spacing|20',
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
										'gatherpress/icon',
										{ icon: 'location' },
									],
									[
										'core/post-title',
										{
											isLink: true,
											fontSize: 'medium',
										},
									],
								],
							],
						],
					],
					[
						'gatherpress/online-event',
						{},
						[
							[
								'core/group',
								{
									style: {
										spacing: {
											blockGap:
												'var:preset|spacing|20',
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
										'gatherpress/icon',
										{ icon: 'video-alt2' },
									],
									[
										'gatherpress/online-event-link',
										{
											linkText: `<span class="gatherpress-tooltip" data-gatherpress-tooltip="${ __(
												'Link available for attendees only.',
												'gatherpress'
											) }">${ __(
												'Online event',
												'gatherpress'
											) }</span>`,
											fontSize: 'medium',
										},
									],
								],
							],
						],
					],
				],
			],
			[
				'core/columns',
				{},
				[
					[
						'core/column',
						{ width: '40%' },
						[
							[
								'core/group',
								{
									style: {
										spacing: {
											blockGap:
												'var:preset|spacing|20',
										},
									},
									layout: {
										type: 'grid',
										minimumColumnWidth: '4rem',
										columnCount: null,
									},
								},
								[
									[
										'gatherpress/rsvp-response',
										{
											rsvpLimitEnabled: true,
											rsvpLimit: 3,
										},
										[
											[
												'core/group',
												{
													className:
														'gatherpress--rsvp-responses',
													style: {
														spacing: {
															blockGap: '0',
														},
													},
													layout: {
														type: 'grid',
														columnCount: 3,
														minimumColumnWidth: null,
													},
												},
												[
													[
														'gatherpress/rsvp-template',
														{},
														[
															[
																'core/avatar',
																{
																	size: 48,
																},
															],
														],
													],
												],
											],
										],
									],
								],
							],
							[ 'gatherpress/rsvp-count' ],
						],
					],
					[
						'core/column',
						{ width: '60%' },
						[
							[
								'gatherpress/rsvp',
								{
									patternPicked: true,
									layout: {
										type: 'flex',
										justifyContent: 'right',
										flexWrap: 'wrap',
									},
								},
							],
						],
					],
				],
			],
		],
	],
	QUERY_PAGINATION_VARIATION,
	QUERY_NO_RESULTS_VARIATION,
];

export default EVENT_CARD_WITH_RSVP_TEMPLATE;
