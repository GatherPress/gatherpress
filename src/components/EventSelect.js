/**
 * WordPress dependencies
 */
import { ComboboxControl } from '@wordpress/components';
import { useMemo, useRef, useState } from '@wordpress/element';
import { useSelect } from '@wordpress/data';
import { useDebounce } from '@wordpress/compose';
import { store as coreStore } from '@wordpress/core-data';
import { decodeEntities } from '@wordpress/html-entities';
import { __ } from '@wordpress/i18n';

/**
 * How many events one search returns.
 *
 * Small on purpose: fetching them all is the problem this control solves.
 *
 * @since 0.36.0
 *
 * @type {number}
 */
const PER_PAGE = 10;

/**
 * Stands in for a post type that has not resolved yet.
 *
 * Shared so an unresolved type keeps its reference between renders.
 *
 * @since 0.36.0
 *
 * @type {Object[]}
 */
const EMPTY_RECORDS = [];

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
	// The default `view` context returns published posts only, which hides
	// the drafts an organizer is filtering RSVPs on.
	return {
		context: 'edit',
		status: 'any',
		// `Blocks\Event_Query::rest_query()` reads an absent parameter as
		// upcoming, and RSVPs are mostly looked up after the event.
		gatherpress_event_query: 'all',
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
	// A request cannot span post types, so each one is a query.
	const types = useMemo(
		() =>
			( Array.isArray( postTypes ) ? postTypes : [ postTypes ] ).filter(
				Boolean
			),
		[ postTypes ]
	);

	// `useSelect` bails out by comparing what the mapping returned, so a
	// freshly built array would re-render on every unrelated store change.
	const flattened = useRef( { lists: [], events: [] } );

	/**
	 * Flattens per-type records, reusing the previous array when unchanged.
	 *
	 * @since 0.36.0
	 *
	 * @param {Object[][]} lists Records for each searched post type.
	 *
	 * @return {Object[]} All records, flat.
	 */
	const flattenRecords = ( lists ) => {
		const previous = flattened.current;
		const unchanged =
			previous.lists.length === lists.length &&
			previous.lists.every( ( list, index ) => list === lists[ index ] );

		if ( ! unchanged ) {
			flattened.current = { lists, events: lists.flat() };
		}

		return flattened.current.events;
	};

	const { events, selected, isResolving } = useSelect(
		( wpSelect ) => {
			const { getEntityRecord, getEntityRecords, isResolving: resolving } =
				wpSelect( coreStore );
			const query = buildEventQuery( search );

			return {
				events: flattenRecords(
					types.map(
						( type ) =>
							getEntityRecords( 'postType', type, query ) ??
							EMPTY_RECORDS
					)
				),
				// The selection's post type is unknown, so ask each; at most
				// one answers.
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
 * Shared by the RSVP screen's event filter and the Post ID Override block
 * support, so the ARIA combobox behavior lives in one place.
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
