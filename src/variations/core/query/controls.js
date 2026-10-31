/**
 * WordPress dependencies
 */
import { addFilter } from '@wordpress/hooks';
import { InspectorControls } from '@wordpress/block-editor';
import { PanelBody } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { useEffect, useRef } from '@wordpress/element';
import { registerPlugin } from '@wordpress/plugins';
import { useDispatch } from '@wordpress/data';

/**
 *  Internal dependencies
 */
import { NAME } from './name';
import EventQueryControls from './slots/query-controls';
import EventInheritedQueryControls from './slots/inherited-query-controls';
import {
	EventQueryControlsSlotFill,
	EventInheritedQueryControlsSlotFill,
} from './components';
import { useIsPostTypeLoaded, usePostTypeSupports } from '../../../helpers/event';
import { usePostTypeLabel } from '../../../helpers/editor';

/**
 * Builds the block name the event query panel gives an event query loop.
 *
 * @since 0.36.1
 *
 * @param {string} eventQuery  The `gatherpress_event_query` mode, 'upcoming' or 'past'.
 * @param {string} pluralLabel Plural post type label, e.g. "Events".
 *
 * @return {string} The block name, e.g. "Upcoming Events".
 */
const getEventQueryBlockName = ( eventQuery, pluralLabel ) => {
	const queryLabel = ( 'upcoming' === eventQuery )
		? __( 'Upcoming', 'gatherpress' )
		: __( 'Past', 'gatherpress' );

	return sprintf(
		/* translators: %1$s: 'Upcoming' or 'Past', %2$s: Plural post type label, e.g. "Events". */
		__( '%1$s %2$s', 'gatherpress' ),
		queryLabel,
		pluralLabel,
	);
};

/**
 * Determines if the current block instance is the GatherPress event query variation.
 *
 * @param {Object} props            - Props passed to the block's edit component.
 * @param {Object} props.attributes - Block attributes.
 *
 * @return {boolean} True if the block's namespace matches 'gatherpress-event-query', otherwise false.
 */
const isEventQueryLoop = ( props ) => {
	const {
		attributes: { namespace },
	} = props;
	return namespace && namespace === NAME;
};

/**
 * Auto-transforms a plain `core/query` block into the GatherPress "Event
 * Query" variation when the user selects any post type that declares
 * `gatherpress-event-date` support. Reactive so the transform fires on
 * first selection of a custom event-supporting post type, not only after
 * routing through `gatherpress_event` first (#1608). Skips blocks that
 * already carry a namespace so other variations (e.g. AQL) aren't hijacked.
 *
 * @param {Object}   props
 * @param {Object}   props.attributes    - Block attributes.
 * @param {Function} props.setAttributes - Function to update block attributes.
 *
 * @return {void}
 */
const QueryPosttypeObserver = ( { attributes, setAttributes } ) => {
	const { postType } = attributes.query;
	const { namespace } = attributes;
	const supportsEventDate = usePostTypeSupports(
		'gatherpress-event-date',
		postType,
	);
	useEffect( () => {
		// Only auto-transform blocks without a namespace set.
		// Blocks with an existing namespace (e.g., 'advanced-query-loop') should not be overwritten.
		if ( supportsEventDate && ! namespace ) {
			const newAttributes = {
				...attributes,
				namespace: NAME,
				query: {
					...attributes.query,
					gatherpress_event_query: 'upcoming',
					include_unfinished: 1,
					order: 'asc',
					orderBy: 'datetime',
					inherit: false,
				},
			};
			setAttributes( newAttributes );
		}
	}, [ supportsEventDate, namespace, attributes, setAttributes ] );
};

/**
 * Renders the "Event Query Settings" panel for a GatherPress event query block.
 *
 * Extracted into its own component so the `usePostTypeSupports` hook can be
 * called unconditionally at the top of a render (Rules of Hooks) — the HOC
 * has early-return paths for non-query blocks where we don't want to read
 * supports at all.
 *
 * Hides itself when the queried post type doesn't support
 * `gatherpress-event-date`, so changing a loop's post type away from events
 * (without removing the variation) collapses the now-irrelevant panel
 * instead of leaving stale event-only controls visible.
 *
 * @param {Object} props - Block props passed through from the HOC.
 *
 * @return {Element|null} The InspectorControls panel, or null when not applicable.
 */
export const EventQueryControlsPanel = ( props ) => {
	const {
		updateBlockAttributes,
		__unstableMarkNextChangeAsNotPersistent: markNextChangeAsNotPersistent,
	} = useDispatch( 'core/block-editor' );
	const { clientId } = props;
	const gatherpressEventQuery = props.attributes?.query?.gatherpress_event_query || 'upcoming';
	const metadata = props.attributes?.metadata;
	const queryPostType = props.attributes?.query?.postType;
	const queryPostTypeSupportsEvents = usePostTypeSupports(
		'gatherpress-event-date',
		queryPostType,
	);

	// `usePostTypeSupports` is also false while the post type is still
	// loading, so both effects below wait for the post type before they
	// change the block. Without this, opening the editor could strip the
	// event query vars and the block name from an event loop (#2495).
	const isQueryPostTypeLoaded = useIsPostTypeLoaded( queryPostType );

	// Read the plural label so the "Block List" label reflects what the currently
	// selected post type is actually called — a custom event-supporting post type with
	// `name => 'Productions'` shows "Upcoming (or Past) Productions".
	const fallbackPluralLabel = __( 'Events', 'gatherpress' );
	const pluralLabel = usePostTypeLabel(
		'name',
		queryPostType,
		fallbackPluralLabel,
	);

	// The plural label of the last naming pass, so a name generated for the
	// previous post type is still recognized after a post type switch.
	const lastPluralLabel = useRef( null );

	// Update block name with post type label and query mode
	useEffect( () => {
		if ( ! isQueryPostTypeLoaded ) {
			return;
		}

		const currentName = metadata?.name ?? '';

		// Names this effect can have generated: for the current post type,
		// for the post type before a switch, and for the fallback label,
		// which an earlier version wrote while the post type was loading.
		// The names are built in the current user's locale and from the
		// current labels, so a name generated in another locale, or before a
		// site changed the label, counts as an author's name and stays.
		const generatedNames = [
			pluralLabel,
			lastPluralLabel.current,
			fallbackPluralLabel,
		]
			.filter( Boolean )
			.flatMap( ( label ) => [
				getEventQueryBlockName( 'upcoming', label ),
				getEventQueryBlockName( 'past', label ),
			] );

		lastPluralLabel.current = pluralLabel;

		// Keep a name that the author gave the block.
		if ( currentName && ! generatedNames.includes( currentName ) ) {
			return;
		}

		// Unset if not a supporting post type.
		const blockName = queryPostTypeSupportsEvents
			? getEventQueryBlockName( gatherpressEventQuery, pluralLabel )
			: '';

		if ( blockName === currentName ) {
			return;
		}

		// Keep the other metadata (for example block bindings), because
		// `updateBlockAttributes` replaces the whole `metadata` attribute.
		// An unset name drops the key, as core's rename modal does, and
		// metadata with no keys left is removed.
		const { name: removedName, ...otherMetadata } = metadata ?? {};
		const nextMetadata = blockName
			? { ...otherMetadata, name: blockName }
			: otherMetadata;

		// Naming an unnamed loop fills in a default, so it must not count
		// as an edit, or opening a loop saved without a name (for example
		// before 0.34.0, or from a pattern) shows unsaved changes. Core's
		// Query block marks its own defaults the same way. The name is
		// saved with the next save.
		if ( ! currentName ) {
			markNextChangeAsNotPersistent();
		}

		updateBlockAttributes( clientId, {
			metadata: Object.keys( nextMetadata ).length ? nextMetadata : undefined,
		} );
	}, [
		isQueryPostTypeLoaded,
		queryPostTypeSupportsEvents,
		pluralLabel,
		fallbackPluralLabel,
		gatherpressEventQuery,
		metadata,
		clientId,
		updateBlockAttributes,
		markNextChangeAsNotPersistent,
	] );

	// Strip the event-only query vars when the selected post type doesn't
	// support event dates. `orderBy: 'datetime'` (and 'rand') are added to the
	// REST orderby enum only for event-date post types, so leaving them on a
	// plain post/page/venue query makes the core endpoint reject the request
	// (rest_invalid_param) and the Query Loop spins forever (#1756). The
	// gatherpress_event_query / include_unfinished vars are harmless on those
	// endpoints but meaningless there, so drop them too. Guarded on the vars
	// still being present so this doesn't re-fire in a loop, and on the post
	// type having loaded so an event loop isn't stripped on open (#2495).
	useEffect( () => {
		if ( ! isQueryPostTypeLoaded || queryPostTypeSupportsEvents ) {
			return;
		}

		const { query } = props.attributes;
		const hasEventOnlyOrderBy =
			'datetime' === query.orderBy || 'rand' === query.orderBy;
		const hasEventOnlyVars =
			undefined !== query.gatherpress_event_query ||
			undefined !== query.include_unfinished ||
			hasEventOnlyOrderBy;

		if ( ! hasEventOnlyVars ) {
			return;
		}

		const {
			gatherpress_event_query: removedEventQuery,
			include_unfinished: removedIncludeUnfinished,
			...remainingQuery
		} = query;

		// Reset the GatherPress-only ordering to the core default the
		// posts/pages endpoint accepts; leave any other orderBy intact.
		if ( hasEventOnlyOrderBy ) {
			remainingQuery.orderBy = 'date';
			remainingQuery.order = 'desc';
		}

		updateBlockAttributes( clientId, { query: remainingQuery } );
	}, [
		isQueryPostTypeLoaded,
		queryPostTypeSupportsEvents,
		props.attributes,
		clientId,
		updateBlockAttributes,
	] );

	// Read the singular label so the label reflects what the currently
	// selected post type is actually called — a custom event-supporting post type with
	// `singular_name => 'Production'` shows "Production Query Settings".
	const singularLabel = usePostTypeLabel(
		'singular_name',
		queryPostType,
		__( 'Event', 'gatherpress' ),
	);

	if ( ! queryPostTypeSupportsEvents ) {
		return null;
	}

	return (
		<InspectorControls>
			<PanelBody title={ sprintf(
				/* translators: %s: Singular post type label, e.g. "Event". */
				__( '%s Query Settings', 'gatherpress' ),
				singularLabel,
			) }>
				{ false === props.attributes.query.inherit ? (
					<EventQueryControls.Slot
						fillProps={ { ...props } }
					/>
				) : (
					<EventInheritedQueryControls.Slot
						fillProps={ { ...props } }
					/>
				) }
			</PanelBody>
		</InspectorControls>
	);
};

/**
 * Higher Order Component (HOC) to inject GatherPress-specific controls into core/query blocks.
 *
 * - If the block is not the designated event query or a query block, returns the block unchanged.
 * - For standard query blocks, watches for post type selection to convert into an event query when needed.
 * - For GatherPress event queries, provides the relevant controls in a PanelBody within InspectorControls.
 *
 * @param {Function} BlockEdit - The Query block's BlockEdit component.
 *
 * @return {Function} Enhanced BlockEdit component.
 */
const withEventQueryControls = ( BlockEdit ) => ( props ) => {
	// Early return if block is not a query or not a supported variation.
	if ( ! isEventQueryLoop( props ) && 'core/query' !== props.name ) {
		return <BlockEdit { ...props } />;
	}
	/// If it's a generic core/query, observe for transformation to GatherPress event query.
	if ( ! isEventQueryLoop( props ) ) {
		return (
			<>
				<QueryPosttypeObserver { ...props } />
				<BlockEdit { ...props } />
			</>
		);
	}
	// For a GatherPress event query, inject the controls panel (full or inherited controls).
	return (
		<>
			<BlockEdit { ...props } />
			<EventQueryControlsPanel { ...props } />
		</>
	);
};

/**
 * Registers the withEventQueryControls HOC as a filter to extend core/query blocks
 * with custom InspectorControls for GatherPress event queries.
 */
addFilter( 'editor.BlockEdit', 'core/query', withEventQueryControls );

/**
 * Registers the Query Controls SlotFills for the plugin interface, allowing
 * the relevant GatherPress query controls and inherited controls to be displayed.
 */
registerPlugin( 'gatherpress-query-controls-slotfill', {
	render: EventQueryControlsSlotFill,
} );
registerPlugin( 'gatherpress-inherited-query-controls-slotfill', {
	render: EventInheritedQueryControlsSlotFill,
} );
