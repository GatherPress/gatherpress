/**
 * WordPress dependencies
 */
import { registerBlockVariation } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import './controls';
import { QUERY_NO_RESULTS_VARIATION } from '../query-no-results';
import { QUERY_PAGINATION_VARIATION } from '../query-pagination';

export const NAME = 'gatherpress-event-query';

const QUERY_ATTRIBUTES = {
	namespace: NAME,
	query: {
		perPage: 5,
		pages: 0,
		offset: 0,
		postType: 'gatherpress_event',
		gatherpress_event_query: 'upcoming',
		include_unfinished: 1,
		order: 'asc',
		orderBy: 'datetime',
		inherit: false,
	},
};

const VARIATION_ATTRIBUTES = {
	category: 'gatherpress',
	keywords: [ __( 'Events', 'gatherpress' ), __( 'Dates', 'gatherpress' ) ],
	isActive: [ 'namespace', 'query.postType' ],
	attributes: {
		...QUERY_ATTRIBUTES,
		className: 'gatherpress-event-query',
	},
	// Disabling irrelevant or unsupported query controls
	// @see https://developer.wordpress.org/block-editor/how-to-guides/block-tutorial/extending-the-query-loop-block/#disabling-irrelevant-or-unsupported-query-controls
	allowedControls: [ 'inherit', 'taxQuery', 'author', 'search' ],
	scope: [ 'block' ],
};

/**
 * Docs about the Query block.
 *
 * General information on how to modify the query loop block, that's worth reading and learning:
 *
 * @see https://developer.wordpress.org/block-editor/how-to-guides/block-tutorial/extending-the-query-loop-block/#extending-the-query
 * @see https://wpfieldwork.com/modify-query-loop-block-to-filter-by-custom-field/
 * @see https://developer.wordpress.org/block-editor/reference-guides/block-api/block-variations/
 * @see https://jeffreycarandang.com/restrict-wordpress-gutenberg-block-settings-based-on-post-type-user-roles-or-block-context/
 */

/**
 * This is the main query-block variation to list events exclusively.
 * A user can pick the block directly from the inserter or the left sidebar.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/block-api/block-variations/
 */
registerBlockVariation( 'core/query', {
	...VARIATION_ATTRIBUTES,
	name: NAME,
	title: __( 'Event Query', 'gatherpress' ),
	description: __( 'Create event queries', 'gatherpress' ),
	scope: [ 'inserter', 'transform' ],
	/*
	 * Having innerBlocks in THIS (visible) variation, essentially
	 * skips the setup phase of the Query Loop block with suggested starter patterns
	 * and the block is inserted with these inner blocks as its starting content.
	 *
	 * This is not what GatherPress wanted, so it is disabled.
	 *
	 * @see https://developer.wordpress.org/block-editor/how-to-guides/block-tutorial/extending-the-query-loop-block/#customize-your-variation-layout
	 *
	 * As long as GatherPress does not have any valid Starter Patterns, this 'innerBlocks' section is temporarily re-enabled
	 * to prevent the default 'core/query' block starter patterns to appear.
	 * As soon as #1124 is done, this part should be disabled again.
	 *
	 * @todo Add 'Start blank' patterns for the gatherpress query loop variation. https://github.com/GatherPress/gatherpress/issues/1124
	 */
	innerBlocks: [
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
				// Media & Text - Event details (left) and featured image (right).
				// Image stacks on top on mobile.
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
							'gatherpress/venue-v2',
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
							'gatherpress/online-event-v2',
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
				// Second row of columns (40/60) - Avatars/count and RSVP button.
				[
					'core/columns',
					{},
					[
						// Left column (40%) - Avatars and RSVP count.
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
						// Right column (60%) - RSVP button.
						[
							'core/column',
							{ width: '60%' },
							[
								[
									'gatherpress/rsvp',
									{
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
	],

	example: {
		attributes: {
			...QUERY_ATTRIBUTES,
		},
		innerBlocks: [
			{
				name: 'core/post-template',
				attributes: {},
				innerBlocks: [
					{
						name: 'core/post-title',
					},
				],
			},
		],
	},
} );
