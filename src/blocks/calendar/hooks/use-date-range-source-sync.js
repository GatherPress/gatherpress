/**
 * Hook to manage dateRangeSource synchronization, view-presets, and attribute persistence.
 *
 * Separates date-anchoring concerns from the Edit layout component.
 *
 * @package
 * @since TBD
 */

import { store as coreStore } from '@wordpress/core-data';
import { useSelect } from '@wordpress/data';
import { useEffect, useMemo, useRef } from '@wordpress/element';
import { usePostTypeSupports } from '../../../helpers/event';
import { useSourcePostDates } from './use-source-post-dates';
import { calculatePostSpanUnits } from '../utils/date-utils';
import {
	DATE_SOURCE_DEFAULT,
	isContextDateSource,
} from '../utils/source-utils';

/**
 * Coordinates date source resolution, attribute synchronization, and duration presets.
 *
 * @param {Object}   params               Hook parameters.
 * @param {Object}   params.attributes    Block attributes.
 * @param {Function} params.setAttributes Block setAttributes dispatcher.
 * @param {Object}   params.context       Block context.
 * @param {number}   params.startOfWeek   Start of week index (0-6).
 *
 * @return {Object} Synchronized dates state.
 */
export function useDateRangeSourceSync( {
	attributes,
	setAttributes,
	context,
	startOfWeek,
} ) {
	const {
		viewType = 'month',
		unitCount = 1,
		selectedDate = '',
		dateModifier = 0,
		dateRangeSource = DATE_SOURCE_DEFAULT,
		postId = 0,
		sourcePostType = '',
	} = attributes;

	const { postId: contextPostId = 0, postType: contextPostType = '' } =
		context;

	// Verify if the current host post type supports gatherpress-event-date.
	const hasCurrentSupport = usePostTypeSupports(
		'gatherpress-event-date',
		contextPostType || null,
	);

	// usePostTypeSupports() is also false while the post type loads. Without this
	// check, a cold editor load resets a saved "Current" source and marks the post dirty.
	const hasResolvedPostType = useSelect(
		( wpSelect ) => {
			const typeToCheck =
				contextPostType ||
				wpSelect( 'core/editor' )?.getCurrentPostType();

			return (
				! typeToCheck ||
				wpSelect( coreStore ).hasFinishedResolution( 'getPostType', [
					typeToCheck,
				] )
			);
		},
		[ contextPostType ],
	);

	// Fallback to default if context mode is requested on a non-event post type.
	useEffect( () => {
		if (
			isContextDateSource( dateRangeSource ) &&
			hasResolvedPostType &&
			! hasCurrentSupport
		) {
			setAttributes( { dateRangeSource: DATE_SOURCE_DEFAULT } );
		}
	}, [ dateRangeSource, hasResolvedPostType, hasCurrentSupport, setAttributes ] );

	const sourcePostDates = useSourcePostDates( {
		dateRangeSource,
		postId,
		sourcePostType,
		contextPostId,
		contextPostType,
	} );

	const hasPostDates = Boolean(
		sourcePostDates.hasPost &&
		sourcePostDates.startDate &&
		sourcePostDates.endDate,
	);

	const effectiveUnitCount = useMemo( () => {
		if ( ! hasPostDates ) {
			return unitCount;
		}
		return calculatePostSpanUnits(
			viewType,
			sourcePostDates.startDate,
			sourcePostDates.endDate,
			startOfWeek,
		);
	}, [
		hasPostDates,
		viewType,
		sourcePostDates.startDate,
		sourcePostDates.endDate,
		startOfWeek,
		unitCount,
	] );

	const effectiveSelectedDate = hasPostDates
		? sourcePostDates.startDate
		: selectedDate;

	const effectiveDateModifier = hasPostDates ? 0 : dateModifier;

	// Synchronize unitCount, selectedDate, and dateModifier attributes with the event post span.
	useEffect( () => {
		if ( ! hasPostDates ) {
			return;
		}

		const updates = {};
		if ( unitCount !== effectiveUnitCount ) {
			updates.unitCount = effectiveUnitCount;
		}
		if ( selectedDate !== effectiveSelectedDate ) {
			updates.selectedDate = effectiveSelectedDate;
		}
		if ( 0 !== dateModifier ) {
			updates.dateModifier = 0;
		}

		if ( 0 < Object.keys( updates ).length ) {
			setAttributes( updates );
		}
	}, [
		hasPostDates,
		effectiveUnitCount,
		effectiveSelectedDate,
		unitCount,
		selectedDate,
		dateModifier,
		setAttributes,
	] );

	// Preset viewType based on post duration when a new source post is selected.
	// The ref starts at the post the block opens with, so opening the editor keeps
	// the saved view; only a change of source post applies the preset.
	const lastPresetPostIdRef = useRef( sourcePostDates.effectivePostId );
	useEffect( () => {
		if ( ! hasPostDates || ! sourcePostDates.effectivePostId ) {
			return;
		}

		if ( lastPresetPostIdRef.current === sourcePostDates.effectivePostId ) {
			return;
		}

		lastPresetPostIdRef.current = sourcePostDates.effectivePostId;

		const startObj = new Date( sourcePostDates.startDate );
		const endObj = new Date( sourcePostDates.endDate );
		const durationDays =
			Math.round( ( endObj - startObj ) / ( 1000 * 60 * 60 * 24 ) ) + 1;

		let recommendedView = 'month';
		if ( 3 >= durationDays ) {
			recommendedView = 'day';
		} else if ( 28 >= durationDays ) {
			recommendedView = 'week';
		}

		if ( viewType !== recommendedView ) {
			setAttributes( { viewType: recommendedView } );
		}
	}, [
		hasPostDates,
		sourcePostDates.effectivePostId,
		sourcePostDates.startDate,
		sourcePostDates.endDate,
		viewType,
		setAttributes,
	] );

	return {
		sourcePostDates,
		hasPostDates,
		effectiveUnitCount,
		effectiveSelectedDate,
		effectiveDateModifier,
		hasCurrentSupport,
	};
}
