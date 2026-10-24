import { memo } from '@wordpress/element';
import { __, sprintf } from '@wordpress/i18n';
import {
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalUseColorProps as useColorProps,
} from '@wordpress/block-editor';

import { CalendarDayPreview } from './CalendarDayPreview';
import { getRibbonRowsStyle } from '../blocks/calendar/utils/ribbon-rows';

/**
 * CalendarWeekPreview Component
 *
 * Renders a whole non-active week as a read-only virtual instance: a real
 * `<tr>` (mirroring the real calendar-week block's own color styling) of
 * CalendarDayPreview previews, one per day, all built from the same real day
 * template inner blocks. Clicking any day activates it, making that week
 * (and that day) the live/editable one.
 *
 * @since TBD
 *
 * @param {Object}   props                     Component props.
 * @param {Array}    props.week                This week's day data entries.
 * @param {number}   props.weekNumber          ISO week number, 0 for none.
 * @param {Array}    props.dayInnerBlocks      The real day template's inner blocks, for previews.
 * @param {Object}   props.weekBlockAttributes The real calendar-week block's own attributes, for style parity.
 * @param {Object}   props.dayBlockAttributes  The real calendar-day block's own attributes, for style parity.
 * @param {Function} props.onActivateDay       Called with a day's date when that day is clicked.
 * @param {number}   props.numRibbonSlots      Number of ribbon slots.
 * @param {boolean}  props.isTimeGrid          Whether time grid is active.
 * @param {Object}   props.timeGridConfig      Time grid configuration object.
 *
 * @return {Element} Week row preview element.
 */
function CalendarWeekPreviewComponent( {
	week,
	weekNumber,
	dayInnerBlocks,
	weekBlockAttributes,
	dayBlockAttributes,
	onActivateDay,
	numRibbonSlots = 0,
	isTimeGrid = false,
	timeGridConfig = {},
} ) {
	// Mirror the real calendar-week block's own color/spacing/shadow
	// styling so every previewed week row looks like the live one.
	const colorProps = useColorProps( weekBlockAttributes ?? {} );

	const classNames = [
		'gatherpress-calendar__week',
		isTimeGrid ? 'has-time-grid' : '',
		colorProps.className,
	]
		.filter( Boolean )
		.join( ' ' );

	const style = {
		...colorProps.style,
		...getRibbonRowsStyle( numRibbonSlots ),
	};

	return (
		<tr className={ classNames } style={ style }>
			{ /* Time Gutter column: first child inside <tr> */ }
			{ isTimeGrid && (
				<th
					scope="row"
					className="gatherpress-calendar__time-gutter"
					aria-hidden="true"
					style={ {
						paddingTop: `calc(28px + ${ numRibbonSlots } * 24px)`,
					} }
				>
					{ ( timeGridConfig.slots || [] ).map( ( slot ) => (
						<div
							key={ slot }
							className="gatherpress-calendar__time-slot-label"
						>
							{ slot }
						</div>
					) ) }
				</th>
			) }

			{ 0 < weekNumber && (
				<th scope="row" className="gatherpress--screen-reader-text">
					{ sprintf(
						/* translators: %d: ISO 8601 week number. */
						__( 'Week %d', 'gatherpress' ),
						weekNumber,
					) }
				</th>
			) }

			{ week.map( ( day, dayIndex ) => (
				<CalendarDayPreview
					key={ day.date ?? `empty-${ dayIndex }` }
					day={ day }
					innerBlocks={ dayInnerBlocks }
					dayBlockAttributes={ dayBlockAttributes }
					onActivateDay={ onActivateDay }
					numRibbonSlots={ numRibbonSlots }
					isTimeGrid={ isTimeGrid }
					timeGridConfig={ timeGridConfig }
				/>
			) ) }
		</tr>
	);
}

// Memoized: this (and each CalendarDayPreview inside it) mounts a real,
// isolated block-editor preview instance. Without memoization, every
// unrelated re-render higher up (e.g. selecting any block) would
// re-render every non-active week/day preview in the whole grid.
export const CalendarWeekPreview = memo( CalendarWeekPreviewComponent );
