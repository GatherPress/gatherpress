/**
 * WordPress dependencies
 */
import {
	RangeControl,
	SelectControl,
	ToggleControl,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalToggleGroupControl as ToggleGroupControl,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalToggleGroupControlOption as ToggleGroupControlOption,
} from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { useEffect } from '@wordpress/element';
import { applyFilters } from '@wordpress/hooks';
import { __, _x, sprintf } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import EventQueryControls from './slots/query-controls';
import EventInheritedQueryControls from './slots/inherited-query-controls';
import { isEventPostType, isPostTypeSupporting } from '../../../helpers/event';
import { isInFSETemplate, usePostTypeLabel } from '../../../helpers/editor';

/**
 * EventCountControls component
 *
 * Displays a RangeControl slider allowing the user to set
 * how many events to show per page in the event list.
 *
 * @param {Object}   props
 * @param {Object}   props.attributes    Block attributes.
 * @param {Function} props.setAttributes Function to update block attributes.
 *
 * @return {Element}                     RangeControl for event "per page" count.
 */
export const EventCountControls = ( { attributes, setAttributes } ) => {
	const { query: { postType, perPage, offset = 0 } = {} } = attributes;

	// Read the plural label so the label reflects what the currently
	// selected post type is actually called — a custom event-supporting post type with
	// `name => 'Productions'` shows "Productions Per Page".
	const pluralLabel = usePostTypeLabel(
		'name',
		postType,
		__( 'Events', 'gatherpress' ),
	);

	return (
		<RangeControl
			__next40pxDefaultSize
			label={ sprintf(
			/* translators: %s: Plural post type label, e.g. "Events". */
				__( '%s Per Page', 'gatherpress' ),
				pluralLabel,
			) }
			min={ 1 }
			max={ 50 }
			onChange={ ( newCount ) => {
				setAttributes( {
					query: {
						...attributes.query,
						perPage: newCount,
						offset,
					},
				} );
			} }
			value={ perPage }
		/>
	);
};

/**
 * EventExcludeControls component
 *
 * Renders a ToggleControl to allow the editor to exclude
 * the current event from the query results.
 *
 * Looks up the current post's ID and updates the `exclude_current`
 * query param accordingly.
 *
 * @param {Object}   props
 * @param {Object}   props.attributes    Block attributes.
 * @param {Function} props.setAttributes Function to update block attributes.
 *
 * @return {Element}                        ToggleControl to exclude current event.
 */
export const EventExcludeControls = ( { attributes, setAttributes } ) => {
	const { query: { postType, exclude_current: excludeCurrent } = {} } = attributes;

	const currentPost = useSelect( ( select ) => {
		return select( 'core/editor' ).getCurrentPost();
	}, [] );

	// Read the singular label so the label reflects what the currently
	// selected post type is actually called — a custom event-supporting post type with
	// `singular_name => 'Production'` shows "Exclude Current Production".
	const singularLabel = usePostTypeLabel(
		'singular_name',
		postType,
		__( 'Event', 'gatherpress' ),
	);

	if ( ! currentPost ) {
		return <div>{ __( 'Loading…', 'gatherpress' ) }</div>;
	}

	return (
		<ToggleControl
			label={ sprintf(
				/* translators: %s: Singular post type label, e.g. "Event". */
				__( 'Exclude Current %s', 'gatherpress' ),
				singularLabel,
			) }
			checked={ !! excludeCurrent }
			onChange={ ( value ) => {
				setAttributes( {
					query: {
						...attributes.query,
						exclude_current: value ? currentPost.id : 0,
					},
				} );
			} }
		/>
	);
};

/**
 * EventIncludeUnfinishedControls component
 *
 * Shows a ToggleControl to let the editor include events
 * that have started but not ended yet (unfinished events).
 * Updates the `include_unfinished` query param in block attributes.
 *
 * @param {Object}   props
 * @param {Object}   props.attributes    Block attributes.
 * @param {Function} props.setAttributes Function to update block attributes.
 *
 * @return {Element}                        ToggleControl for unfinished events.
 */
export const EventIncludeUnfinishedControls = ( {
	attributes,
	setAttributes,
} ) => {
	const {
		query: {
			postType,
			include_unfinished: includeUnfinished,
			gatherpress_event_query: eventListType = 'upcoming',
		} = {},
	} = attributes;

	// Determine the effective value based on defaults:
	// - For upcoming events: default to true (include currently running events)
	// - For past events: default to false (exclude currently running events)
	// If explicitly set to 1 or 0, use that value
	// Note: We need to check against undefined specifically, not just truthy/falsy
	let effectiveValue;
	if ( undefined === includeUnfinished ) {
		// Not explicitly set, use defaults based on event type
		effectiveValue = ( 'upcoming' === eventListType );
	} else {
		// Explicitly set to 1 or 0 (integers)
		effectiveValue = ( 1 === includeUnfinished );
	}

	// Read the plural label so the label reflects what the currently
	// selected post type is actually called — a custom event-supporting post type with
	// `name => 'Productions'` shows "Include Unfinished Productions".
	const pluralLabel = usePostTypeLabel(
		'name',
		postType,
		__( 'Events', 'gatherpress' ),
	);

	return (
		<ToggleControl
			label={ sprintf(
				/* translators: %s: Plural post type label, e.g. "Events". */
				__( 'Include Unfinished %s', 'gatherpress' ),
				pluralLabel,
			) }
			help={ sprintf(
				/* translators: %1$s: 'upcoming' or 'past', %2$s: Plural post type label */
				_x(
					'%1$s %2$s that have started but are not yet finished.',
					"'Shows' or 'Hides'",
					'gatherpress',
				),
				effectiveValue
					? __( 'Shows', 'gatherpress' )
					: __( 'Hides', 'gatherpress' ),
				pluralLabel,
			) }
			checked={ effectiveValue }
			onChange={ ( value ) => {
				const newValue = value ? 1 : 0;
				setAttributes( {
					query: {
						...attributes.query,
						include_unfinished: newValue,
					},
				} );
			} }
		/>
	);
};

/**
 * EventListTypeControls component
 *
 * Lets the editor choose whether the query returns "upcoming" or "past" events.
 * Uses a ToggleGroupControl with "Upcoming" and "Past" options,
 * stored as `gatherpress_event_query` in attributes.
 *
 * @param {Object}   props
 * @param {Object}   props.attributes    Block attributes.
 * @param {Function} props.setAttributes Function to update block attributes.
 *
 * @return {Element}                     ToggleGroupControl for event list type.
 */
export const EventListTypeControls = ( { attributes, setAttributes } ) => {
	const {
		query: { postType, gatherpress_event_query: eventListType = 'upcoming' } = {},
	} = attributes;

	// Read the singular label so the label reflects what the currently
	// selected post type is actually called — a custom event-supporting post type with
	// `singular_name => 'Production'` shows "Production List Type".
	const singularLabel = usePostTypeLabel(
		'singular_name',
		postType,
		__( 'Event', 'gatherpress' ),
	);

	return (
		<ToggleGroupControl
			label={ sprintf(
				/* translators: %s: Singular post type label, e.g. "Event". */
				__( '%s List Type', 'gatherpress' ),
				singularLabel,
			) }
			value={ eventListType }
			isBlock
			__next40pxDefaultSize
			onChange={ ( newEventType ) => {
				// When switching event type, reset related defaults so the
				// query immediately makes sense for the new type.
				const isUpcoming = 'upcoming' === newEventType;
				const updatedQuery = {
					...attributes.query,
					gatherpress_event_query: newEventType,
					include_unfinished: isUpcoming ? 1 : 0,
				};

				// Only reset the sort direction when ordering by Event Date,
				// so we don't override a manually chosen order for other fields.
				if ( 'datetime' === attributes.query?.orderBy ) {
					updatedQuery.order = isUpcoming ? 'asc' : 'desc';
				}

				setAttributes( { query: updatedQuery } );
			} }
		>
			<ToggleGroupControlOption
				value="upcoming"
				label={ __( 'Upcoming', 'gatherpress' ) }
			/>
			<ToggleGroupControlOption
				value="past"
				label={ __( 'Past', 'gatherpress' ) }
			/>
		</ToggleGroupControl>
	);
};

/**
 * Resolve the post being edited, and whether its type is a shadow source.
 *
 * Block context wins when it is there; otherwise the editor store answers.
 * A shadow-source host is one whose post type declares
 * `gatherpress-shadow-source`, and the toggle's label and the query's source
 * both follow it. Anything else falls back to venues, the most common way to
 * scope events by source.
 *
 * @since TBD
 *
 * @param {Object} context Block context, including post type and ID.
 *
 * @return {Object} `editorPostId`, `editorPostType` and `editorIsShadowSource`.
 */
const useShadowSourceEditor = ( context ) => {
	const fallbackPostId = useSelect( ( wpSelect ) => wpSelect( 'core/editor' )?.getCurrentPostId(), [] );
	const fallbackPostType = useSelect( ( wpSelect ) => wpSelect( 'core/editor' )?.getCurrentPostType(), [] );
	const editorPostId = context?.postId || fallbackPostId;
	const editorPostType = context?.postType || fallbackPostType;

	const editorPostTypeSupports = useSelect(
		( wpSelect ) =>
			editorPostType
				? wpSelect( 'core' ).getPostType( editorPostType )?.supports
				: null,
		[ editorPostType ],
	);

	return {
		editorPostId,
		editorPostType,
		editorIsShadowSource: !! editorPostTypeSupports?.[ 'gatherpress-shadow-source' ],
	};
};

/**
 * Keep the query's shadow-source attributes in step with the post being edited.
 *
 * Backfills whenever the filter is on but the IDs don't match the current
 * editor post. Catches three real-world scenarios that otherwise let the
 * editor render an unscoped query briefly (every event in the DB, including
 * ones outside the current source) before the user notices:
 *
 *   1. Blocks saved before the rename to `gatherpress_shadow_source_post_*`,
 *      where the saved markup carries `null` for those keys, so the first
 *      REST request lacks the context and the resolver gives up.
 *   2. Toggles fired while the `core/editor` data store is still hydrating,
 *      where `editorPostId` is `undefined` at the moment `onChange` runs, so
 *      the attrs get written as `null` and stay that way until the user
 *      toggles a second time.
 *   3. Reloads where Gutenberg restored `shadow_filter: 1` but the
 *      shadow-source attrs were never persisted in the first place.
 *
 * Only runs on shadow-source editor post types so non-shadow contexts
 * (templates, venue pages with the standard venue subsystem) behave as
 * before.
 *
 * This is data integrity rather than UI, which is why it is a hook of its
 * own: the toggle can be filtered out of the panel, and a block that already
 * has the filter on still needs its source kept current.
 *
 * @since TBD
 *
 * @param {Object}   editor        Result of `useShadowSourceEditor()`.
 * @param {Object}   attributes    Block attributes.
 * @param {Function} setAttributes Function to update block attributes.
 * @param {boolean}  enabled       Whether to sync at all.
 *
 * @return {void}
 */
const useShadowSourceSync = ( editor, attributes, setAttributes, enabled ) => {
	const { editorPostId, editorPostType, editorIsShadowSource } = editor;
	const queryShadowId = attributes.query?.gatherpress_shadow_source_post_id;
	const queryShadowType = attributes.query?.gatherpress_shadow_source_post_type;
	const needsBackfill =
		enabled &&
		!! attributes.query?.shadow_filter &&
		editorIsShadowSource &&
		!! editorPostId &&
		!! editorPostType &&
		( queryShadowId !== editorPostId || queryShadowType !== editorPostType );

	useEffect( () => {
		if ( ! needsBackfill ) {
			return;
		}
		setAttributes( {
			query: {
				...attributes.query,
				gatherpress_shadow_source_post_id: editorPostId,
				gatherpress_shadow_source_post_type: editorPostType,
			},
		} );
	}, [
		needsBackfill,
		editorPostId,
		editorPostType,
		attributes.query,
		setAttributes,
	] );
};

/**
 * Runs the shadow-source sync without rendering anything.
 *
 * Mounted by the Event Query Loop panel alongside its filtered controls
 * rather than as one of them, so filtering the toggle out removes the toggle
 * and nothing else.
 *
 * @since TBD
 *
 * @param {Object}   props
 * @param {Object}   props.context       Block context, including post type and ID.
 * @param {Object}   props.attributes    Block attributes.
 * @param {Function} props.setAttributes Function to update block attributes.
 *
 * @return {null} Renders nothing.
 */
const ShadowSourceSync = ( { context, attributes, setAttributes } ) => {
	useShadowSourceSync(
		useShadowSourceEditor( context ),
		attributes,
		setAttributes,
		true,
	);

	return null;
};

/**
 * ShadowSourceFilterControls component
 *
 * Renders a ToggleControl to filter the event query by the
 * current shadow-source context. When enabled on a shadow-source page, only
 * events associated with that shadow-source are shown. When not on a
 * shadow-source page, the filter is gracefully ignored.
 *
 * In a template/template-part context, the editor has no
 * current shadow-source to bind to — the toggle is still relevant
 * because the template will be applied to shadow-source posts at
 * render time, but the help copy reflects the deferred binding.
 *
 * @param {Object}   props
 * @param {Object}   props.context           Block context, including post type and ID.
 * @param {Object}   props.attributes        Block attributes.
 * @param {Function} props.setAttributes     Function to update block attributes.
 * @param {boolean}  props.inTemplateContext Whether the host editor is a template or template part.
 * @param {boolean}  props.syncShadowSource  Whether this toggle keeps the query's source current
 *                                           itself. Defaults to true; the Event Query Loop panel
 *                                           passes false because it syncs independently.
 *
 * @return {Element}                          ToggleControl for shadow-source filtering.
 */
export const ShadowSourceFilterControls = ( {
	context,
	attributes,
	setAttributes,
	inTemplateContext = false,
	syncShadowSource = true,
} ) => {
	const {
		query: { shadow_filter: ShadowFilter } = {},
	} = attributes;

	const editor = useShadowSourceEditor( context );
	const { editorPostId, editorPostType, editorIsShadowSource } = editor;

	// GatherPress's own panel mounts ShadowSourceSync separately, so it can
	// switch this off here and not sync twice. Rendered anywhere else, the
	// toggle keeps its own sync.
	useShadowSourceSync( editor, attributes, setAttributes, syncShadowSource );

	const sourcePostType = editorIsShadowSource
		? editorPostType
		: 'gatherpress_venue';

	// Read the singular label so the label reflects what the currently
	// selected post type is actually called — a re-named gatherpress_venue post type with
	// `singular_name => 'Location'` shows "Filter by Current Location".
	const pluralQueryLabel = usePostTypeLabel(
		'name',
		attributes?.query?.postType,
		__( 'Events', 'gatherpress' ),
	);

	const singularLabel = usePostTypeLabel(
		'singular_name',
		sourcePostType,
		__( 'Venue', 'gatherpress' ),
	);

	const helpText = inTemplateContext
		? __(
			'The filter only takes effect when this template renders on a shadow-source page (venue, tour, production, etc.).',
			'gatherpress',
		)
		: sprintf(
			/* translators: 1: Singular post type label, e.g. "Venue", 2: Plural post type label, e.g. "Events" */
			__(
				'When placed inside %1$s context, only shows %2$s tied to that %1$s.',
				'gatherpress',
			),
			singularLabel,
			pluralQueryLabel,
		);

	return (
		<ToggleControl
			label={ sprintf(
				/* translators: %s: Singular post type label, e.g. "Venue". */
				__( 'Filter by Current %s', 'gatherpress' ),
				singularLabel,
			) }
			help={ helpText }
			checked={ !! ShadowFilter }
			onChange={ ( value ) => {
				// Pass editor's current page through to REST so the preview
				// matches what the runtime template will render against. The
				// runtime path uses `is_singular()` and ignores these — they
				// only matter for the REST-driven editor preview.
				const contextPostId =
					value && editorIsShadowSource ? editorPostId : null;
				const contextPostType =
					value && editorIsShadowSource ? editorPostType : null;

				setAttributes( {
					query: {
						...attributes.query,
						shadow_filter: value ? 1 : 0,
						gatherpress_shadow_source_post_id: contextPostId,
						gatherpress_shadow_source_post_type: contextPostType,
					},
				} );
			} }
		/>
	);
};

/**
 * EventOffsetControls component
 *
 * Provides a RangeControl for defining the query's result offset,
 * i.e., the amount of posts to skip (for pagination or similar).
 *
 * @param {Object}   props
 * @param {Object}   props.attributes    Block attributes.
 * @param {Function} props.setAttributes Function to update block attributes.
 *
 * @return {Element}                        RangeControl for event query offset.
 */
export const EventOffsetControls = ( { attributes, setAttributes } ) => {
	const { query: { postType, offset = 0 } = {} } = attributes;

	// Read the singular label so the label reflects what the currently
	// selected post type is actually called — a custom event-supporting post type with
	// `singular_name => 'Production'` shows "Production Offset".
	const singularLabel = usePostTypeLabel(
		'singular_name',
		postType,
		__( 'Event', 'gatherpress' ),
	);

	return (
		<RangeControl
			__next40pxDefaultSize
			label={ sprintf(
				/* translators: %s: Singular post type label, e.g. "Event". */
				__( '%s Offset', 'gatherpress' ),
				singularLabel,
			) }
			min={ 0 }
			max={ 50 }
			value={ offset }
			onChange={ ( newOffset ) => {
				setAttributes( {
					query: {
						...attributes.query,
						offset: newOffset,
					},
				} );
			} }
		/>
	);
};

/**
 * EventOrderControls component
 *
 * Allows user to select the order and ordering field for event query results.
 * Provides a SelectControl for the orderBy (field to sort by) and a ToggleControl
 * for the ordering direction (ascending/descending).
 *
 * @param {Object}   props
 * @param {Object}   props.attributes    Block attributes.
 * @param {Function} props.setAttributes Function to update block attributes.
 *
 * @return {Element}                        Controls for event sorting and order.
 */
export const EventOrderControls = ( { attributes, setAttributes } ) => {
	const { query: { postType, order, orderBy } = {} } = attributes;
	let label;
	if ( 'rand' === orderBy ) {
		label = __( 'Random Order', 'gatherpress' );
	} else if ( 'asc' === order ) {
		label = __( 'Ascending Order', 'gatherpress' );
	} else {
		label = __( 'Descending Order', 'gatherpress' );
	}

	// Read the singular label so the label reflects what the currently
	// selected post type is actually called — a custom event-supporting post type with
	// `singular_name => 'Production'` shows "Production Date".
	const singularLabel = usePostTypeLabel(
		'singular_name',
		postType,
		__( 'Event', 'gatherpress' ),
	);

	// Read the plural label so the label reflects what the currently
	// selected post type is actually called — a custom event-supporting post type with
	// `name => 'Productions'` shows "Order Productions by".
	const pluralLabel = usePostTypeLabel(
		'name',
		postType,
		__( 'Events', 'gatherpress' ),
	);

	return (
		<>
			<SelectControl
				__next40pxDefaultSize
				label={ sprintf(
					/* translators: %s: Plural post type label, e.g. "Events". */
					__( 'Order %s by', 'gatherpress' ),
					pluralLabel,
				) }
				value={ orderBy }
				options={ [
					{
						label: sprintf(
							/* translators: %s: Singular post type label, e.g. "Event". */
							__( '%s Date', 'gatherpress' ),
							singularLabel,
						),
						value: 'datetime', // This is GatherPress specific, a normal post would use 'date'.
					},
					{
						label: __( 'Last Modified Date', 'gatherpress' ),
						value: 'modified',
					},
					{
						label: __( 'Title', 'gatherpress' ),
						value: 'title',
					},
					{
						label: __( 'Random', 'gatherpress' ),
						value: 'rand',
					},
					{
						label: __( 'Post ID', 'gatherpress' ),
						value: 'id',
					},
				] }
				onChange={ ( newOrderBy ) => {
					setAttributes( {
						query: {
							...attributes.query,
							orderBy: newOrderBy,
						},
					} );
				} }
			/>
			<ToggleControl
				label={ label }
				checked={ 'asc' === order }
				disabled={ 'rand' === orderBy }
				onChange={ () => {
					setAttributes( {
						query: {
							...attributes.query,
							order: 'asc' === order ? 'desc' : 'asc',
						},
					} );
				} }
			/>
		</>
	);
};

/**
 * Whether a value is something React can render as a component.
 *
 * A function covers function and class components. `memo`, `forwardRef` and
 * `lazy` hand back objects tagged with `$$typeof`, so a `typeof` check alone
 * would turn those away.
 *
 * @since TBD
 *
 * @param {*} value The candidate.
 *
 * @return {boolean} True when React can render it.
 */
const isComponent = ( value ) =>
	'function' === typeof value ||
	( 'object' === typeof value && null !== value && '$$typeof' in value );

/**
 * Render a filtered list of query controls.
 *
 * Entries a filter mangled are skipped rather than thrown, because a bad
 * return from somebody else's plugin should not take the editor down with it.
 *
 * @param {Array}  controls  Entries shaped `{ name, Component, props }`.
 * @param {Object} fillProps Block edit props handed down by the slot.
 *
 * @return {Array} The rendered controls.
 */
const renderQueryControls = ( controls, fillProps ) =>
	( Array.isArray( controls ) ? controls : [] ).map( ( control ) => {
		const { name, Component, props } = control ?? {};

		if ( ! name || ! isComponent( Component ) ) {
			return null;
		}

		return <Component key={ name } { ...fillProps } { ...props } />;
	} );

/**
 * EventQueryControlsSlotFill component
 *
 * Provides the main container for all GatherPress event query controls.
 * Renders all controls depending on the current context (such as post type),
 * wrapping them in the appropriate SlotFill for the Query Controls sidebar section.
 *
 * @return {Element} SlotFill with all event query controls for GatherPress.
 */
export const EventQueryControlsSlotFill = () => {
	const inTemplateContext = isInFSETemplate();

	return (
		<EventQueryControls>
			{ ( props ) => {
				const queryPostType = props.attributes?.query?.postType;
				const currentPostType = props?.context?.postType;

				// If the is the correct variation, add the custom controls.
				const isEventContext = isEventPostType( currentPostType );

				// Reactive gate against the host editor's post type. Templates and template
				// parts have no concrete shadow-source context to bind to, but they may render on a
				// shadow-source page later, so we keep the toggle visible there with adjusted copy.
				// On any non-shadow-source, non-template host the toggle can never apply, so we hide
				// it to remove the mental load of an option that does nothing.
				const isShadowSourceContext = isPostTypeSupporting(
					'gatherpress-shadow-source',
					currentPostType,
				);

				const showExcludeControl =
					isEventContext &&
					currentPostType &&
					queryPostType &&
					currentPostType === queryPostType;

				const showShadowSourceFilterControl =
					inTemplateContext ||
					(
						isShadowSourceContext &&
						currentPostType &&
						queryPostType &&
						currentPostType !== queryPostType
					);

				const controls = [
					{ name: 'listType', Component: EventListTypeControls },
					{
						name: 'includeUnfinished',
						Component: EventIncludeUnfinishedControls,
					},
					...( showExcludeControl
						? [ { name: 'exclude', Component: EventExcludeControls } ]
						: [] ),
					...( showShadowSourceFilterControl
						? [
							{
								name: 'shadowSourceFilter',
								Component: ShadowSourceFilterControls,
								props: { inTemplateContext, syncShadowSource: false },
							},
						]
						: [] ),
					{ name: 'count', Component: EventCountControls },
					{ name: 'offset', Component: EventOffsetControls },
					{ name: 'order', Component: EventOrderControls },
				];

				/**
				 * Filters the controls shown in the Event Query Loop panel.
				 *
				 * The list holds only what would actually render, so a
				 * context-gated control is absent rather than present and
				 * hidden. Entries are `{ name, Component, props }`, where
				 * `props` is merged over the block edit props the slot passes
				 * down. Returning a reordered, extended or shortened array all
				 * work, which is what lets a companion plugin drop controls its
				 * own layout has no use for.
				 *
				 * The inherited variant of the panel has its own filter,
				 * `gatherpress.eventInheritedQueryControls`.
				 *
				 * @since TBD
				 *
				 * Removing `shadowSourceFilter` removes the toggle only. A
				 * block that already has the filter on keeps its source in
				 * step with the post being edited either way.
				 *
				 * The filter runs for every Event Query Loop, so use the
				 * second argument to change only the blocks a plugin is
				 * responsible for.
				 *
				 * @param {Array}  controls Entries shaped `{ name, Component, props }`,
				 *                          in render order.
				 * @param {Object} props    Block edit props for the query block
				 *                          being edited, including `clientId`
				 *                          and `attributes`.
				 * @return {Array} The controls to render.
				 *
				 * @example
				 *   addFilter(
				 *     'gatherpress.eventQueryControls',
				 *     'my-calendar/trim-controls',
				 *     ( controls, { clientId } ) => {
				 *       const hasCalendar = select( blockEditorStore )
				 *         .getBlock( clientId )
				 *         ?.innerBlocks.some( ( block ) => 'my-calendar/calendar' === block.name );
				 *
				 *       return hasCalendar
				 *         ? controls.filter( ( { name } ) => 'offset' !== name )
				 *         : controls;
				 *     }
				 *   );
				 */
				const filtered = applyFilters(
					'gatherpress.eventQueryControls',
					controls,
					props,
				);

				return (
					<>
						{ showShadowSourceFilterControl && (
							<ShadowSourceSync { ...props } />
						) }
						{ renderQueryControls( filtered, props ) }
					</>
				);
			} }
		</EventQueryControls>
	);
};

/**
 * EventInheritedQueryControlsSlotFill component
 *
 * Provides a condensed container for controls used when
 * a query is "inherited" (such as for nested queries), omitting
 * some controls that are irrelevant in inherited context.
 *
 * @return {Element} SlotFill with inherited event query controls for GatherPress.
 */
export const EventInheritedQueryControlsSlotFill = () => {
	return (
		<EventInheritedQueryControls>
			{ ( props ) => {
				const controls = [
					{ name: 'listType', Component: EventListTypeControls },
					{
						name: 'includeUnfinished',
						Component: EventIncludeUnfinishedControls,
					},
					{ name: 'order', Component: EventOrderControls },
				];

				/**
				 * Filters the controls shown when the query is inherited.
				 *
				 * Same shape as `gatherpress.eventQueryControls`, kept
				 * separate because an inherited query already offers a
				 * different, shorter set and a consumer is unlikely to want
				 * one rule for both.
				 *
				 * @since TBD
				 *
				 * @param {Array}  controls Entries shaped `{ name, Component, props }`,
				 *                          in render order.
				 * @param {Object} props    Block edit props for the query block
				 *                          being edited.
				 * @return {Array} The controls to render.
				 */
				return renderQueryControls(
					applyFilters(
						'gatherpress.eventInheritedQueryControls',
						controls,
						props,
					),
					props,
				);
			} }
		</EventInheritedQueryControls>
	);
};
