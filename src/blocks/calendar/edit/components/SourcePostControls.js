/**
 * Inspector controls for date range source selection.
 *
 * @package
 * @since TBD
 */

import { __ } from '@wordpress/i18n';
/* eslint-disable @wordpress/no-unsafe-wp-apis */
import {
	SelectControl,
	ComboboxControl,
	BaseControl,
	__experimentalToggleGroupControl as ToggleGroupControl,
	__experimentalToggleGroupControlOption as ToggleGroupControlOption,
} from '@wordpress/components';
/* eslint-enable @wordpress/no-unsafe-wp-apis */
import { useSelect } from '@wordpress/data';
import { store as coreStore } from '@wordpress/core-data';
import { useState, useMemo } from '@wordpress/element';

import { useEventPostTypes } from '../hooks/useEventPostTypes';
import {
	DATE_SOURCE_DEFAULT,
	DATE_SOURCE_CONTEXT,
	DATE_SOURCE_SELECTED,
	normalizeDateSource,
	isSelectedDateSource,
	getDefaultEventPostType,
} from '../utils/source-utils';

const EMPTY_RECORDS = [];

/**
 * SourcePostControls component.
 *
 * @param {Object}   props                   Component props.
 * @param {string}   props.dateRangeSource   Source mode ('default', 'context', 'selected').
 * @param {Function} props.onSourceChange    Callback when source changes.
 * @param {number}   props.postId            Selected post ID.
 * @param {Function} props.onPostIdChange    Callback when post ID changes.
 * @param {string}   props.sourcePostType    Selected source post type.
 * @param {Function} props.onPostTypeChange  Callback when post type changes.
 * @param {boolean}  props.hasCurrentSupport Whether context post type supports events.
 *
 * @return {Element} Source controls component.
 */
export function SourcePostControls( {
	dateRangeSource = DATE_SOURCE_DEFAULT,
	onSourceChange,
	postId = 0,
	onPostIdChange,
	sourcePostType = '',
	onPostTypeChange,
	hasCurrentSupport = false,
} ) {
	const [ searchFilter, setSearchFilter ] = useState( '' );
	const eventPostTypes = useEventPostTypes();

	const activeValue = normalizeDateSource( dateRangeSource );
	const isSelected = isSelectedDateSource( activeValue );

	let activePostType = sourcePostType;
	if ( ! activePostType ) {
		activePostType = getDefaultEventPostType( eventPostTypes );
	}

	const query = useMemo( () => {
		const baseQuery = {
			per_page: 100,
			// context: 'edit',
			status: [ 'publish' ],
			// gatherpress_event_query: 'all',
			orderby: 'title',
			order: 'asc',
		};

		if ( searchFilter ) {
			return { ...baseQuery, search: searchFilter };
		}

		return baseQuery;
	}, [ searchFilter ] );

	const { records, isLoading } = useSelect(
		( select ) => {
			if ( ! isSelected || ! activePostType ) {
				return { records: EMPTY_RECORDS, isLoading: false };
			}

			const { getEntityRecords, isResolving } = select( coreStore );
			const items = getEntityRecords( 'postType', activePostType, query );

			let resultRecords = EMPTY_RECORDS;
			if ( items ) {
				resultRecords = items;
			}

			return {
				records: resultRecords,
				isLoading: isResolving( 'getEntityRecords', [
					'postType',
					activePostType,
					query,
				] ),
			};
		},
		[ isSelected, activePostType, query ],
	);

	const postOptions = useMemo( () => {
		return records.map( ( post ) => {
			let title = `#${ post.id }`;
			if ( post.title?.raw ) {
				title = post.title.raw;
			} else if ( post.title?.rendered ) {
				title = post.title.rendered;
			}

			let statusSuffix = '';
			if ( post.status && 'publish' !== post.status ) {
				statusSuffix = ` (${ post.status })`;
			}

			return {
				value: String( post.id ),
				label: `${ title }${ statusSuffix }`,
			};
		} );
	}, [ records ] );

	const handlePostTypeSelect = ( newType ) => {
		onPostTypeChange( newType );
	};

	const handleSourceChange = ( nextValue ) => {
		if ( nextValue ) {
			onSourceChange( nextValue );
		}
	};

	return (
		<div style={ { marginBottom: '16px' } }>
			<BaseControl
				label={ __( 'Date Range Source', 'gatherpress' ) }
				id="gatherpress-calendar-date-source"
			>
				<ToggleGroupControl
					__nextHasNoMarginBottom
					label={ __( 'Date Range Source', 'gatherpress' ) }
					hideLabelFromVision
					value={ activeValue }
					onChange={ handleSourceChange }
					isBlock
					isDeselectable={ false }
				>
					<ToggleGroupControlOption
						value={ DATE_SOURCE_DEFAULT }
						label={ __( 'Default', 'gatherpress' ) }
					/>
					{ hasCurrentSupport && (
						<ToggleGroupControlOption
							value={ DATE_SOURCE_CONTEXT }
							label={ __( 'Current', 'gatherpress' ) }
						/>
					) }
					<ToggleGroupControlOption
						value={ DATE_SOURCE_SELECTED }
						label={ __( 'Specific', 'gatherpress' ) }
					/>
				</ToggleGroupControl>
			</BaseControl>

			{ isSelected && (
				<div style={ { marginTop: '12px' } }>
					{ 1 < eventPostTypes.length && (
						<SelectControl
							label={ __( 'Post Type', 'gatherpress' ) }
							value={ activePostType }
							options={ eventPostTypes.map( ( type ) => ( {
								label: type.name || type.slug,
								value: type.slug,
							} ) ) }
							onChange={ handlePostTypeSelect }
						/>
					) }

					<ComboboxControl
						label={ __( 'Source Post', 'gatherpress' ) }
						value={ postId ? String( postId ) : '' }
						options={ postOptions }
						onChange={ ( val ) =>
							onPostIdChange( val ? Number( val ) : 0 )
						}
						onFilterValueChange={ setSearchFilter }
						isLoading={ isLoading }
					/>
				</div>
			) }
		</div>
	);
}
