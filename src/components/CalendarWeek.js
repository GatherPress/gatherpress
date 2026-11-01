import { useMemo } from '@wordpress/element';
import { BlockContextProvider } from '@wordpress/block-editor';

import { CalendarWeekPreview } from './CalendarWeekPreview';

/**
 * Counts consecutive active (non-empty) columns remaining from current column.
 *
 * @param {Array<Object>} week      Week days array.
 * @param {number}        colIndex  Starting column index.
 * @param {number}        totalCols Total columns count.
 * @return {number} Active columns remaining.
 */
function countRemainingActiveColumns( week, colIndex, totalCols ) {
	let colsLeft = 0;
	for ( let c = colIndex; c < totalCols; c++ ) {
		if ( ! week[ c ]?.isEmpty ) {
			colsLeft++;
		}
	}
	return colsLeft;
}

/**
 * Annotates posts within a day with multi-day row boundaries and span metadata.
 *
 * @param {Object}        day       Day descriptor.
 * @param {number}        colIndex  Current column index.
 * @param {Array<Object>} week      Full week days array.
 * @param {number}        totalCols Total columns in week.
 * @return {Object} Day descriptor with annotated posts.
 */
function annotateDayPosts( day, colIndex, week, totalCols ) {
	if ( ! day.posts || ! day.posts.length ) {
		return day;
	}

	// First column of row OR first day after leading padding cells.
	const isFirstCol =
		0 === colIndex || Boolean( week[ colIndex - 1 ]?.isEmpty );

	// Last column of row OR last day before trailing padding cells.
	const isLastCol =
		colIndex === totalCols - 1 || Boolean( week[ colIndex + 1 ]?.isEmpty );

	const colsLeft = countRemainingActiveColumns( week, colIndex, totalCols );

	const annotatedPosts = day.posts.map( ( post ) => {
		const totalDays = post.totalDays ?? 1;
		const dayIndex = post.dayIndex ?? 1;

		if ( 1 >= totalDays ) {
			return { ...post, rowSpan: 1 };
		}

		const rowSpan = Math.max(
			1,
			Math.min( totalDays - dayIndex + 1, colsLeft ),
		);

		return {
			...post,
			isContinued: isFirstCol && 1 < dayIndex,
			isContinues: isLastCol && dayIndex < totalDays,
			rowSpan,
		};
	} );

	return {
		...day,
		posts: annotatedPosts,
	};
}

/**
 * CalendarWeek Component
 *
 * Renders an individual week row within the calendar table.
 *
 * One week is rendered live/editable using `liveWeekChildren`, while all
 * other weeks render as a read-only `CalendarWeekPreview`. The context value
 * is memoized per-week so inactive preview rows keep their context reference
 * and bail out of re-rendering during unrelated editor state updates.
 *
 * @since TBD
 *
 * @param {Object}   props                     Component props.
 * @param {Array}    props.week                Week day entries.
 * @param {number}   props.weekIndex           Index of this week in the unit.
 * @param {number}   props.weekNumber          ISO week number, 0 for none.
 * @param {string}   props.activeDate          Currently live/editable date.
 * @param {Function} props.setActiveDate       Setter for active date.
 * @param {Object}   props.weekContext         Base context shared by all weeks.
 * @param {Element}  props.liveWeekChildren    Live week inner blocks.
 * @param {Array}    props.dayInnerBlocks      Day template inner blocks.
 * @param {Object}   props.weekBlockAttributes Attributes of week block.
 * @param {Object}   props.dayBlockAttributes  Attributes of day block.
 * @param {Object}   props.timeGridConfig      Time grid configuration.
 * @param {number}   props.numRibbonSlots      Number of ribbon slots.
 *
 * @return {Element} Calendar week element.
 */
export function CalendarWeek( {
	week,
	weekIndex,
	weekNumber,
	activeDate,
	setActiveDate,
	weekContext,
	liveWeekChildren,
	dayInnerBlocks,
	weekBlockAttributes,
	dayBlockAttributes,
	timeGridConfig = {},
	numRibbonSlots = 0,
} ) {
	const isTimeGrid = Boolean( timeGridConfig?.enabled );

	// Annotate week days with row boundary wrap flags for multi-day events.
	const annotatedWeek = useMemo( () => {
		const totalCols = week.length;
		return week.map( ( day, colIndex ) =>
			annotateDayPosts( day, colIndex, week, totalCols ),
		);
	}, [ week ] );

	const isActiveWeek = annotatedWeek.some(
		( day ) => day.date === activeDate,
	);

	// Memoized so each week's context object keeps its reference across renders
	// that don't change the calendar or active date.
	const weekContextValue = useMemo(
		() => ( {
			...weekContext,
			'gatherpress/weekIndex': weekIndex,
			'gatherpress/weekDays': annotatedWeek,
			'gatherpress/weekNumber': weekNumber,
			'gatherpress/activeDate': activeDate,
			'gatherpress/setActiveDate': setActiveDate,
			'gatherpress/numRibbonSlots': numRibbonSlots,
		} ),
		[
			weekContext,
			weekIndex,
			annotatedWeek,
			weekNumber,
			activeDate,
			setActiveDate,
			numRibbonSlots,
		],
	);

	return (
		<BlockContextProvider value={ weekContextValue }>
			{ isActiveWeek ? (
				liveWeekChildren
			) : (
				<CalendarWeekPreview
					week={ annotatedWeek }
					weekNumber={ weekNumber }
					dayInnerBlocks={ dayInnerBlocks }
					weekBlockAttributes={ weekBlockAttributes }
					dayBlockAttributes={ dayBlockAttributes }
					onActivateDay={ setActiveDate }
					numRibbonSlots={ numRibbonSlots }
					isTimeGrid={ isTimeGrid }
					timeGridConfig={ timeGridConfig }
				/>
			) }
		</BlockContextProvider>
	);
}
