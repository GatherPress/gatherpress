/**
 * Calendar Entries Block Editor Component
 *
 * @package
 * @since TBD
 */

import {
	useBlockProps,
	useInnerBlocksProps,
	BlockContextProvider,
	store as blockEditorStore,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalUseColorProps as useColorProps,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalUseBorderProps as useBorderProps,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalGetShadowClassesAndStyles as getShadowClassesAndStyles,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalUseBlockPreview as useBlockPreview,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalGetGapCSSValue as getGapCSSValue,
} from '@wordpress/block-editor';
import { useSelect } from '@wordpress/data';
import { useMemo, memo } from '@wordpress/element';

const EMPTY_ARRAY = [];

/**
 * Filter out top-level event-date blocks outside modals on multi-day events.
 *
 * @param {Array} blocks Template inner blocks.
 * @return {Array} Filtered blocks.
 */
function filterMultiDayBlocks( blocks = [] ) {
	return blocks
		.filter( ( block ) => 'gatherpress/event-date' !== block.name )
		.map( ( block ) => {
			if (
				'gatherpress/modal-manager' === block.name &&
				block.innerBlocks
			) {
				return {
					...block,
					innerBlocks: block.innerBlocks.filter(
						( inner ) => 'gatherpress/event-date' !== inner.name,
					),
				};
			}
			return block;
		} );
}

/**
 * Renders preview copies for the 2nd, 3rd, etc. posts in the day cell.
 */
const EventPreview = memo( function EventPreviewComponent( {
	blocks,
	isMultiDay = false,
} ) {
	const previewBlocks = useMemo( () => {
		return isMultiDay ? filterMultiDayBlocks( blocks ) : blocks;
	}, [ blocks, isMultiDay ] );

	const previewProps = useBlockPreview( { blocks: previewBlocks } );

	return (
		<div
			{ ...previewProps }
			className={ `gatherpress-calendar__entry-template ${
				previewProps.className || ''
			}` }
		/>
	);
} );

/**
 * Individual event item wrapper that supplies event context, span classes, and slot variables.
 */
const EventItem = memo( function EventItemComponent( {
	post,
	isFirst,
	innerBlocksProps,
	innerBlocks,
	parentAttributes,
	defaultPostType = 'post',
} ) {
	// Safely resolve postId.
	let postId;
	if ( 'object' === typeof post && 'number' === typeof post?.id ) {
		postId = post.id;
	} else if ( 'number' === typeof post ) {
		postId = post;
	}

	const postType =
		'object' === typeof post && post?.type ? post.type : defaultPostType;

	const isAllDay = Boolean(
		'object' === typeof post &&
		( post?.isAllDay ||
			true === post?.meta?.gatherpress_is_all_day ||
			'1' === post?.meta?.gatherpress_is_all_day ||
			1 === post?.meta?.gatherpress_is_all_day ||
			'true' === post?.meta?.gatherpress_is_all_day ),
	);
	const totalDays = 'object' === typeof post ? ( post?.totalDays ?? 1 ) : 1;
	const isMultiDay = 1 < totalDays || isAllDay;
	const dayIndex = 'object' === typeof post ? ( post?.dayIndex ?? 1 ) : 1;
	const slot = 'object' === typeof post ? ( post?.slot ?? 0 ) : 0;
	const span =
		'object' === typeof post ? ( post?.span ?? 'single' ) : 'single';
	const isContinued = Boolean(
		'object' === typeof post && post?.isContinued,
	);
	const isContinues = Boolean(
		'object' === typeof post && post?.isContinues,
	);

	// Timed grid coordinates
	const isTimed = Boolean( 'object' === typeof post && post?.isTimed );
	const timeRowStart = post?.timeRowStart;
	const timeRowEnd = post?.timeRowEnd;
	const overlapTotal = post?.overlapTotal ?? 1;
	const overlapIndex = post?.overlapIndex ?? 0;

	const contextValue = useMemo( () => {
		// The placeholder item on an empty day has no event. Clear the post
		// context, so blocks like core/post-title do not show the post that
		// holds the calendar, but their own placeholder.
		if ( ! postId ) {
			return { postId: undefined, postType: undefined };
		}
		return {
			postId,
			postType,
			'gatherpress/eventSpan': span,
			'gatherpress/eventDayIndex': dayIndex,
			'gatherpress/eventTotalDays': totalDays,
			'gatherpress/isAllDay': isAllDay,
			'gatherpress/eventSlot': slot,
		};
	}, [ postId, postType, span, dayIndex, totalDays, isAllDay, slot ] );

	const colorProps = useColorProps( parentAttributes ?? {} );
	const borderProps = useBorderProps( parentAttributes ?? {} );
	const shadowProps = getShadowClassesAndStyles( parentAttributes ?? {} );

	const rowSpan = 'object' === typeof post ? ( post?.rowSpan ?? 1 ) : 1;

	const classNames = [
		'gatherpress-calendar__entry',
		postId ? `post-${ postId }` : '',
		isAllDay ? 'is-all-day' : '',
		isMultiDay ? 'is-multi-day' : '',
		isTimed ? 'is-timed-event' : '',
		span ? `is-span-${ span }` : '',
		isContinued ? 'is-span-continued' : '',
		isContinues ? 'is-span-continues' : '',
		2 <= rowSpan ? 'has-multi-col-span' : '',
		colorProps.className,
		borderProps.className,
	]
		.filter( Boolean )
		.join( ' ' );

	const style = {
		...colorProps.style,
		'--gatherpress-calendar-dot-color': colorProps.style?.color,
		'--gatherpress-calendar-entry-slot': slot,
		'--gatherpress-calendar-row-span': rowSpan,
		...( isTimed
			? {
				'--gatherpress-event-time-row-start': timeRowStart,
				'--gatherpress-event-time-row-end': timeRowEnd,
				'--gatherpress-overlap-total': overlapTotal,
				'--gatherpress-overlap-index': overlapIndex,
			}
			: {} ),
		...borderProps.style,
		...shadowProps.style,
	};

	return (
		<BlockContextProvider value={ contextValue }>
			<div
				className={ classNames }
				style={ style }
				data-slot={ slot }
				data-span={ span }
				data-day-index={ dayIndex }
				data-total-days={ totalDays }
				data-row-span={ rowSpan }
				data-is-timed={ isTimed ? 'true' : undefined }
			>
				{ isFirst ? (
					// The primary, editable template container
					<div { ...innerBlocksProps } />
				) : (
					// Cloned previews reflecting the same template for additional posts
					<EventPreview
						blocks={ innerBlocks }
						isMultiDay={ isMultiDay }
					/>
				) }
			</div>
		</BlockContextProvider>
	);
} );

export default function Edit( { attributes, clientId, context } ) {
	const dayPosts = context?.[ 'gatherpress/dayPosts' ] ?? EMPTY_ARRAY;
	const isEmpty = context?.[ 'gatherpress/isEmpty' ] ?? false;
	const defaultPostType = context?.postType || 'post';
	const blockGap = attributes?.style?.spacing?.blockGap;

	const isTimeGridActive = Boolean(
		context?.[ 'gatherpress/enableTimeGrid' ],
	);

	const blockProps = useBlockProps( {
		className: `wp-block-gatherpress-calendar-entries${
			isTimeGridActive ? ' has-time-grid' : ''
		}`,
		style: {
			gap: blockGap ? getGapCSSValue( blockGap ) : undefined,
		},
	} );

	const innerBlocksProps = useInnerBlocksProps(
		{
			className: 'gatherpress-calendar__entry-template',
		},
		{
			renderAppender: false,
		},
	);

	// Retrieve template blocks so additional items can mirror them in preview.
	const innerBlocks = useSelect(
		( select ) =>
			select( blockEditorStore ).getBlock( clientId )?.innerBlocks ??
			EMPTY_ARRAY,
		[ clientId ],
	);

	// Skip rendering if the cell is an empty padding day
	if ( isEmpty ) {
		return null;
	}

	// Show at least one placeholder item so the block template is editable on empty days
	const displayDots =
		0 < dayPosts.length ? dayPosts : [ { id: 'placeholder' } ];

	return (
		<div { ...blockProps }>
			{ displayDots.map( ( post, index ) => (
				<EventItem
					key={ post?.id ?? index }
					post={ post }
					isFirst={ 0 === index }
					innerBlocksProps={ innerBlocksProps }
					innerBlocks={ innerBlocks }
					parentAttributes={ attributes }
					defaultPostType={ defaultPostType }
				/>
			) ) }
		</div>
	);
}
