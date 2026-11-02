/**
 * DateControls component managing calendar navigation and settings.
 *
 * @package
 * @since TBD
 */

import { __ } from '@wordpress/i18n';
/* eslint-disable @wordpress/no-unsafe-wp-apis */
import {
	Button,
	SelectControl,
	TextControl,
	ToggleControl,
	__experimentalNumberControl as NumberControl,
} from '@wordpress/components';
/* eslint-enable @wordpress/no-unsafe-wp-apis */
import { useMemo } from '@wordpress/element';

import { calculateTargetDate, formatHeading } from '../utils/date-utils';
import { useDateOffsetHelp } from '../hooks/use-date-offset-help';
import { MonthPicker } from './month-picker';
import { SourcePostControls } from './source-post-controls';
import { ShadowSourceFilterControls } from './shadow-source-filter-controls';
import {
	DATE_SOURCE_DEFAULT,
	isPostDateSource,
	isSelectedDateSource,
	isContextDateSource,
} from '../utils/source-utils';

const MAX_UNITS = {
	month: 12,
	week: 5,
	day: 7,
};

/**
 * Resolves selection text for 'selected' source mode.
 *
 * @param {boolean} hasPostDates Whether post dates are available.
 * @param {string}  selectedDate Selected date string.
 * @param {string}  postTitle    Resolved post title.
 * @param {number}  postId       Post ID.
 * @return {string} Label text.
 */
function resolveSelectedSourceLabel(
	hasPostDates,
	selectedDate,
	postTitle,
	postId,
) {
	if ( hasPostDates ) {
		return postTitle ? `${ postTitle } (${ selectedDate })` : selectedDate;
	}
	if ( 0 < postId ) {
		return __( 'Loading event dates…', 'gatherpress' );
	}
	return __( 'No source post selected.', 'gatherpress' );
}

/**
 * Resolves selection text for 'context' source mode.
 *
 * @param {boolean} hasPostDates Whether post dates are available.
 * @param {string}  selectedDate Selected date string.
 * @param {string}  postTitle    Resolved post title.
 * @return {string} Label text.
 */
function resolveContextSourceLabel( hasPostDates, selectedDate, postTitle ) {
	if ( hasPostDates ) {
		return postTitle ? `${ postTitle } (${ selectedDate })` : selectedDate;
	}
	return __(
		'No event dates detected on the current post.',
		'gatherpress',
	);
}

/**
 * Resolves selection text for 'default' source mode.
 *
 * @param {string} selectedDate Selected date string.
 * @param {string} viewType     Calendar view type.
 * @return {string} Label text.
 */
function resolveDefaultSourceLabel( selectedDate, viewType ) {
	if ( selectedDate ) {
		if ( 'month' === viewType && /^\d{4}-\d{2}/.test( selectedDate ) ) {
			const month = calculateTargetDate( { selectedDate } );
			return formatHeading( 'month', month, month );
		}
		return selectedDate;
	}

	if ( 'day' === viewType ) {
		return __( 'Current Day', 'gatherpress' );
	}
	if ( 'week' === viewType ) {
		return __( 'Current Week', 'gatherpress' );
	}
	return __( 'Current Month', 'gatherpress' );
}

/**
 * DateControls Component.
 *
 * @param {Object}   props                   Component props.
 * @param {string}   props.clientId          Calendar block client ID.
 * @param {Object}   props.context           Block context dictionary.
 * @param {string}   props.viewType          View type ('month', 'week', 'day').
 * @param {Function} props.onViewTypeChange  Callback when viewType changes.
 * @param {number}   props.unitCount         Number of units to show.
 * @param {Function} props.onUnitCountChange Callback when unitCount changes.
 * @param {string}   props.selectedDate      Selected date/month.
 * @param {number}   props.dateModifier      Date offset value.
 * @param {Function} props.onDateChange      Callback when date changes.
 * @param {Function} props.onModifierChange  Callback when modifier changes.
 * @param {string}   props.dateRangeSource   Source mode ('default', 'context', 'selected').
 * @param {Function} props.onSourceChange    Callback when source changes.
 * @param {number}   props.postId            Selected post ID.
 * @param {Function} props.onPostIdChange    Callback when post ID changes.
 * @param {string}   props.sourcePostType    Selected post type.
 * @param {Function} props.onPostTypeChange  Callback when post type changes.
 * @param {string}   props.postTitle         Resolved post title.
 * @param {boolean}  props.hasPostDates      Whether post dates are actively driving the range.
 * @param {boolean}  props.hasCurrentSupport Whether context post type supports events.
 * @param {boolean}  props.enableTimeGrid    Whether the time grid is enabled.
 * @param {Function} props.onTimeGridChange  Callback when time grid toggle changes.
 *
 * @return {Element} Date controls component.
 */
export function DateControls( {
	clientId,
	context = {},
	viewType,
	onViewTypeChange,
	unitCount,
	onUnitCountChange,
	selectedDate,
	dateModifier,
	onDateChange,
	onModifierChange,
	dateRangeSource = DATE_SOURCE_DEFAULT,
	onSourceChange,
	postId = 0,
	onPostIdChange,
	sourcePostType = '',
	onPostTypeChange,
	postTitle = '',
	hasPostDates = false,
	hasCurrentSupport = false,
	enableTimeGrid = false,
	onTimeGridChange,
} ) {
	const offsetHelp = useDateOffsetHelp( dateModifier, viewType );
	const maxUnits = MAX_UNITS[ viewType ] || 12;

	const isPostAnchored = isPostDateSource( dateRangeSource );
	const isDefaultMode = ! isPostAnchored;

	// Condition: Day or Week view AND Query Loop sorted by datetime
	const isDatetimeOrdered =
		'datetime' === ( context?.query?.orderBy || context?.query?.orderby );
	const supportsTimeGrid =
		( 'day' === viewType || 'week' === viewType ) && isDatetimeOrdered;

	const handleViewTypeChange = ( newViewType ) => {
		const newMax = MAX_UNITS[ newViewType ] || 12;
		if ( Number( unitCount ) > newMax ) {
			onUnitCountChange( newMax );
		}
		onViewTypeChange( newViewType );
	};

	const handleUnitCountChange = ( nextValue ) => {
		const num = Number( nextValue );
		if ( ! Number.isNaN( num ) && num > maxUnits ) {
			onUnitCountChange( maxUnits );
			return;
		}
		onUnitCountChange( nextValue );
	};

	const stepLabel = useMemo( () => {
		if ( 'day' === viewType ) {
			return __( 'Days from current', 'gatherpress' );
		}
		if ( 'week' === viewType ) {
			return __( 'Weeks from current', 'gatherpress' );
		}
		return __( 'Months from current', 'gatherpress' );
	}, [ viewType ] );

	const unitCountLabel = useMemo( () => {
		if ( 'day' === viewType ) {
			return __( 'Number of days to show', 'gatherpress' );
		}
		if ( 'week' === viewType ) {
			return __( 'Number of weeks to show', 'gatherpress' );
		}
		return __( 'Number of months to show', 'gatherpress' );
	}, [ viewType ] );

	const selectionLabel = useMemo( () => {
		if ( isSelectedDateSource( dateRangeSource ) ) {
			return resolveSelectedSourceLabel(
				hasPostDates,
				selectedDate,
				postTitle,
				postId,
			);
		}
		if ( isContextDateSource( dateRangeSource ) ) {
			return resolveContextSourceLabel(
				hasPostDates,
				selectedDate,
				postTitle,
			);
		}
		return resolveDefaultSourceLabel( selectedDate, viewType );
	}, [
		dateRangeSource,
		hasPostDates,
		postTitle,
		selectedDate,
		postId,
		viewType,
	] );

	const unitLabel = useMemo( () => {
		if ( 'day' === viewType ) {
			return __( 'day(s)', 'gatherpress' );
		}
		if ( 'week' === viewType ) {
			return __( 'week(s)', 'gatherpress' );
		}
		return __( 'month(s)', 'gatherpress' );
	}, [ viewType ] );

	return (
		<>
			<SourcePostControls
				dateRangeSource={ dateRangeSource }
				onSourceChange={ onSourceChange }
				postId={ postId }
				onPostIdChange={ onPostIdChange }
				sourcePostType={ sourcePostType }
				onPostTypeChange={ onPostTypeChange }
				hasCurrentSupport={ hasCurrentSupport }
			/>

			<ShadowSourceFilterControls
				clientId={ clientId }
				context={ context }
				dateRangeSource={ dateRangeSource }
				postId={ postId }
				sourcePostType={ sourcePostType }
			/>

			<SelectControl
				__next40pxDefaultSize
				label={ __( 'Calendar View', 'gatherpress' ) }
				value={ viewType }
				options={ [
					{
						label: __( 'Month', 'gatherpress' ),
						value: 'month',
					},
					{
						label: __( 'Week', 'gatherpress' ),
						value: 'week',
					},
					{
						label: __( 'Day', 'gatherpress' ),
						value: 'day',
					},
				] }
				onChange={ handleViewTypeChange }
			/>

			{ isDefaultMode && (
				<NumberControl
					__next40pxDefaultSize
					label={ unitCountLabel }
					labelPosition="side"
					type="number"
					value={ unitCount }
					onChange={ handleUnitCountChange }
					min={ 1 }
					max={ maxUnits }
					step={ 1 }
					style={ { marginBottom: '1rem' } }
				/>
			) }

			<dl
				style={ {
					margin: '0 0 1rem',
					padding: '0.5rem',
					background: '#f0f0f1',
					borderRadius: '4px',
				} }
			>
				<dt style={ { fontWeight: '600' } }>
					{ __( 'Current Selection', 'gatherpress' ) }
				</dt>
				<dd style={ { margin: 0 } }>
					{ selectionLabel }
					{ /* Blocks saved before 0.9.0 can have a date and an offset. */ }
					{ isDefaultMode && 0 !== dateModifier && (
						<>
							{ ' ' }
							{ 0 < dateModifier
								? `+${ dateModifier }`
								: dateModifier }{ ' ' }
							{ unitLabel }
						</>
					) }
				</dd>
			</dl>

			{ isDefaultMode && (
				<>
					{ 'month' === viewType ? (
						<MonthPicker
							selectedMonth={ selectedDate }
							dateModifier={ dateModifier }
							onChange={ onDateChange }
						/>
					) : (
						<TextControl
							__next40pxDefaultSize
							label={ __(
								'Specific Date (YYYY-MM-DD)',
								'gatherpress',
							) }
							type="date"
							value={ selectedDate }
							onChange={ onDateChange }
							style={ { marginBottom: '0.5rem' } }
						/>
					) }

					{ /* The reset buttons stay in place, so focus is not lost. */ }
					<Button
						onClick={ () => onDateChange( '' ) }
						variant="secondary"
						disabled={ ! selectedDate }
						accessibleWhenDisabled
						__next40pxDefaultSize
						style={ { width: '100%', marginBottom: '0.5rem' } }
					>
						{ __( 'Reset to Current', 'gatherpress' ) }
					</Button>

					{ ! selectedDate && (
						<>
							<p
								style={ {
									marginTop: '1rem',
									marginBottom: '0.5rem',
									fontWeight: '500',
								} }
							>
								{ __( 'Date Offset', 'gatherpress' ) }
							</p>
							<NumberControl
								__next40pxDefaultSize
								label={ stepLabel }
								labelPosition="side"
								type="number"
								value={ dateModifier }
								onChange={ onModifierChange }
								min={ -52 }
								max={ 52 }
								step={ 1 }
								help={ offsetHelp }
							/>
							<Button
								onClick={ () => onModifierChange( 0 ) }
								variant="secondary"
								disabled={ 0 === dateModifier }
								accessibleWhenDisabled
								__next40pxDefaultSize
								style={ {
									width: '100%',
									marginBottom: '0.5rem',
								} }
							>
								{ __( 'Reset Offset', 'gatherpress' ) }
							</Button>
						</>
					) }
				</>
			) }

			{ /* Global Time Grid Toggle (Guarded) */ }
			{ supportsTimeGrid && (
				<ToggleControl
					label={ __( 'Show Time Grid', 'gatherpress' ) }
					help={ __(
						'Render timed events vertically along an hour timeline.',
						'gatherpress',
					) }
					checked={ !! enableTimeGrid }
					onChange={ onTimeGridChange }
				/>
			) }
		</>
	);
}
