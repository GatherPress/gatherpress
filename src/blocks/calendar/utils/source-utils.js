/**
 * Utility functions and constants for calendar date range sources.
 *
 * @package
 * @since TBD
 */

import { select as wpSelect } from '@wordpress/data';
import { _x } from '@wordpress/i18n';
import { store as coreStore } from '@wordpress/core-data';

export const DATE_SOURCE_DEFAULT = 'default';
export const DATE_SOURCE_CONTEXT = 'context';
export const DATE_SOURCE_SELECTED = 'selected';

/**
 * Retrieves the datetime separator published by GatherPress core.
 *
 * Falls back to the calendar's localized default if not configured.
 *
 * @return {string} Separator string.
 */
export function getDatetimeSeparator() {
	const editorSettings = wpSelect( 'core/editor' )?.getEditorSettings?.();
	const configSeparator =
		editorSettings?.gatherpress?.config?.datetimeSeparator;

	if (
		'string' === typeof configSeparator &&
		'' !== configSeparator.trim()
	) {
		return configSeparator.trim();
	}
	return _x(
		'–',
		'Calendar heading date range separator',
		'gatherpress',
	);
}

/**
 * Normalizes any date source attribute value into canonical form.
 *
 * @param {string} source Raw dateRangeSource attribute value.
 * @return {string} 'default', 'context', or 'selected'.
 */
export function normalizeDateSource( source ) {
	if (
		'context' === source ||
		'current_post' === source ||
		'current' === source
	) {
		return DATE_SOURCE_CONTEXT;
	}
	if (
		'selected' === source ||
		'specific_post' === source ||
		'specific' === source
	) {
		return DATE_SOURCE_SELECTED;
	}
	return DATE_SOURCE_DEFAULT;
}

/**
 * Checks whether the date range source is driven by an event post.
 *
 * @param {string} source Raw source value.
 * @return {boolean} True if anchored to an event post.
 */
export function isPostDateSource( source ) {
	return DATE_SOURCE_DEFAULT !== normalizeDateSource( source );
}

/**
 * Checks whether the date range source is anchored to the context post.
 *
 * @param {string} source Raw source value.
 * @return {boolean} True if context mode.
 */
export function isContextDateSource( source ) {
	return DATE_SOURCE_CONTEXT === normalizeDateSource( source );
}

/**
 * Checks whether the date range source is anchored to a specific post.
 *
 * @param {string} source Raw source value.
 * @return {boolean} True if selected mode.
 */
export function isSelectedDateSource( source ) {
	return DATE_SOURCE_SELECTED === normalizeDateSource( source );
}

/**
 * Resolves the fallback event post type slug from available types.
 *
 * @param {Array<Object>} eventPostTypes Array of post type objects.
 * @return {string} Post type slug.
 */
export function getDefaultEventPostType( eventPostTypes = [] ) {
	if ( Array.isArray( eventPostTypes ) && 0 < eventPostTypes.length ) {
		return eventPostTypes[ 0 ].slug || 'gatherpress_event';
	}
	return 'gatherpress_event';
}

/**
 * Normalizes any datetime value (string, Date, timestamp, object) into YYYY-MM-DD.
 *
 * @param {*} val Raw datetime value.
 * @return {string} YYYY-MM-DD date string or empty string.
 */
export function toDateString( val ) {
	if ( ! val ) {
		return '';
	}
	if ( 'string' === typeof val && 10 <= val.length ) {
		return val.slice( 0, 10 );
	}
	if ( 'number' === typeof val ) {
		const ms = 1e11 > val ? val * 1000 : val;
		const d = new Date( ms );
		if ( ! Number.isNaN( d.getTime() ) ) {
			const y = d.getFullYear();
			const m = String( d.getMonth() + 1 ).padStart( 2, '0' );
			const day = String( d.getDate() ).padStart( 2, '0' );
			return `${ y }-${ m }-${ day }`;
		}
	}
	if ( val instanceof Date && ! Number.isNaN( val.getTime() ) ) {
		const y = val.getFullYear();
		const m = String( val.getMonth() + 1 ).padStart( 2, '0' );
		const day = String( val.getDate() ).padStart( 2, '0' );
		return `${ y }-${ m }-${ day }`;
	}
	if ( 'object' === typeof val ) {
		if ( 'string' === typeof val.date && 10 <= val.date.length ) {
			return val.date.slice( 0, 10 );
		}
		if ( 'string' === typeof val.dateTime && 10 <= val.dateTime.length ) {
			return val.dateTime.slice( 0, 10 );
		}
	}
	return '';
}

/**
 * Extracts live event start and end dates from GatherPress's datetime store or edited meta.
 *
 * @param {Function} select WordPress data select function.
 * @return {Object|null} { startDate, endDate } or null.
 */
export function getLiveEventDates( select ) {
	if ( 'function' !== typeof select ) {
		return null;
	}

	const editorStore = select( 'core/editor' );
	const datetimeStore = select( 'gatherpress/datetime' );
	const editedMeta = editorStore?.getEditedPostAttribute?.( 'meta' );

	let liveStart = toDateString( datetimeStore?.getDateTimeStart?.() );
	if ( ! liveStart ) {
		liveStart = toDateString( editedMeta?.gatherpress_datetime_start );
	}

	let liveEnd = toDateString( datetimeStore?.getDateTimeEnd?.() );
	if ( ! liveEnd ) {
		liveEnd = toDateString( editedMeta?.gatherpress_datetime_end );
	}
	if ( ! liveEnd ) {
		liveEnd = liveStart;
	}

	if ( liveStart ) {
		let finalEnd = liveEnd;
		if ( finalEnd < liveStart ) {
			finalEnd = liveStart;
		}

		return {
			startDate: liveStart,
			endDate: finalEnd,
		};
	}

	return null;
}

/**
 * Pure resolver to extract start/end dates and title from an event post or active editor session.
 *
 * Shared between useSourcePostDates (block preview/inspector) and bindings.js (bound heading).
 *
 * @param {Function} select                   WordPress data select function.
 * @param {Object}   params                   Resolution parameters.
 * @param {string}   params.dateRangeSource   Source mode ('default', 'context', 'selected').
 * @param {number}   [params.postId]          Explicit post ID.
 * @param {string}   [params.sourcePostType]  Explicit post type slug.
 * @param {number}   [params.contextPostId]   Context post ID.
 * @param {string}   [params.contextPostType] Context post type slug.
 * @param {string}   [params.defaultPostType] Default fallback post type slug.
 *
 * @return {Object} { startDate: string, endDate: string, postTitle: string, hasPost: boolean, effectivePostId: number }
 */
export function resolveSourceEventDates( select, params = {} ) {
	const {
		dateRangeSource = DATE_SOURCE_DEFAULT,
		postId = 0,
		sourcePostType = '',
		contextPostId = 0,
		contextPostType = '',
		defaultPostType = 'gatherpress_event',
	} = params;

	const canonicalSource = normalizeDateSource( dateRangeSource );
	if ( DATE_SOURCE_DEFAULT === canonicalSource ) {
		return {
			startDate: '',
			endDate: '',
			postTitle: '',
			hasPost: false,
			effectivePostId: 0,
		};
	}

	const editorStore = select( 'core/editor' );
	const currentEditorPostId = editorStore?.getCurrentPostId?.();
	const editorPostType = editorStore?.getCurrentPostType?.();

	const isSelected = DATE_SOURCE_SELECTED === canonicalSource;
	const isContext = DATE_SOURCE_CONTEXT === canonicalSource;

	// A template in the site editor has a string ID ("theme//single-…"): it is not a post.
	const toPostId = ( id ) => {
		const number = Number( id );
		return Number.isInteger( number ) && 0 < number ? number : 0;
	};

	let effectivePostId = 0;
	if ( isSelected ) {
		effectivePostId = toPostId( postId );
	} else if ( isContext ) {
		effectivePostId =
			toPostId( contextPostId ) || toPostId( currentEditorPostId );
	}

	let effectivePostType = defaultPostType;
	if ( isSelected && sourcePostType ) {
		effectivePostType = sourcePostType;
	} else if ( isContext ) {
		effectivePostType =
			contextPostType || editorPostType || defaultPostType;
	}

	if ( ! effectivePostId ) {
		return {
			startDate: '',
			endDate: '',
			postTitle: '',
			hasPost: false,
			effectivePostId: 0,
		};
	}

	// 1. Direct-editing: listen live to gatherpress/datetime store and core/editor edited meta,
	// only for the post that the editor edits, and only when it has event dates. A Query Loop
	// item, or an editor post without event dates, reads its saved dates below.
	const isTargetCurrentEditorPost =
		effectivePostId === toPostId( currentEditorPostId ) &&
		Boolean(
			select( coreStore ).getPostType( editorPostType )?.supports?.[
				'gatherpress-event-date'
			],
		);

	if ( isTargetCurrentEditorPost ) {
		const liveDates = getLiveEventDates( select );
		if ( liveDates ) {
			const currentTitle =
				editorStore?.getEditedPostAttribute?.( 'title' ) || '';

			return {
				startDate: liveDates.startDate,
				endDate: liveDates.endDate,
				postTitle: currentTitle || `#${ effectivePostId }`,
				hasPost: true,
				effectivePostId,
			};
		}
	}

	// 2. Query loop, site editor template, or different target post: fetch via core entity record.
	const post = select( coreStore ).getEntityRecord(
		'postType',
		effectivePostType,
		effectivePostId,
	);

	if ( ! post ) {
		return {
			startDate: '',
			endDate: '',
			postTitle: '',
			hasPost: false,
			effectivePostId,
		};
	}

	const meta = post.meta;
	const startDate = toDateString( meta?.gatherpress_datetime_start );
	let endDate = startDate;
	if ( toDateString( meta?.gatherpress_datetime_end ) ) {
		endDate = toDateString( meta?.gatherpress_datetime_end );
	}
	if ( endDate < startDate ) {
		endDate = startDate;
	}

	let title = `#${ effectivePostId }`;
	if ( post.title?.rendered ) {
		title = post.title.rendered;
	} else if ( post.title?.raw ) {
		title = post.title.raw;
	}

	return {
		startDate,
		endDate,
		postTitle: title,
		hasPost: Boolean( startDate ),
		effectivePostId,
	};
}
