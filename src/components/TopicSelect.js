/**
 * WordPress dependencies
 */
import { ComboboxControl } from '@wordpress/components';
import { useMemo, useState } from '@wordpress/element';
import { useSelect } from '@wordpress/data';
import { useDebounce } from '@wordpress/compose';
import { store as coreStore } from '@wordpress/core-data';
import { decodeEntities } from '@wordpress/html-entities';
import { __ } from '@wordpress/i18n';

/**
 * The topic taxonomy backing the feed scope of the same name.
 *
 * @since 0.36.0
 *
 * @type {string}
 */
export const TOPIC_TAXONOMY = 'gatherpress_topic';

/**
 * How many topics one search returns.
 *
 * @since 0.36.0
 *
 * @type {number}
 */
const PER_PAGE = 10;

/**
 * Stands in for records that have not resolved yet.
 *
 * Shared so an unresolved search keeps its reference between renders.
 *
 * @since 0.36.0
 *
 * @type {Object[]}
 */
const EMPTY_RECORDS = [];

/**
 * Turns topic records into combobox options.
 *
 * @since 0.36.0
 *
 * @param {Object[]} topics   Topic records from the search.
 * @param {Object}   selected The currently selected topic, if any.
 *
 * @return {Object[]} Combobox options.
 */
export function toTopicOptions( topics, selected ) {
	const toOption = ( topic ) => ( {
		value: topic.id,
		label: decodeEntities( topic.name ?? '' ) || `#${ topic.id }`,
	} );

	const options = ( topics ?? [] ).map( toOption );

	// Keep the current selection in the list when the search excludes it.
	if ( selected && ! options.some( ( { value } ) => selected.id === value ) ) {
		return [ toOption( selected ), ...options ];
	}

	return options;
}

/**
 * Search topics for a combobox, keeping the current selection visible.
 *
 * @since 0.36.0
 *
 * @param {string}        search  Current search term.
 * @param {number|string} topicId The currently selected topic ID, if any.
 *
 * @return {{topicOptions: Object[], isResolving: boolean}} Combobox options and resolution state.
 */
export function useTopicOptions( search, topicId ) {
	const { topics, selected, isResolving } = useSelect(
		( wpSelect ) => {
			const { getEntityRecord, getEntityRecords, isResolving: resolving } =
				wpSelect( coreStore );
			const query = {
				context: 'view',
				per_page: PER_PAGE,
				search,
				// The terms endpoint has no `relevance` orderby; its enum is
				// id, include, name, slug, include_slugs, term_group, description,
				// count. Anything else is a 400 and an empty picker.
				orderby: 'name',
				order: 'asc',
			};

			return {
				topics:
					getEntityRecords( 'taxonomy', TOPIC_TAXONOMY, query ) ??
					EMPTY_RECORDS,
				selected: topicId
					? getEntityRecord( 'taxonomy', TOPIC_TAXONOMY, topicId )
					: null,
				isResolving: resolving( 'getEntityRecords', [
					'taxonomy',
					TOPIC_TAXONOMY,
					query,
				] ),
			};
		},
		[ search, topicId ]
	);

	// `getEntityRecords` hands back the same array while a query is unchanged,
	// so this only rebuilds when the records or the selection actually move.
	const topicOptions = useMemo(
		() => toTopicOptions( topics, selected ),
		[ topics, selected ]
	);

	return { topicOptions, isResolving };
}

/**
 * A searchable topic picker.
 *
 * @since 0.36.0
 *
 * @param {Object}   props                     Component props.
 * @param {number}   props.value               Currently selected topic ID.
 * @param {Function} props.onChange            Called with the selected topic ID, or null when cleared.
 * @param {string}   props.label               Control label.
 * @param {string}   props.help                Optional help text below the control.
 * @param {boolean}  props.hideLabelFromVision Render the label for assistive tech only.
 *
 * @return {JSX.Element} The topic picker.
 */
export default function TopicSelect( {
	value,
	onChange,
	label = __( 'Topic', 'gatherpress' ),
	help,
	hideLabelFromVision = false,
} ) {
	const [ search, setSearch ] = useState( '' );
	const { topicOptions } = useTopicOptions( search, value );

	// Typing should not fire a request per keystroke.
	const setSearchDebounced = useDebounce( setSearch, 300 );

	return (
		<ComboboxControl
			__next40pxDefaultSize
			label={ label }
			hideLabelFromVision={ hideLabelFromVision }
			help={ help }
			value={ value || null }
			options={ topicOptions }
			onChange={ onChange }
			onFilterValueChange={ setSearchDebounced }
		/>
	);
}
