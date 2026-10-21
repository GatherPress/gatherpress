/**
 * GatherPress Calendar Bound Block Variations
 *
 * Registers variations of core/heading and core/paragraph bound to
 * calendar block-bindings sources.
 *
 * @package
 * @since TBD
 */

import { registerBlockVariation } from '@wordpress/blocks';
import { __, _x } from '@wordpress/i18n';

/**
 * Register variation for core/paragraph bound to gatherpress/calendar-day.
 */
registerBlockVariation( 'core/paragraph', {
	name: 'gatherpress-calendar-day-number',
	title: _x(
		'Calendar Day Number',
		'Block variation name',
		'gatherpress',
	),
	description: _x(
		'Displays the day number inside a calendar day cell.',
		'Block variation description',
		'gatherpress',
	),
	category: 'gatherpress',
	keywords: [
		__( 'day', 'gatherpress' ),
		__( 'number', 'gatherpress' ),
		__( 'calendar', 'gatherpress' ),
	],
	attributes: {
		className: 'gatherpress-calendar__day-number',
		fontSize: 'small',
		placeholder: 'DD',
		content: 'DD',
		style: {
			spacing: {
				margin: {
					top: '0',
					bottom: '0',
					left: '0',
					right: '0',
				},
			},
		},
		metadata: {
			bindings: {
				content: {
					source: 'gatherpress/calendar-day',
				},
			},
			name: _x(
				'Day Number',
				'Block variation name',
				'gatherpress',
			),
		},
	},
	isActive: ( blockAttributes, variationAttributes ) =>
		Boolean(
			blockAttributes?.className
				?.split( ' ' )
				.includes( variationAttributes.className ),
		),
	scope: [ 'inserter' ],
} );
