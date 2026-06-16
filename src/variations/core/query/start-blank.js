/**
 * WordPress dependencies
 */
import { registerBlockVariation } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { NAME } from './name';
import { QUERY_ATTRIBUTES, VARIATION_ATTRIBUTES } from './constants';

import {
	eventDateTitle,
	eventDateTitleExcerpt,
	imageEventDateTitle,
	eventDateTitleVenueMap,
} from './icons';
import { QUERY_NO_RESULTS_VARIATION } from '../query-no-results';
import { QUERY_PAGINATION_VARIATION } from '../query-pagination';

const { attributes, ...VARIATION_ATTRIBUTES_WITHOUT_ATTRIBUTES } = VARIATION_ATTRIBUTES;

const START_BLANK_QUERY_ATTRIBUTES = {
	namespace: [ NAME ],
	query: {
		...QUERY_ATTRIBUTES.query,
	},
};

const START_BLANK_VARIATION_ATTRIBUTES = {
	...VARIATION_ATTRIBUTES_WITHOUT_ATTRIBUTES,
	scope: [ 'block' ],
	attributes: {
		...attributes,
		...START_BLANK_QUERY_ATTRIBUTES,
	},
};

/**
 * "Start blank" pattern "Event Date & Title"
 *
 * To connect this variation to the "Event Query" "Start blank" patterns,
 * it defines the namespace attribute as array,
 * that contains the name property of the visible variation this one wants to connect to.
 */
registerBlockVariation( 'core/query', {
	...START_BLANK_VARIATION_ATTRIBUTES,
	name: NAME + '-start-blank-1',
	title: __( 'Event Date & Title', 'gatherpress' ),
	icon: eventDateTitle,
	/*
	 * Intentionally with `innerBlocks` here, because this variation powers the "Start blank" patterns.
	 *
	 * @see https://developer.wordpress.org/block-editor/how-to-guides/block-tutorial/extending-the-query-loop-block/#customize-your-variation-layout
	 */
	innerBlocks: [
		{
			name: 'core/post-template',
			attributes: {
				metadata: {
					name: __( 'Events Template', 'gatherpress' ),
				},
			},
			innerBlocks: [
				[ 'gatherpress/event-date' ],
				[ 'core/post-title' ],
			],
		},
		QUERY_PAGINATION_VARIATION,
		QUERY_NO_RESULTS_VARIATION,
	],
} );

/**
 * "Start blank" pattern "Event Date, Title & Excerpt"
 *
 * To connect this variation to the "Event Query" "Start blank" patterns,
 * it defines the namespace attribute as array,
 * that contains the name property of the visible variation this one wants to connect to.
 */
registerBlockVariation( 'core/query', {
	...START_BLANK_VARIATION_ATTRIBUTES,
	name: NAME + '-start-blank-2',
	title: __( 'Event Date, Title & Excerpt', 'gatherpress' ),
	icon: eventDateTitleExcerpt,
	/*
	 * Intentionally with `innerBlocks` here, because this variation powers the "Start blank" patterns.
	 *
	 * @see https://developer.wordpress.org/block-editor/how-to-guides/block-tutorial/extending-the-query-loop-block/#customize-your-variation-layout
	 */
	innerBlocks: [
		{
			name: 'core/post-template',
			attributes: {
				metadata: {
					name: __( 'Events Template', 'gatherpress' ),
				},
			},
			innerBlocks: [
				[ 'gatherpress/event-date' ],
				[ 'core/post-title' ],
				[ 'core/post-excerpt' ],
			],
		},
		QUERY_PAGINATION_VARIATION,
		QUERY_NO_RESULTS_VARIATION,
	],
} );

/**
 * "Start blank" pattern "Image, Event Date & Title"
 *
 * To connect this variation to the "Event Query" "Start blank" patterns,
 * it defines the namespace attribute as array,
 * that contains the name property of the visible variation this one wants to connect to.
 */
registerBlockVariation( 'core/query', {
	...START_BLANK_VARIATION_ATTRIBUTES,
	name: NAME + '-start-blank-3',
	title: __( 'Image, Event Date & Title', 'gatherpress' ),
	icon: imageEventDateTitle,
	/*
	 * Intentionally with `innerBlocks` here, because this variation powers the "Start blank" patterns.
	 *
	 * @see https://developer.wordpress.org/block-editor/how-to-guides/block-tutorial/extending-the-query-loop-block/#customize-your-variation-layout
	 */
	innerBlocks: [
		{
			name: 'core/post-template',
			attributes: {
				metadata: {
					name: __( 'Events Template', 'gatherpress' ),
				},
			},
			innerBlocks: [
				[ 'core/media-text', { useFeaturedImage: true }, [
					[ 'gatherpress/event-date' ],
					[ 'core/post-title' ],
				] ],
			],
		},
		QUERY_PAGINATION_VARIATION,
		QUERY_NO_RESULTS_VARIATION,
	],
} );

/**
 * "Start blank" pattern "Event Date, Title, Venue & Map"
 *
 * To connect this variation to the "Event Query" "Start blank" patterns,
 * it defines the namespace attribute as array,
 * that contains the name property of the visible variation this one wants to connect to.
 */
registerBlockVariation( 'core/query', {
	...START_BLANK_VARIATION_ATTRIBUTES,
	name: NAME + '-start-blank-4',
	title: __( 'Event Date, Title, Venue & Map', 'gatherpress' ),
	icon: eventDateTitleVenueMap,
	/*
	 * Intentionally with `innerBlocks` here, because this variation powers the "Start blank" patterns.
	 *
	 * @see https://developer.wordpress.org/block-editor/how-to-guides/block-tutorial/extending-the-query-loop-block/#customize-your-variation-layout
	 */
	innerBlocks: [
		{
			name: 'core/post-template',
			attributes: {
				metadata: {
					name: __( 'Events Template', 'gatherpress' ),
				},
			},
			innerBlocks: [
				[ 'gatherpress/event-date' ],
				[ 'core/post-title' ],
				[ 'gatherpress/venue', { patternPicked: true } ],
			],
		},
		QUERY_PAGINATION_VARIATION,
		QUERY_NO_RESULTS_VARIATION,
	],
} );
