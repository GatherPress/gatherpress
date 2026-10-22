/**
 * CalendarDayPreview Component
 *
 * @package
 * @since TBD
 */

import { memo, useCallback, useMemo } from '@wordpress/element';
import {
	BlockContextProvider,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalUseColorProps as useColorProps,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalUseBorderProps as useBorderProps,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalGetSpacingClassesAndStyles as getSpacingClassesAndStyles,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalGetShadowClassesAndStyles as getShadowClassesAndStyles,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalUseBlockPreview as useBlockPreview,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalGetGapCSSValue as getGapCSSValue,
} from '@wordpress/block-editor';
import { dateI18n } from '@wordpress/date';

import {
	isDayNumberBindingBlock,
	getLayoutProps,
	FALLBACK_BLOCK_GAP,
} from '../blocks/calendar/utils/day-number';
import { getRibbonRowsStyle } from '../blocks/calendar/utils/ribbon-rows';

/**
 * Strips top-level event-date blocks from entries on multi-day/all-day spans.
 *
 * @param {Array} blocks Day template inner blocks.
 * @return {Array} Sanitized blocks.
 */
function stripMultiDayEventDates( blocks = [] ) {
	return ( blocks ?? [] ).map( ( block ) => {
		if (
			'gatherpress/calendar-entries' === block.name &&
			block.innerBlocks
		) {
			return {
				...block,
				innerBlocks: block.innerBlocks.map( ( inner ) => {
					if (
						'gatherpress/modal-manager' === inner.name &&
						inner.innerBlocks
					) {
						return {
							...inner,
							innerBlocks: inner.innerBlocks.filter(
								( b ) => 'gatherpress/event-date' !== b.name,
							),
						};
					}
					return inner;
				} ),
			};
		}
		return block;
	} );
}

/**
 * Clones inner blocks, resolving the Day Number bound block's content.
 * Filters out entries on empty days and strips multi-day start times.
 *
 * @param {Array}   blocks   Real inner blocks to preview.
 * @param {Object}  day      Day data (day, isEmpty, posts).
 * @param {boolean} isRibbon Whether the day block is in ribbon style.
 * @return {Array} Resolved blocks.
 */
function withResolvedDayContent( blocks, day, isRibbon ) {
	const hasPosts = 0 < ( day.posts?.length ?? 0 );
	const hasMultiDay = day.posts?.some(
		( p ) => 1 < ( p.totalDays ?? 1 ) || p.isAllDay,
	);

	let processedBlocks = ( blocks ?? [] ).filter( ( block ) => {
		if ( ! hasPosts ) {
			return isDayNumberBindingBlock( block );
		}
		return true;
	} );

	if ( isRibbon && hasMultiDay ) {
		processedBlocks = stripMultiDayEventDates( processedBlocks );
	}

	return processedBlocks.map( ( block ) => {
		if ( ! isDayNumberBindingBlock( block ) ) {
			return block;
		}

		const format =
			block.attributes?.metadata?.bindings?.content?.args?.format || '';
		let content = '';

		if ( ! day.isEmpty ) {
			if ( day.date && '' !== format ) {
				const dateObj = new Date( `${ day.date }T12:00:00Z` );
				content = dateI18n( format, dateObj, 'UTC' );
			} else {
				content = String( day.day ?? '' );
			}
		}

		return {
			...block,
			attributes: {
				...block.attributes,
				content,
				metadata: {
					...block.attributes?.metadata,
					bindings: undefined,
				},
			},
		};
	} );
}

/**
 * CalendarDayPreview Component
 *
 * Renders a single, non-editable calendar day cell as a virtual instance:
 * a real `<td>` (matching gatherpress/calendar-day's own markup, including
 * its own color/border block-support styling) wrapping a read-only editor
 * preview of that day's real inner blocks (e.g. Day Number, Post Title,
 * Event Date), rendered under the day's own context (date, day number,
 * posts, isToday, isEmpty).
 *
 * We deliberately preview only the day's *inner* blocks rather than the
 * gatherpress/calendar-day block itself, because previewing a block that
 * renders its own <td> would nest an extra wrapper element inside this
 * <td>, producing invalid table markup.
 *
 * Clicking the cell activates it, making it the live/editable day.
 *
 * @since TBD
 *
 * @param {Object}   props                    Component props.
 * @param {Object}   props.day                Day data (day, date, posts, isEmpty, isToday).
 * @param {Array}    props.innerBlocks        The real calendar-day block's inner blocks to preview.
 * @param {Object}   props.dayBlockAttributes The real calendar-day block's own attributes, for style parity.
 * @param {Function} props.onActivateDay      Called with this day's date when the cell is clicked.
 * @param {number}   props.numRibbonSlots     Number of ribbon slots.
 * @param {boolean}  props.isTimeGrid         Whether time grid is active.
 * @param {Object}   props.timeGridConfig     Time grid configuration object.
 *
 * @return {Element} Day cell preview element.
 */
function CalendarDayPreviewComponent( {
	day,
	innerBlocks,
	dayBlockAttributes,
	onActivateDay,
	numRibbonSlots = 0,
	isTimeGrid = false,
	timeGridConfig = {},
} ) {
	const onActivate = useCallback(
		() => onActivateDay( day.date ),
		[ onActivateDay, day.date ],
	);

	const dayContext = useMemo(
		() => ( {
			'gatherpress/dayDate': day.date ?? '',
			'gatherpress/dayNumber': day.day ?? 0,
			'gatherpress/dayPosts': day.posts ?? [],
			'gatherpress/isEmpty': !! day.isEmpty,
			'gatherpress/isToday': !! day.isToday,
			'gatherpress/enableTimeGrid': isTimeGrid,
			'gatherpress/timeGridConfig': timeGridConfig,
			'gatherpress/numRibbonSlots': numRibbonSlots,
		} ),
		[ day, isTimeGrid, timeGridConfig, numRibbonSlots ],
	);

	const isRibbon = Boolean(
		dayBlockAttributes?.className?.includes( 'is-style-ribbon' ),
	);
	const isRibbonOrTimeGrid = isRibbon || isTimeGrid;

	const hasDayNumberBlock = useMemo(
		() => ( innerBlocks ?? [] ).some( isDayNumberBindingBlock ),
		[ innerBlocks ],
	);

	const resolvedBlocks = useMemo(
		() => withResolvedDayContent( innerBlocks, day, isRibbonOrTimeGrid ),
		[ innerBlocks, day, isRibbonOrTimeGrid ],
	);

	// Mirror the real calendar-day block's own color/border/spacing/shadow
	// styling (e.g. a custom background, padding, or drop-shadow) so every
	// previewed day looks like the live one.
	const colorProps = useColorProps( dayBlockAttributes ?? {} );
	const borderProps = useBorderProps( dayBlockAttributes ?? {} );
	const spacingProps = getSpacingClassesAndStyles( dayBlockAttributes ?? {} );
	const shadowProps = getShadowClassesAndStyles( dayBlockAttributes ?? {} );
	const layoutProps = getLayoutProps(
		dayBlockAttributes?.layout,
		dayBlockAttributes?.style?.spacing?.blockGap,
	);
	// console.group("getLayoutProps");
	// console.log(dayBlockAttributes);
	// console.log(colorProps.className);
	// console.log(layoutProps.className);
	// console.log(layoutProps.style);
	// console.groupEnd();
	const isPast = ! day.isEmpty && ! day.isToday && day.isPast;
	const isFuture = ! day.isEmpty && ! day.isToday && day.isFuture;

	const classNames = [
		'gatherpress-calendar__day',
		day.isEmpty ? 'is-empty' : '',
		day.isToday ? 'is-today' : '',
		isPast ? 'is-past' : '',
		isFuture ? 'is-future' : '',
		0 < day.posts?.length ? 'has-posts' : '',
		day.posts?.some( ( p ) => 1 < ( p.totalDays ?? 1 ) || p.isAllDay )
			? 'has-multi-day-event'
			: '',
		day.isWeekend ? 'is-weekend' : '',
		day.weekday ? `is-${ day.weekday }` : '',
		isRibbonOrTimeGrid ? 'is-style-ribbon' : '',
		isTimeGrid ? 'has-time-grid' : '',
		colorProps.className,
		borderProps.className,
	]
		.filter( Boolean )
		.join( ' ' );

	const style = {
		...colorProps.style,
		...borderProps.style,
		...spacingProps.style,
		...shadowProps.style,
		...getRibbonRowsStyle( numRibbonSlots ),
	};

	const previewLayoutStyles = isRibbonOrTimeGrid
		? {
			display: 'flex',
			flexDirection: 'column',
			alignItems: 'stretch',
			justifyContent: 'flex-start',
			width: '100%',
			minWidth: 0,
		}
		: {
			...layoutProps.style,
			// A split gap with one side set uses the root gap for the other.
			gap: getGapCSSValue(
				dayBlockAttributes?.style?.spacing?.blockGap,
				FALLBACK_BLOCK_GAP,
			),
		};

	const blockPreviewProps = useBlockPreview( {
		blocks: resolvedBlocks,
		props: {
			className: isRibbonOrTimeGrid
				? 'gatherpress-calendar__day-preview'
				: layoutProps.className || undefined,
			style: previewLayoutStyles,
		},
	} );

	if ( day.isEmpty ) {
		return <td className={ classNames } style={ style } />;
	}

	return (
		<BlockContextProvider value={ dayContext }>
			<td
				className={ classNames }
				style={ style }
				role="button"
				tabIndex={ 0 }
				onClick={ onActivate }
				onKeyPress={ onActivate }
			>
				{ ! hasDayNumberBlock && (
					<div className="gatherpress-calendar__day-number">
						{ day.day }
					</div>
				) }
				{ 0 < resolvedBlocks.length && (
					<div { ...blockPreviewProps } />
				) }
			</td>
		</BlockContextProvider>
	);
}

// Memoized: each instance mounts a real, isolated block-editor preview
// (useBlockPreview). Without memoization, every unrelated re-render
// higher up (e.g. selecting any block) would re-render every one of
// these across the whole grid, which is visibly expensive.
export const CalendarDayPreview = memo( CalendarDayPreviewComponent );
