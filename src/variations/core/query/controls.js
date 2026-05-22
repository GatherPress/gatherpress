/**
 * WordPress dependencies
 */
import { addFilter } from '@wordpress/hooks';
import { InspectorControls } from '@wordpress/block-editor';
import { PanelBody } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';
import { useEffect } from '@wordpress/element';
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
import { usePostTypeSupports } from '../../../helpers/event';
import { usePostTypeLabel } from '../../../helpers/editor';

/**
 * Determines if the current block instance is the GatherPress event query variation.
 *
 * @param {Object} props            - Props passed to the block's edit component.
 * @param {Object} props.attributes - Block attributes.
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
 * @return {void}
 */
const QueryPosttypeObserver = ( { attributes, setAttributes } ) => {
	const { postType } = attributes.query;
	const { namespace } = attributes;
	const supportsEventDate = usePostTypeSupports(
		'gatherpress-event-date',
		postType
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
 * @return {Element|null} The InspectorControls panel, or null when not applicable.
 */
export const EventQueryControlsPanel = ( props ) => {
	const { updateBlockAttributes } = useDispatch( 'core/block-editor' );
	const { clientId } = props;
	const gatherpressEventQuery = props.attributes?.query?.gatherpress_event_query || 'upcoming';
	const queryPostType = props.attributes?.query?.postType;
	const queryPostTypeSupportsEvents = usePostTypeSupports(
		'gatherpress-event-date',
		queryPostType
	);

	// Read the plural label so the "Block List" label reflects what the currently
	// selected post type is actually called — a custom event-supporting post type with
	// `name => 'Productions'` shows "Upcoming (or Past) Productions".
	const pluralLabel = usePostTypeLabel(
		'name',
		queryPostType,
		__( 'Events', 'gatherpress' )
	);

	// Update block name with post type label and query mode
	useEffect( () => {
		const queryLabel = ( 'upcoming' === gatherpressEventQuery )
			? __( 'Upcoming', 'gatherpress' )
			: __( 'Past', 'gatherpress' );

		let blockName = sprintf(
			/* translators: %1$s: 'Upcoming' or 'Past', %2$s: Plural post type label, e.g. "Events". */
			__( '%1$s %2$s', 'gatherpress' ),
			queryLabel,
			pluralLabel
		);

		// Unset if not a supporting post type.
		if ( ! queryPostTypeSupportsEvents ) {
			blockName = '';
		}

		updateBlockAttributes( clientId, {
			metadata: {
				name: blockName,
			},
		} );
	}, [
		queryPostTypeSupportsEvents,
		pluralLabel,
		gatherpressEventQuery,
		clientId,
		updateBlockAttributes,
	] );

	// Read the singular label so the label reflects what the currently
	// selected post type is actually called — a custom event-supporting post type with
	// `singular_name => 'Production'` shows "Production Query Settings".
	const singularLabel = usePostTypeLabel(
		'singular_name',
		queryPostType,
		__( 'Event', 'gatherpress' )
	);

	if ( ! queryPostTypeSupportsEvents ) {
		return null;
	}

	return (
		<InspectorControls>
			<PanelBody title={ sprintf(
				/* translators: %s: Singular post type label, e.g. "Event". */
				__( '%s Query Settings', 'gatherpress' ),
				singularLabel
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
