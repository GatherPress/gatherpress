/**
 * GatherPress Calendar Day Block Editor Component
 *
 * @package
 * @since TBD
 */

import {
	BlockContextProvider,
	useBlockProps,
	useInnerBlocksProps,
	InspectorControls,
	store as blockEditorStore,
} from '@wordpress/block-editor';
import { PanelBody, RangeControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useSelect } from '@wordpress/data';
import { useMemo, memo } from '@wordpress/element';

import './editor.scss';

import { findDayNumberBlock } from '../calendar/utils/day-number';

const EMPTY_ARRAY = [];

// The Day Number paragraph and the entries of the day. The calendar renders only these.
const ALLOWED_BLOCKS = [ 'core/paragraph', 'gatherpress/calendar-entries' ];

/**
 * Edit Component for Calendar Day
 *
 * Renders an individual calendar cell with date information and event dots/content.
 *
 * @param {Object}   props               Component props.
 * @param {Object}   props.attributes    Block attributes.
 * @param {Function} props.setAttributes Function to update block attributes.
 * @param {Object}   props.context       Context provided by parent Calendar / query.
 * @param {string}   props.clientId      This block's client ID.
 * @return {Element} Day cell preview element.
 */
export default memo( function Edit( {
	attributes,
	setAttributes,
	context,
	clientId,
} ) {
	const { slotHeight = 48 } = attributes || {};

	const dayDate = context?.[ 'gatherpress/dayDate' ] ?? '';
	const dayNumber = context?.[ 'gatherpress/dayNumber' ] ?? 1;
	const isToday = context?.[ 'gatherpress/isToday' ] ?? false;
	const isEmpty = context?.[ 'gatherpress/isEmpty' ] ?? false;
	const weekday = context?.[ 'gatherpress/weekday' ] ?? '';
	const isWeekend = context?.[ 'gatherpress/isWeekend' ] ?? false;
	const viewType = context?.[ 'gatherpress/viewType' ] ?? 'month';
	const numRibbonSlots = context?.[ 'gatherpress/numRibbonSlots' ] ?? 0;
	const dayStartHour = context?.[ 'gatherpress/dayStartHour' ] ?? 8;
	const dayEndHour = context?.[ 'gatherpress/dayEndHour' ] ?? 20;

	const isDatetimeOrdered =
		'datetime' === ( context?.query?.orderBy || context?.query?.orderby );
	const isTimeGridActive = Boolean(
		( 'day' === viewType || 'week' === viewType ) &&
		isDatetimeOrdered &&
		context?.[ 'gatherpress/enableTimeGrid' ],
	);

	const rawPosts = context?.[ 'gatherpress/dayPosts' ];
	const posts = useMemo( () => rawPosts ?? EMPTY_ARRAY, [ rawPosts ] );

	const isRibbon = Boolean(
		attributes?.className?.includes( 'is-style-ribbon' ),
	);
	const isRibbonOrTimeGrid = isRibbon || isTimeGridActive;

	// block.json's own providesContext only re-exposes values stored in
	// this block's *attributes*, which we never set (day number etc. are
	// purely derived from context, not persisted). That means descendants
	// - like a Day Number block bound to our binding source, which reads
	// this same context to resolve its value - would only ever see stale
	// attribute defaults. Re-provide the real, current values explicitly
	// so bindings and any other context-aware child resolve correctly.
	const dayContext = useMemo(
		() => ( {
			'gatherpress/dayDate': dayDate,
			'gatherpress/dayNumber': dayNumber,
			'gatherpress/dayPosts': posts,
			'gatherpress/isEmpty': isEmpty,
			'gatherpress/isToday': isToday,
			'gatherpress/weekday': weekday,
			'gatherpress/isWeekend': isWeekend,
			'gatherpress/enableTimeGrid': isTimeGridActive,
			'gatherpress/numRibbonSlots': numRibbonSlots,
			'gatherpress/dayStartHour': dayStartHour,
			'gatherpress/dayEndHour': dayEndHour,
			'gatherpress/slotHeight': slotHeight,
		} ),
		[
			dayDate,
			dayNumber,
			posts,
			isEmpty,
			isToday,
			weekday,
			isWeekend,
			isTimeGridActive,
			numRibbonSlots,
			dayStartHour,
			dayEndHour,
			slotHeight,
		],
	);

	// Check whether this day cell or any of its inner blocks are currently selected,
	// and whether a bound Day Number block exists in the template.
	const { isDayActive, hasDayNumberBlock } = useSelect(
		( select ) => {
			const { isBlockSelected, hasSelectedInnerBlock, getBlocks } =
				select( blockEditorStore );

			return {
				isDayActive:
					isBlockSelected( clientId ) ||
					hasSelectedInnerBlock( clientId, true ),
				hasDayNumberBlock: !! findDayNumberBlock(
					getBlocks( clientId ),
				),
			};
		},
		[ clientId ],
	);

	const classNames = [
		'gatherpress-calendar__day',
		isEmpty ? 'is-empty' : '',
		isToday ? 'is-today' : '',
		0 < posts.length ? 'has-posts' : '',
		isWeekend ? 'is-weekend' : '',
		weekday ? `is-${ weekday }` : '',
		isRibbonOrTimeGrid ? 'is-style-ribbon' : '',
		isTimeGridActive ? 'has-time-grid' : '',
		! isDayActive && 0 === posts.length ? 'is-inactive-empty-day' : '',
	]
		.filter( Boolean )
		.join( ' ' );

	const blockProps = useBlockProps( {
		className: classNames,
		style: {
			'--gatherpress-num-ribbon-slots': numRibbonSlots,
		},
	} );

	const { children, ...innerBlocksWrapperProps } = useInnerBlocksProps(
		{},
		{
			allowedBlocks: ALLOWED_BLOCKS,
			templateLock: false,
			renderAppender: false,
		},
	);

	if ( isEmpty ) {
		return <td { ...blockProps } />;
	}

	return (
		<>
			{ isTimeGridActive && (
				<InspectorControls>
					<PanelBody
						title={ __(
							'Time Grid Sizing',
							'gatherpress',
						) }
						initialOpen={ true }
					>
						<RangeControl
							label={ __(
								'Slot Height (Vertical Space)',
								'gatherpress',
							) }
							value={ slotHeight }
							onChange={ ( val ) =>
								setAttributes( { slotHeight: val } )
							}
							min={ 32 }
							max={ 120 }
							step={ 4 }
							help={ __(
								'Adjusts the vertical space per hour slot in pixels.',
								'gatherpress',
							) }
						/>
					</PanelBody>
				</InspectorControls>
			) }
			<td { ...blockProps }>
				{ ! hasDayNumberBlock && (
					<div className="gatherpress-calendar__day-number">
						{ dayNumber }
					</div>
				) }
				<div { ...innerBlocksWrapperProps }>
					<BlockContextProvider value={ dayContext }>
						{ children }
					</BlockContextProvider>
				</div>
			</td>
		</>
	);
} );
