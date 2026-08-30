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
 * How many events one search returns.
 *
 * Small on purpose. The control exists because a site with hundreds of events
 * cannot use a select, so fetching them all would reintroduce the problem it
 * was built to solve.
 *
 * @since 0.36.0
 *
 * @type {number}
 */
const PER_PAGE = 10;

/**
 * Builds the entity query one event search issues.
 *
 * @since 0.36.0
 *
 * @param {string} search Current search term.
 *
 * @return {Object} The query for `getEntityRecords`.
 */
export function buildEventQuery( search ) {
	// `edit` context with every status, because the events worth picking are
	// routinely not published: an organizer filters RSVPs on a draft they are
	// still building. The default `view` context returns published posts only,
	// which hid exactly the events this control exists to find.
	return {
		context: 'edit',
		status: 'any',
		per_page: PER_PAGE,
		search,
		orderby: search ? 'relevance' : 'date',
		order: 'desc',
	};
}

/**
 * Turns event records into combobox options.
 *
 * @since 0.36.0
 *
 * @param {Object[]} events   Event records from the search.
 * @param {Object}   selected The currently selected event, if any.
 *
 * @return {Object[]} Combobox options.
 */
export function toEventOptions( events, selected ) {
	const toOption = ( event ) => ( {
		value: event.id,
		label:
			decodeEntities( event.title?.rendered ?? '' ) || `#${ event.id }`,
	} );

	const options = ( events ?? [] ).map( toOption );

	// Keep the current selection in the list when the search excludes it.
	if ( selected && ! options.some( ( { value } ) => selected.id === value ) ) {
		return [ toOption( selected ), ...options ];
	}

	return options;
}

/**
 * Search events for a combobox, keeping the current selection visible.
 *
 * @since 0.36.0
 *
 * @param {string}          search    Current search term.
 * @param {number|string}   eventId   The currently selected event ID, if any.
 * @param {string|string[]} postTypes Post type slug, or slugs, to search.
 *
 * @return {{eventOptions: Object[]}} Combobox options.
 */
export function useEventOptions( search, eventId, postTypes ) {
	// A single request cannot span post types, so searching more than one
	// means one query each. Sites have one event post type unless a
	// companion plugin adds another, so this is one request in practice.
	const types = useMemo(
		() =>
			( Array.isArray( postTypes ) ? postTypes : [ postTypes ] ).filter(
				Boolean
			),
		[ postTypes ]
	);

	const { events, selected, isResolving } = useSelect(
		( wpSelect ) => {
			const { getEntityRecord, getEntityRecords, isResolving: resolving } =
				wpSelect( coreStore );
			const query = buildEventQuery( search );

			return {
				events: types.flatMap(
					( type ) =>
						getEntityRecords( 'postType', type, query ) ?? []
				),
				// The selected event's own post type is not known here, so
				// ask each candidate; at most one answers.
				selected: types
					.map( ( type ) =>
						eventId
							? getEntityRecord( 'postType', type, eventId )
							: null
					)
					.find( Boolean ),
				isResolving: types.some( ( type ) =>
					resolving( 'getEntityRecords', [
						'postType',
						type,
						query,
					] )
				),
			};
		},
		[ types, search, eventId ]
	);

	const eventOptions = useMemo(
		() => toEventOptions( events, selected ),
		[ events, selected ]
	);

	return { eventOptions, isResolving };
}

/**
 * A searchable event picker.
 *
 * Shared by the RSVP admin screen's event filter and the Post ID Override
 * block support, which are the same question asked twice: pick an event, get
 * its ID. `ComboboxControl` carries the ARIA combobox-with-listbox behavior,
 * so the pattern lives in one place rather than being rebuilt per surface.
 *
 * @since 0.36.0
 *
 * @param {Object}          props                     Component props.
 * @param {number|string}   props.value               Currently selected event ID.
 * @param {Function}        props.onChange            Called with the selected event ID, or null when cleared.
 * @param {string|string[]} props.postTypes           Post type slug, or slugs, to search.
 * @param {string}          props.label               Control label.
 * @param {string}          props.help                Optional help text below the control.
 * @param {boolean}         props.hideLabelFromVision Render the label for assistive tech only.
 *
 * @return {JSX.Element} The event picker.
 */
export default function EventSelect( {
	value,
	onChange,
	postTypes,
	label = __( 'Event', 'gatherpress' ),
	help,
	hideLabelFromVision = false,
} ) {
	const [ search, setSearch ] = useState( '' );
	const { eventOptions } = useEventOptions( search, value, postTypes );

	// Typing should not fire a request per keystroke.
	const setSearchDebounced = useDebounce( setSearch, 300 );

	return (
		<ComboboxControl
			__next40pxDefaultSize
			label={ label }
			hideLabelFromVision={ hideLabelFromVision }
			help={ help }
			value={ value || null }
			options={ eventOptions }
			onChange={ onChange }
			onFilterValueChange={ setSearchDebounced }
		/>
	);
}
