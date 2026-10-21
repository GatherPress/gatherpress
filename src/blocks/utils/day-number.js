/**
 * Shared helpers for locating the "Day Number" block - a core/paragraph
 * bound to the gatherpress/calendar-day binding source - among a calendar
 * day's real inner blocks, and reading its own visual settings so both the
 * live day and the read-only day previews can stay in sync with it.
 *
 * @package
 * @since 0.4.0
 */

// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
import { __experimentalGetGapCSSValue as getGapCSSValue } from '@wordpress/block-editor';

const DAY_NUMBER_BINDING_SOURCE = 'gatherpress/calendar-day';

/**
 * The gap for a side that the Day block does not set: the theme's root
 * block gap, as core's layout support uses, else core's 0.5em default.
 */
export const FALLBACK_BLOCK_GAP = 'var(--wp--style--block-gap, 0.5em)';

/**
 * Whether a block's `content` attribute is bound to the Day Number source.
 *
 * @param {Object} block Block object.
 *
 * @return {boolean} Whether the block is the Day Number bound block.
 */
export function isDayNumberBindingBlock( block ) {
	return (
		block?.attributes?.metadata?.bindings?.content?.source ===
		DAY_NUMBER_BINDING_SOURCE
	);
}

/**
 * Finds the Day Number bound block among a day's real inner blocks.
 *
 * @param {Array} blocks Inner blocks.
 *
 * @return {Object|undefined} The Day Number block, if present.
 */
export function findDayNumberBlock( blocks ) {
	return ( blocks ?? [] ).find( isDayNumberBindingBlock );
}

const JUSTIFY_CONTENT_BY_TEXT_ALIGN = {
	left: 'flex-start',
	center: 'center',
	right: 'flex-end',
};

/**
 * Translates the Day Number block's own text alignment into a
 * justify-content value for the flex "events" row it sits in. Text-align
 * alone has no visible effect on a shrink-wrapped flex item's position
 * within its row, so the row's main-axis alignment must follow it instead.
 *
 * @param {Array} blocks Day's real inner blocks.
 *
 * @return {string|undefined} A justify-content value, or undefined to keep the CSS default.
 */
export function getDayNumberJustifyContent( blocks ) {
	const textAlign =
		findDayNumberBlock( blocks )?.attributes?.style?.typography?.textAlign;

	return JUSTIFY_CONTENT_BY_TEXT_ALIGN[ textAlign ];
}

const FLEX_JUSTIFY_CONTENT = {
	left: 'flex-start',
	right: 'flex-end',
	center: 'center',
};

const FLEX_VERTICAL_ALIGNMENT = {
	top: 'flex-start',
	center: 'center',
	bottom: 'flex-end',
};

/**
 * Resolves the flex layout styles, as core's wp_get_layout_style() does.
 *
 * Each orientation has one extra option: a row can use "space-between"
 * justification and "stretch" alignment, a stack the reverse. In a stack,
 * justification sets align-items, with flex-start as the default, and
 * vertical alignment sets justify-content.
 *
 * @param {Object} layout Block's layout attribute object.
 *
 * @return {Object} Inline styles.
 */
function getFlexLayoutStyle( layout ) {
	const isVertical = 'vertical' === layout.orientation;
	const justifyOptions = {
		...FLEX_JUSTIFY_CONTENT,
		...( isVertical
			? { stretch: 'stretch' }
			: { 'space-between': 'space-between' } ),
	};
	const alignmentOptions = {
		...FLEX_VERTICAL_ALIGNMENT,
		...( isVertical
			? { 'space-between': 'space-between' }
			: { stretch: 'stretch' } ),
	};
	const justification = justifyOptions[ layout.justifyContent ];
	const alignment = alignmentOptions[ layout.verticalAlignment ];

	const style = {
		display: 'flex',
		flexWrap: 'nowrap' === layout.flexWrap ? 'nowrap' : 'wrap',
	};

	if ( isVertical ) {
		style.flexDirection = 'column';
		style.alignItems = justification ?? 'flex-start';
		if ( alignment ) {
			style.justifyContent = alignment;
		}
	} else {
		if ( justification ) {
			style.justifyContent = justification;
		}
		if ( alignment ) {
			style.alignItems = alignment;
		}
	}

	return style;
}

/**
 * Resolves the grid layout styles, as core's wp_get_layout_style() does.
 *
 * "Manual" mode sets columnCount (and optionally rowCount), "Auto" mode
 * sets minimumColumnWidth. With both, the columns shrink to the minimum
 * width when the container is narrow.
 *
 * @param {Object}        layout   Block's layout attribute object.
 * @param {string|Object} blockGap Block's style.spacing.blockGap value.
 *
 * @return {Object} Inline styles.
 */
function getGridLayoutStyle( layout, blockGap ) {
	const { columnCount, minimumColumnWidth, rowCount, autoFit } = layout;
	const autoPlacement = autoFit ? 'auto-fit' : 'auto-fill';
	const style = { display: 'grid' };

	if ( columnCount && minimumColumnWidth ) {
		// The column gap is the 'left' side of a split gap. Without one, use
		// the gap that the preview renders: the theme's root block gap.
		const gapSide =
			blockGap && 'object' === typeof blockGap ? blockGap.left : blockGap;
		let gap = getGapCSSValue( gapSide ) || FALLBACK_BLOCK_GAP;
		if ( '0' === gap ) {
			gap = '0px';
		}
		const maxValue = `max(min(${ minimumColumnWidth }, 100%), (100% - (${ gap } * (${ columnCount } - 1))) /${ columnCount })`;
		style.gridTemplateColumns = `repeat(${ autoPlacement }, minmax(${ maxValue }, 1fr))`;
	} else if ( columnCount ) {
		style.gridTemplateColumns = `repeat(${ columnCount }, minmax(0, 1fr))`;
	} else {
		style.gridTemplateColumns = `repeat(${ autoPlacement }, minmax(min(${
			minimumColumnWidth || '12rem'
		}, 100%), 1fr))`;
	}

	if ( ! columnCount || minimumColumnWidth ) {
		style.containerType = 'inline-size';
	}

	if ( columnCount && rowCount ) {
		style.gridTemplateRows = `repeat(${ rowCount }, minmax(1rem, auto))`;
	}

	return style;
}

/**
 * Resolves Gutenberg layout attributes into the classes and inline styles
 * that core's layout support gives the live day, for the virtual previews.
 *
 * @param {Object}        layout   Block's layout attribute object.
 * @param {string|Object} blockGap Block's style.spacing.blockGap value, used by grid columns.
 *
 * @return {Object} { className: string, style: Object }
 */
export function getLayoutProps( layout = {}, blockGap = undefined ) {
	const classes = [];
	let style = {};

	const type = layout?.type || 'default';

	if ( 'flex' === type ) {
		classes.push( 'is-layout-flex' );
		// The same classes as core's wp_render_layout_support_flag().
		if ( layout.orientation ) {
			classes.push( `is-${ layout.orientation }` );
		}
		if ( layout.justifyContent ) {
			classes.push(
				`is-content-justification-${ layout.justifyContent }`,
			);
		}
		if ( 'nowrap' === layout.flexWrap ) {
			classes.push( 'is-nowrap' );
		}
		style = getFlexLayoutStyle( layout );
	} else if ( 'grid' === type ) {
		classes.push( 'is-layout-grid' );
		style = getGridLayoutStyle( layout, blockGap );
	} else if ( 'constrained' === type ) {
		classes.push( 'is-layout-constrained' );
	} else {
		classes.push( 'is-layout-flow' );
	}

	return {
		className: classes.join( ' ' ),
		style,
	};
}
