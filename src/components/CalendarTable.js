import { _x } from '@wordpress/i18n';

import { CalendarWeek } from './CalendarWeek';

/**
 * CalendarTable Component
 *
 * Renders the calendar grid as virtual instances of the real
 * gatherpress/calendar-week (and, within it, gatherpress/calendar-day)
 * blocks. Supports multi-unit layouts (e.g. multi-month display).
 *
 * @since TBD
 *
 * @param {Object}   props                     Component props.
 * @param {Object}   props.calendar            Calendar data structure.
 * @param {string[]} props.captions            Visually hidden caption of each table.
 * @param {boolean}  props.showWeekdays        Whether to show weekday headers.
 * @param {Object}   props.style               Table style.
 * @param {string}   props.activeDate          Currently live/editable date.
 * @param {Function} props.setActiveDate       Setter for active date.
 * @param {Object}   props.weekContext         Base context shared by all weeks.
 * @param {Element}  props.liveWeekChildren    Live week inner blocks.
 * @param {Array}    props.dayInnerBlocks      Day template inner blocks.
 * @param {Object}   props.weekBlockAttributes Attributes of week block.
 * @param {Object}   props.dayBlockAttributes  Attributes of day block.
 * @param {Object}   props.tbodyProps          Tbody element props.
 * @param {Object}   props.timeGridConfig      Time grid configuration.
 *
 * @return {Element} Calendar table component.
 */
export function CalendarTable( {
	calendar,
	captions = [],
	showWeekdays,
	style,
	activeDate,
	setActiveDate,
	weekContext,
	liveWeekChildren,
	dayInnerBlocks,
	weekBlockAttributes,
	dayBlockAttributes,
	tbodyProps,
	timeGridConfig = {},
} ) {
	const units = calendar.units || [
		{ dayNames: calendar.dayNames, weeks: calendar.weeks },
	];
	const isTimeGrid = Boolean( timeGridConfig?.enabled );

	return (
		<>
			{ units.map( ( unit, unitIndex ) => (
				<table
					key={ unitIndex }
					className={ `gatherpress-calendar__table${ isTimeGrid ? ' gatherpress--has-time-grid' : '' }` }
					style={ style }
				>
					{ captions[ unitIndex ] && (
						<caption className="gatherpress--screen-reader-text">
							{ captions[ unitIndex ] }
						</caption>
					) }
					{ /* Without "Show Weekdays" the row is hidden on screen only, as on the front end. */ }
					<thead
						className={
							showWeekdays
								? undefined
								: 'gatherpress--screen-reader-text'
						}
					>
						<tr>
							{ isTimeGrid && (
								<th
									scope="col"
									className="gatherpress-calendar__time-gutter-header"
									aria-hidden="true"
								/>
							) }
							{ unit.weekNumbers && (
								<th
									scope="col"
									className="gatherpress--screen-reader-text"
								>
									{ _x(
										'Week',
										'week number column header',
										'gatherpress',
									) }
								</th>
							) }
							{ unit.dayNames.map( ( dayName, index ) => (
								<th key={ index } scope="col">
									{ dayName }
								</th>
							) ) }
						</tr>
					</thead>
					<tbody { ...tbodyProps }>
						{ unit.weeks.map( ( week, weekIndex ) => (
							<CalendarWeek
								key={ weekIndex }
								week={ week }
								weekIndex={ weekIndex }
								weekNumber={
									unit.weekNumbers?.[ weekIndex ] ?? 0
								}
								activeDate={ activeDate }
								setActiveDate={ setActiveDate }
								weekContext={ weekContext }
								liveWeekChildren={ liveWeekChildren }
								dayInnerBlocks={ dayInnerBlocks }
								weekBlockAttributes={ weekBlockAttributes }
								dayBlockAttributes={ dayBlockAttributes }
								timeGridConfig={ timeGridConfig }
								numRibbonSlots={
									unit.weekRibbonSlots?.[ weekIndex ] ??
									unit.numRibbonSlots ??
									calendar.numRibbonSlots ??
									0
								}
							/>
						) ) }
					</tbody>
				</table>
			) ) }
		</>
	);
}
