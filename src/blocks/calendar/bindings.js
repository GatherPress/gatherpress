/**
 * Calendar Block Bindings and Editor Filters
 *
 * @package
 * @since TBD
 */

import { registerBlockBindingsSource } from '@wordpress/blocks';
import { addFilter } from '@wordpress/hooks';
import { __ } from '@wordpress/i18n';
import domReady from '@wordpress/dom-ready';
import { dateI18n } from '@wordpress/date';
import { InspectorControls } from '@wordpress/block-editor';
import { createHigherOrderComponent } from '@wordpress/compose';
import { PanelBody, SelectControl } from '@wordpress/components';

import {
	calculateDateRange,
	calculatePostSpanUnits,
	formatHeading,
} from './utils/date-utils';
import { getStartOfWeek } from './utils/calendar-utils';
import { findBlockByName } from './utils/block-sync-utils';
import {
	getDefaultEventPostType,
	resolveSourceEventDates,
	isPostDateSource,
	selectEventPostTypes,
} from './utils/source-utils';

domReady( () => {
	if ( 'function' !== typeof registerBlockBindingsSource ) {
		return;
	}

	/**
	 * Callback to get heading content for bound heading blocks in the editor.
	 *
	 * Subscribes to the calendar block's settings and current post dates,
	 * formatting the active date range.
	 *
	 * @param {Object}   root0          Parameters object.
	 * @param {Function} root0.select   Block editor select function.
	 * @param {string}   root0.clientId Current block client ID.
	 * @param {Object}   root0.context  Block context: the post of a Query Loop item, or the edited post.
	 * @return {Object} Content object.
	 */
	const getCalendarHeadingValues = ( {
		select: registrySelect,
		clientId,
		context,
	} ) => {
		const { getBlockParentsByBlockName, getBlock, getBlocks } =
			registrySelect( 'core/block-editor' );

		let calendarBlock = null;
		const parentQueryIds = getBlockParentsByBlockName(
			clientId,
			'core/query',
		);

		// 1. Search inside the same parent Query block.
		if ( parentQueryIds && parentQueryIds.length ) {
			const parentQuery = getBlock( parentQueryIds.at( -1 ) );
			if ( parentQuery?.innerBlocks ) {
				calendarBlock = findBlockByName(
					parentQuery.innerBlocks,
					'gatherpress/calendar',
				);
			}
		}

		// 2. Fallback: search all blocks in the editor canvas.
		if ( ! calendarBlock ) {
			calendarBlock = findBlockByName(
				getBlocks(),
				'gatherpress/calendar',
			);
		}

		// Establish reactive subscription to calendar attributes.
		const liveCalendar = calendarBlock
			? getBlock( calendarBlock.clientId )
			: null;

		const {
			viewType = 'month',
			unitCount = 1,
			selectedDate = '',
			dateModifier = 0,
			showWeekends = true,
			dateRangeSource = 'default',
			postId = 0,
			sourcePostType = '',
		} = liveCalendar?.attributes || {};

		const startOfWeek = getStartOfWeek();

		// The same arguments as the calendar block (hooks/use-source-post-dates.js),
		// so the heading and the calendar show the same range.
		const sourceDates = resolveSourceEventDates( registrySelect, {
			dateRangeSource,
			postId,
			sourcePostType,
			contextPostId: context?.postId,
			contextPostType: context?.postType,
			defaultPostType: getDefaultEventPostType(
				selectEventPostTypes( registrySelect ),
			),
		} );

		let effectiveSelectedDate = selectedDate;
		let effectiveUnitCount = unitCount;

		if ( sourceDates.hasPost ) {
			effectiveSelectedDate = sourceDates.startDate;
			effectiveUnitCount = calculatePostSpanUnits(
				viewType,
				sourceDates.startDate,
				sourceDates.endDate,
				startOfWeek,
			);
		}

		const isPostAnchored = isPostDateSource( dateRangeSource );

		const range = calculateDateRange(
			{
				viewType,
				unitCount: effectiveUnitCount,
				selectedDate: effectiveSelectedDate,
				dateModifier: isPostAnchored ? 0 : dateModifier,
				showWeekends,
			},
			startOfWeek,
		);

		return {
			content: formatHeading(
				viewType,
				range.startDateObj,
				range.endDateObj,
			),
		};
	};

	registerBlockBindingsSource( {
		name: 'gatherpress/calendar-heading',
		label: __( 'Calendar Heading', 'gatherpress' ),
		usesContext: [ 'query', 'postId', 'postType' ],
		getValues: getCalendarHeadingValues,
	} );

	registerBlockBindingsSource( {
		name: 'gatherpress/calendar-day',
		label: __( 'Calendar Day Number', 'gatherpress' ),
		usesContext: [
			'gatherpress/dayNumber',
			'gatherpress/dayDate',
			'gatherpress/isEmpty',
		],
		getValues( { context, args } ) {
			if ( context?.[ 'gatherpress/isEmpty' ] ) {
				return { content: '' };
			}
			const dayDate = context?.[ 'gatherpress/dayDate' ];
			const format = args?.format || '';

			if ( dayDate && '' !== format ) {
				const dateObj = new Date( `${ dayDate }T12:00:00Z` );
				return { content: dateI18n( format, dateObj, 'UTC' ) };
			}

			const dayNumber = context?.[ 'gatherpress/dayNumber' ];
			return {
				content:
					null !== dayNumber && undefined !== dayNumber
						? String( dayNumber )
						: '',
			};
		},
	} );
} );

// InspectorControls for blocks bound to gatherpress/calendar-day:
const withDayNumberBindingControls = createHigherOrderComponent(
	( BlockEdit ) => {
		return ( props ) => {
			const { attributes, setAttributes } = props;
			const binding = attributes?.metadata?.bindings?.content;

			if ( 'gatherpress/calendar-day' !== binding?.source ) {
				return <BlockEdit { ...props } />;
			}

			const currentFormat = binding?.args?.format || '';

			const updateFormat = ( newFormat ) => {
				setAttributes( {
					metadata: {
						...attributes.metadata,
						bindings: {
							...attributes.metadata.bindings,
							content: {
								...binding,
								args: {
									...binding.args,
									format: newFormat,
								},
							},
						},
					},
				} );
			};

			return (
				<>
					<BlockEdit { ...props } />
					<InspectorControls>
						<PanelBody
							title={ __(
								'Day Number Settings',
								'gatherpress',
							) }
							initialOpen={ true }
						>
							<SelectControl
								label={ __(
									'Date Format',
									'gatherpress',
								) }
								value={ currentFormat }
								options={ [
									{
										label: __(
											'Default (1, 2, 3…)',
											'gatherpress',
										),
										value: 'j',
									},
									{
										label: __(
											'Leading zero (01, 02, 03…)',
											'gatherpress',
										),
										value: 'd',
									},
									{
										label: __(
											'Ordinal (1st, 2nd, 3rd…)',
											'gatherpress',
										),
										value: 'jS',
									},
									{
										label: __(
											'Dot suffix (1., 2., 3…)',
											'gatherpress',
										),
										value: 'j.',
									},
									{
										label: __(
											'Weekday and day (Mon 1…)',
											'gatherpress',
										),
										value: 'D j',
									},
								] }
								onChange={ updateFormat }
							/>
						</PanelBody>
					</InspectorControls>
				</>
			);
		};
	},
	'withDayNumberBindingControls',
);

addFilter(
	'editor.BlockEdit',
	'gatherpress/day-number-binding-controls',
	withDayNumberBindingControls,
);
