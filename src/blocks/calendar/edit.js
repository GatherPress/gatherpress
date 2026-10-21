/**
 * GatherPress Calendar Block Editor Component
 *
 * @package
 * @since TBD
 */

import { __ } from '@wordpress/i18n';
import {
	useBlockProps,
	useInnerBlocksProps,
	InspectorControls,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalGetGapCSSValue as getGapCSSValue,
} from '@wordpress/block-editor';
import { Placeholder, PanelBody, ToggleControl } from '@wordpress/components';
import { useState, useMemo } from '@wordpress/element';

import { CALENDAR_TEMPLATE } from './constants';
import {
	calculateDateRange,
	calculateDateQuery,
	formatHeading,
} from './utils/date-utils';
import { getDatetimeSeparator } from './utils/source-utils';
import {
	generateCalendar,
	getDefaultActiveDate,
	getColumnsCount,
	getStartOfWeek,
} from './utils/calendar-utils';
import { useCalendarSync } from './hooks/use-calendar-sync';
import { useCalendarData } from './hooks/use-calendar-data';
import { useCalendarDayTemplate } from './hooks/use-calendar-day-template';
import { useDateRangeSourceSync } from './hooks/use-date-range-source-sync';
import { useEventPostTypes } from './hooks/use-event-post-types';
import { DateControls } from './panels/date-controls';
import { CalendarTable } from '../../components/CalendarTable';

/**
 * Edit Component
 *
 * Main editor component for the GatherPress Calendar block.
 *
 * @since TBD
 *
 * @param {Object}   props               Component props.
 * @param {Object}   props.attributes    Block attributes.
 * @param {Function} props.setAttributes Function to update block attributes.
 * @param {Object}   props.context       Context from parent blocks.
 * @param {string}   props.clientId      This block's client ID.
 *
 * @return {Element} React element rendered in the editor.
 */
export default function Edit( {
	attributes,
	setAttributes,
	context,
	clientId,
} ) {
	const {
		viewType = 'month',
		unitCount = 1,
		selectedDate = '',
		dateModifier = 0,
		showWeekdays = true,
		showWeekends = true,
		showScheduled = false,
		dateRangeSource = 'default',
		postId = 0,
		sourcePostType = '',
		enableTimeGrid = false,
	} = attributes;

	const { query } = context;

	const [ activeDate, setActiveDate ] = useState( '' );

	const startOfWeek = getStartOfWeek();

	// Coordinate dateRangeSource resolution, attribute synchronization, and presets.
	const {
		sourcePostDates,
		hasPostDates,
		effectiveUnitCount,
		effectiveSelectedDate,
		effectiveDateModifier,
		hasCurrentSupport,
	} = useDateRangeSourceSync( {
		attributes,
		setAttributes,
		context,
		startOfWeek,
	} );

	// Synchronize parent Query block name, Heading name, and pagination labels.
	useCalendarSync( {
		clientId,
		viewType,
		unitCount: effectiveUnitCount,
	} );

	// Retrieve the real week/day template blocks so previews can clone their
	// actual inner content (Day Number, Post Title, Event Date, etc.) and
	// mirror their own color/border styling (e.g. a custom background).
	const { dayInnerBlocks, dayBlockAttributes, weekBlockAttributes } =
		useCalendarDayTemplate( clientId );

	const dayStartHour = weekBlockAttributes?.dayStartHour ?? 8;
	const dayEndHour = weekBlockAttributes?.dayEndHour ?? 20;
	const slotHeight = dayBlockAttributes?.slotHeight ?? 48;

	// Verify if time grid is actively valid and enabled.
	const isDatetimeOrdered =
		'datetime' === ( query?.orderBy || query?.orderby );
	const isTimeGridActive =
		( 'day' === viewType || 'week' === viewType ) &&
		isDatetimeOrdered &&
		Boolean( enableTimeGrid );

	const timeGridConfig = useMemo( () => {
		if ( ! isTimeGridActive ) {
			return { enabled: false, slots: [] };
		}

		// Like the PHP side: a numeric hour stays, 0 included; anything else uses the default.
		const toHour = ( value, fallback ) =>
			'' !== value && null !== value && Number.isFinite( Number( value ) )
				? Number( value )
				: fallback;
		const start = Math.max( 0, Math.min( 23, toHour( dayStartHour, 8 ) ) );
		const end = Math.max( start + 1, Math.min( 24, toHour( dayEndHour, 20 ) ) );
		const slotHours = 1;
		const quarterMins = 15;
		const totalQuarters = Math.ceil(
			( ( end - start ) * 60 ) / quarterMins,
		);

		const slots = [];
		for ( let h = start; h < end; h += slotHours ) {
			slots.push( `${ String( h ).padStart( 2, '0' ) }:00` );
		}

		return {
			enabled: true,
			startHour: start,
			endHour: end,
			slotHours,
			quarterMins,
			totalQuarters,
			slots,
			slotHeight,
		};
	}, [ isTimeGridActive, dayStartHour, dayEndHour, slotHeight ] );

	const dateRange = useMemo(
		() =>
			calculateDateRange(
				{
					viewType,
					unitCount: effectiveUnitCount,
					selectedDate: effectiveSelectedDate,
					dateModifier: effectiveDateModifier,
					showWeekends,
				},
				startOfWeek,
			),
		[
			viewType,
			effectiveUnitCount,
			effectiveSelectedDate,
			effectiveDateModifier,
			showWeekends,
			startOfWeek,
		],
	);

	const { posts } = useCalendarData(
		query,
		useMemo(
			() =>
				calculateDateQuery(
					{
						viewType,
						unitCount: effectiveUnitCount,
						selectedDate: effectiveSelectedDate,
						dateModifier: effectiveDateModifier,
						showWeekends,
					},
					startOfWeek,
				),
			[
				viewType,
				effectiveUnitCount,
				effectiveSelectedDate,
				effectiveDateModifier,
				showWeekends,
				startOfWeek,
			],
		),
		showScheduled,
	);

	// Reactive, so the grid is built again when the post types finish loading.
	const eventPostTypes = useEventPostTypes();
	const eventPostTypeSlugs = useMemo(
		() => eventPostTypes.map( ( postType ) => postType.slug ),
		[ eventPostTypes ],
	);

	const calendar = useMemo(
		() =>
			generateCalendar(
				posts,
				startOfWeek,
				dateRange,
				showWeekends,
				timeGridConfig,
				eventPostTypeSlugs,
			),
		[
			posts,
			startOfWeek,
			dateRange,
			showWeekends,
			timeGridConfig,
			eventPostTypeSlugs,
		],
	);

	// Table captions, as on the front end: the name of each month grid, or
	// the heading of the week or day range.
	const captions = useMemo( () => {
		const start = dateRange.startDateObj || new Date( dateRange.startDate );
		const end = dateRange.endDateObj || new Date( dateRange.endDate );
		if ( 'month' !== viewType ) {
			return [ formatHeading( viewType, start, end ) ];
		}
		return ( calendar.units || [ calendar ] ).map( ( unit, index ) => {
			// The 15th: a timezone offset cannot move it to another month.
			const mid = new Date(
				start.getFullYear(),
				start.getMonth() + index,
				15,
			);
			return formatHeading( 'month', mid, mid );
		} );
	}, [ viewType, dateRange, calendar ] );

	// Resolve which day is currently "live"/editable: keep the previously
	// active date if it still exists in this month, otherwise fall back to
	// today (or the 1st) so the preview always has a live cell to show.
	const resolvedActiveDate = useMemo( () => {
		const days = calendar.weeks.flat();
		if ( days.some( ( day ) => day.date === activeDate ) ) {
			return activeDate;
		}
		return getDefaultActiveDate( calendar );
	}, [ calendar, activeDate ] );

	const blockClasses = [
		`is-view-${ viewType }`,
		1 < dateRange.unitCount ? 'has-multiple-units' : '',
		isTimeGridActive ? 'has-time-grid' : '',
	]
		.filter( Boolean )
		.join( ' ' );

	const blockProps = useBlockProps( {
		className: blockClasses,
		style: {
			'--gatherpress-calendar-units': dateRange.unitCount,
		},
	} );

	// The real, live-editable week+day+content InnerBlocks tree. Rendered
	// inside <tbody> at whichever week row contains resolvedActiveDate;
	// every other week is a read-only preview (see CalendarTable).
	const { children: liveWeekChildren, ...tbodyProps } = useInnerBlocksProps(
		{
			className: 'gatherpress-calendar__weeks',
		},
		{
			allowedBlocks: [ 'gatherpress/calendar-week' ],
			template: CALENDAR_TEMPLATE,
			templateLock: false, // TODO: Consider 'contentOnly', which is nice here.
			renderAppender: false,
		},
	);

	const blockGap = attributes.style?.spacing?.blockGap;
	const tableGap = getGapCSSValue( blockGap );
	// Day cells subtract the column gaps to stay square (calendar-day/style.scss),
	// so pass one length: the 'left' side of a split gap, and '0px' for '0'.
	const columnGap = getGapCSSValue(
		blockGap && 'object' === typeof blockGap
			? ( blockGap.left ?? '1px' )
			: blockGap,
	);

	const tableColumnsCount = getColumnsCount(
		viewType,
		showWeekends,
		dateRange.unitCount,
		isTimeGridActive,
	);

	const tableStyle = {
		gap: tableGap,
		...( columnGap
			? {
				'--gatherpress-calendar-column-gap':
						'0' === columnGap ? '0px' : columnGap,
			}
			: {} ),
		'--gatherpress-calendar-columns': tableColumnsCount,
		...( isTimeGridActive
			? {
				'--gatherpress-calendar-slot-height': `${ slotHeight }px`,
				'--gatherpress-total-quarter-slots':
						timeGridConfig.totalQuarters,
			}
			: {} ),
	};

	const weekContext = useMemo(
		() => ( {
			'gatherpress/viewType': viewType,
			'gatherpress/year': dateRange.year,
			'gatherpress/month': dateRange.month,
			'gatherpress/startDate': dateRange.startDate,
			'gatherpress/endDate': dateRange.endDate,
			'gatherpress/enableTimeGrid': isTimeGridActive,
			'gatherpress/timeGridConfig': timeGridConfig,
			query,
		} ),
		[ viewType, dateRange, isTimeGridActive, timeGridConfig, query ],
	);

	if ( ! query ) {
		return (
			<div { ...blockProps }>
				<Placeholder
					icon="calendar-alt"
					label={ __(
						'GatherPress Calendar',
						'gatherpress',
					) }
					instructions={ __(
						'This block must be used inside a Query Loop block.',
						'gatherpress',
					) }
				/>
			</div>
		);
	}

	// A picked date or month replaces the offset, which the controls hide
	// while a date is set. The month picker shows the offset month, so the
	// calendar stays on the month it showed. A reset also clears the offset.
	const handleDateChange = ( value ) => {
		setAttributes( { selectedDate: value, dateModifier: 0 } );
	};

	const handleModifierChange = ( value ) => {
		const numValue = '' === value ? 0 : parseInt( value, 10 );
		setAttributes( {
			dateModifier: isNaN( numValue ) ? 0 : numValue,
		} );
	};

	const handleUnitCountChange = ( value ) => {
		const count = parseInt( value, 10 );
		setAttributes( {
			unitCount: isNaN( count ) || 1 > count ? 1 : count,
		} );
	};

	const handleSourcePostTypeChange = ( newType ) => {
		setAttributes( {
			sourcePostType: newType,
			postId: 0,
		} );
	};

	const separator = getDatetimeSeparator();

	return (
		<>
			<InspectorControls>
				<PanelBody
					title={ __( 'Calendar Settings', 'gatherpress' ) }
				>
					<DateControls
						clientId={ clientId }
						context={ context }
						viewType={ viewType }
						onViewTypeChange={ ( val ) =>
							setAttributes( { viewType: val } )
						}
						unitCount={ unitCount }
						onUnitCountChange={ handleUnitCountChange }
						selectedDate={
							hasPostDates
								? `${ dateRange.startDate } ${ separator } ${ dateRange.endDate }`
								: selectedDate
						}
						dateModifier={ dateModifier }
						onDateChange={ handleDateChange }
						onModifierChange={ handleModifierChange }
						dateRangeSource={ dateRangeSource }
						onSourceChange={ ( val ) =>
							setAttributes( { dateRangeSource: val } )
						}
						postId={ postId }
						onPostIdChange={ ( val ) =>
							setAttributes( { postId: val } )
						}
						sourcePostType={ sourcePostType }
						onPostTypeChange={ handleSourcePostTypeChange }
						postTitle={ sourcePostDates.postTitle }
						hasPostDates={ hasPostDates }
						hasCurrentSupport={ hasCurrentSupport }
						enableTimeGrid={ enableTimeGrid }
						onTimeGridChange={ ( val ) =>
							setAttributes( { enableTimeGrid: val } )
						}
					/>

					<ToggleControl
						label={ __( 'Show Weekdays', 'gatherpress' ) }
						checked={ showWeekdays }
						onChange={ ( value ) =>
							setAttributes( { showWeekdays: value } )
						}
						help={ __(
							'Display the days of the week inside the calendar header.',
							'gatherpress',
						) }
					/>
					{ 'day' !== viewType && (
						<ToggleControl
							label={ __(
								'Show Weekends',
								'gatherpress',
							) }
							checked={ showWeekends }
							onChange={ ( value ) =>
								setAttributes( { showWeekends: value } )
							}
							help={ __(
								'Display weekend days in the calendar grid.',
								'gatherpress',
							) }
						/>
					) }
					<ToggleControl
						label={ __(
							'Show Scheduled Posts',
							'gatherpress',
						) }
						checked={ showScheduled }
						onChange={ ( value ) =>
							setAttributes( { showScheduled: value } )
						}
						help={ __(
							'Include posts that are scheduled to publish later. All visitors can see what the calendar shows for them, such as title and excerpt, but their links only work once they are published.',
							'gatherpress',
						) }
					/>
				</PanelBody>
			</InspectorControls>
			<div { ...blockProps }>
				<CalendarTable
					calendar={ calendar }
					captions={ captions }
					showWeekdays={ showWeekdays }
					style={ tableStyle }
					activeDate={ resolvedActiveDate }
					setActiveDate={ setActiveDate }
					weekContext={ weekContext }
					liveWeekChildren={ liveWeekChildren }
					dayInnerBlocks={ dayInnerBlocks }
					weekBlockAttributes={ weekBlockAttributes }
					dayBlockAttributes={ dayBlockAttributes }
					tbodyProps={ tbodyProps }
					timeGridConfig={ timeGridConfig }
				/>
			</div>
		</>
	);
}
