/**
 * GatherPress Calendar Week Block Editor Component
 *
 * @package
 * @since 0.4.0
 */

import { useMemo, memo } from '@wordpress/element';
import { useSelect } from '@wordpress/data';
import { __, sprintf } from '@wordpress/i18n';
import {
	BlockContextProvider,
	useBlockProps,
	useInnerBlocksProps,
	InspectorControls,
	store as blockEditorStore,
} from '@wordpress/block-editor';
import { PanelBody, RangeControl } from '@wordpress/components';
import './editor.scss';

import { DAY_TEMPLATE } from '../calendar/edit/constants';
import { DayPreviewCell } from '../calendar/edit/components/DayPreviewCell';
import { useStableValue } from '../utils/use-stable-value';

const EMPTY_ARRAY = [];
const NOOP = () => {};

/**
 * Edit Component for Calendar Week
 *
 * Renders one week row. Exactly one day (the one matching the
 * `gatherpress/activeDate` context) is mounted as the real, live/editable
 * gatherpress/calendar-day block via InnerBlocks. The other days in the
 * row are read-only virtual previews of that same day template, rendered
 * with their own date/day-number/posts context - mirroring what
 * Calendar_Week::render() does on the frontend.
 *
 * @param {Object}   props               Component props.
 * @param {Object}   props.attributes    Block attributes.
 * @param {Function} props.setAttributes Function to update block attributes.
 * @param {Object}   props.context       Context provided by the Calendar block.
 * @param {string}   props.clientId      This week block's client ID.
 *
 * @return {Element} Week row element.
 */
export default memo( function Edit( {
	attributes,
	setAttributes,
	context,
	clientId,
} ) {
	const { dayStartHour = 8, dayEndHour = 20 } = attributes || {};

	const rawWeekDays = context?.[ 'gatherpress/weekDays' ];
	const weekDays = useMemo(
		() => rawWeekDays ?? EMPTY_ARRAY,
		[ rawWeekDays ],
	);

	const weekNumber = context?.[ 'gatherpress/weekNumber' ] ?? 0;
	const activeDate = context?.[ 'gatherpress/activeDate' ] ?? '';
	const setActiveDate = context?.[ 'gatherpress/setActiveDate' ] ?? NOOP;
	const viewType = context?.[ 'gatherpress/viewType' ] ?? 'month';

	const isDatetimeOrdered =
		'datetime' === ( context?.query?.orderBy || context?.query?.orderby );
	const isTimeGridActive = Boolean(
		( 'day' === viewType || 'week' === viewType ) &&
		isDatetimeOrdered &&
		context?.[ 'gatherpress/enableTimeGrid' ],
	);

	// The real day template's inner blocks (Day Number, Post Title, Event
	// Date, etc.) and the real day block's own attributes (for color/border
	// style parity), used to render the non-active days as previews.
	const { dayInnerBlocks, dayBlockAttributes } = useStableValue(
		useSelect(
			( select ) => {
				const { getBlocks } = select( blockEditorStore );
				const dayBlock = getBlocks( clientId )[ 0 ];
				return {
					dayInnerBlocks: dayBlock
						? getBlocks( dayBlock.clientId )
						: [],
					dayBlockAttributes: dayBlock?.attributes ?? {},
				};
			},
			[ clientId ],
		),
	);

	// Generate hour slots for the Time Gutter column.
	const timeSlots = useMemo( () => {
		if ( ! isTimeGridActive ) {
			return [];
		}
		const slots = [];
		for ( let h = dayStartHour; h < dayEndHour; h += 1 ) {
			slots.push( `${ String( h ).padStart( 2, '0' ) }:00` );
		}
		return slots;
	}, [ isTimeGridActive, dayStartHour, dayEndHour ] );

	// Calculate maximum ribbon slot index across all days in this week.
	const numRibbonSlots = useMemo( () => {
		if ( ! isTimeGridActive ) {
			return 0;
		}
		let maxSlot = -1;
		weekDays.forEach( ( day ) => {
			( day.posts || [] ).forEach( ( p ) => {
				if ( ! p.isTimed ) {
					maxSlot = Math.max( maxSlot, Number( p.slot ) || 0 );
				}
			} );
		} );
		return maxSlot + 1;
	}, [ isTimeGridActive, weekDays ] );

	const blockProps = useBlockProps( {
		className: `gatherpress-calendar__week${
			isTimeGridActive ? ' has-time-grid' : ''
		}`,
		style: {
			'--gatherpress-num-ribbon-slots': numRibbonSlots,
		},
	} );

	const { children, ...innerBlocksWrapperProps } = useInnerBlocksProps(
		blockProps,
		{
			allowedBlocks: [ 'gatherpress/calendar-day' ],
			template: DAY_TEMPLATE,
			templateLock: false,
			renderAppender: false,
		},
	);

	const activeDayIndex = useMemo(
		() => weekDays.findIndex( ( day ) => day.date === activeDate ),
		[ weekDays, activeDate ],
	);
	const activeDay = weekDays[ activeDayIndex ];

	// The live day block doesn't have real calendar-week/calendar-day
	// ancestors providing dayDate/dayNumber/etc., so we supply them here,
	// exactly like the preview cells do.
	const activeDayContext = useMemo(
		() =>
			activeDay
				? {
					'gatherpress/dayDate': activeDay.date ?? '',
					'gatherpress/dayNumber': activeDay.day ?? 0,
					'gatherpress/dayPosts': activeDay.posts ?? [],
					'gatherpress/isEmpty': !! activeDay.isEmpty,
					'gatherpress/isToday': !! activeDay.isToday,
					'gatherpress/weekday': activeDay.weekday ?? '',
					'gatherpress/isWeekend': !! activeDay.isWeekend,
					'gatherpress/numRibbonSlots': numRibbonSlots,
					'gatherpress/dayStartHour': dayStartHour,
					'gatherpress/dayEndHour': dayEndHour,
					'gatherpress/enableTimeGrid': isTimeGridActive,
					'gatherpress/viewType': viewType,
					query: context?.query,
				}
				: {},
		[
			activeDay,
			numRibbonSlots,
			dayStartHour,
			dayEndHour,
			isTimeGridActive,
			viewType,
			context?.query,
		],
	);

	const timeGridConfig = useMemo(
		() => ( {
			enabled: isTimeGridActive,
			slots: timeSlots,
			startHour: dayStartHour,
			endHour: dayEndHour,
		} ),
		[ isTimeGridActive, timeSlots, dayStartHour, dayEndHour ],
	);

	return (
		<>
			{ isTimeGridActive && (
				<InspectorControls>
					<PanelBody
						title={ __( 'Timeline Hours', 'gatherpress' ) }
						initialOpen={ true }
					>
						<RangeControl
							label={ __(
								'Day Starts At (Hour)',
								'gatherpress',
							) }
							value={ dayStartHour }
							onChange={ ( val ) =>
								setAttributes( { dayStartHour: val } )
							}
							min={ 0 }
							max={ Math.max( 0, dayEndHour - 1 ) }
							step={ 1 }
						/>
						<RangeControl
							label={ __(
								'Day Ends At (Hour)',
								'gatherpress',
							) }
							value={ dayEndHour }
							onChange={ ( val ) =>
								setAttributes( { dayEndHour: val } )
							}
							min={ Math.min( 24, dayStartHour + 1 ) }
							max={ 24 }
							step={ 1 }
						/>
					</PanelBody>
				</InspectorControls>
			) }
			<tr { ...innerBlocksWrapperProps }>
				{ /* Time Gutter column: MUST be first child inside <tr> */ }
				{ isTimeGridActive && (
					<th
						scope="row"
						className="gatherpress-calendar__time-gutter"
						aria-hidden="true"
						style={ {
							paddingTop: `calc(28px + ${ numRibbonSlots } * 24px)`,
						} }
					>
						{ timeSlots.map( ( slot ) => (
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

				{ weekDays.map( ( day, dayIndex ) =>
					dayIndex === activeDayIndex ? (
						<BlockContextProvider
							key={ day.date ?? `empty-${ dayIndex }` }
							value={ activeDayContext }
						>
							{ children }
						</BlockContextProvider>
					) : (
						<DayPreviewCell
							key={ day.date ?? `empty-${ dayIndex }` }
							day={ day }
							innerBlocks={ dayInnerBlocks }
							dayBlockAttributes={ dayBlockAttributes }
							onActivateDay={ setActiveDate }
							numRibbonSlots={ numRibbonSlots }
							isTimeGrid={ isTimeGridActive }
							timeGridConfig={ timeGridConfig }
						/>
					),
				) }
			</tr>
		</>
	);
} );
