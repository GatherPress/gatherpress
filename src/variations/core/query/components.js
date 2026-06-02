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
import { __, _x, sprintf } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import EventQueryControls from './slots/query-controls';
import EventInheritedQueryControls from './slots/inherited-query-controls';
import { isEventPostType, usePostTypeSupports } from '../../../helpers/event';
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
		__( 'Events', 'gatherpress' )
	);

	return (
		<RangeControl
			label={ sprintf(
			/* translators: %s: Plural post type label, e.g. "Events". */
				__( '%s Per Page', 'gatherpress' ),
				pluralLabel
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
		__( 'Event', 'gatherpress' )
	);

	if ( ! currentPost ) {
		return <div>{ __( 'Loading…', 'gatherpress' ) }</div>;
	}

	return (
		<ToggleControl
			label={ sprintf(
				/* translators: %s: Singular post type label, e.g. "Event". */
				__( 'Exclude Current %s', 'gatherpress' ),
				singularLabel
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
		__( 'Events', 'gatherpress' )
	);

	return (
		<ToggleControl
			label={ sprintf(
				/* translators: %s: Plural post type label, e.g. "Events". */
				__( 'Include Unfinished %s', 'gatherpress' ),
				pluralLabel
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
				pluralLabel
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
		__( 'Event', 'gatherpress' )
	);

	return (
		<ToggleGroupControl
			label={ sprintf(
				/* translators: %s: Singular post type label, e.g. "Event". */
				__( '%s List Type', 'gatherpress' ),
				singularLabel
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
 * @param {Object}   props.attributes        Block attributes.
 * @param {Function} props.setAttributes     Function to update block attributes.
 * @param {boolean}  props.inTemplateContext Whether the host editor is a template or template part.
 *
 * @return {Element}                          ToggleControl for shadow-source filtering.
 */
export const ShadowSourceFilterControls = ( {
	attributes,
	setAttributes,
	inTemplateContext = false,
} ) => {
	const {
		query: { shadow_filter: ShadowFilter } = {},
	} = attributes;

	// Detect if the editor's current post type is a shadow-source CPT
	// (gatherpress-shadow-source post-type-support). If yes, the filter label
	// adapts to that type — "Filter by Current Tour" / "Filter by Current
	// Production" — matching whatever the template renders against at runtime.
	// Otherwise (events, pages, templates, patterns) fall back to gatherpress_venue
	// since that's the most common scope-by-source scenario.
	const editorPostId = useSelect(
		( wpSelect ) => wpSelect( 'core/editor' )?.getCurrentPostId(),
		[]
	);
	const editorPostType = useSelect(
		( wpSelect ) => wpSelect( 'core/editor' )?.getCurrentPostType(),
		[]
	);
	const editorPostTypeSupports = useSelect(
		( wpSelect ) =>
			editorPostType
				? wpSelect( 'core' ).getPostType( editorPostType )?.supports
				: null,
		[ editorPostType ]
	);
	const editorIsShadowSource = !! editorPostTypeSupports?.[ 'gatherpress-shadow-source' ];
	const sourcePostType = editorIsShadowSource
		? editorPostType
		: 'gatherpress_venue';

	// Backfill the shadow-source context attrs whenever the toggle is on but
	// the IDs don't match the current editor post. Catches three real-world
	// scenarios that otherwise let the editor render an unscoped query
	// briefly (every event in the DB, including ones outside the current
	// source) before the user notices:
	//
	//   1. Blocks saved before the rename to `gatherpress_shadow_source_post_*`
	//      — the saved markup carries `null` for those keys, so the first
	//      REST request lacks the context and the resolver gives up.
	//   2. Toggles fired while `core/editor` data store is still hydrating —
	//      `editorPostId` is `undefined` at the moment `onChange` runs, so
	//      the attrs get written as `null` and stay that way until the user
	//      toggles a second time.
	//   3. Reloads where Gutenberg restored `shadow_filter: 1` but the
	//      shadow-source attrs were never persisted in the first place.
	//
	// Only runs on shadow-source editor post types so non-shadow contexts
	// (templates, venue pages with the standard venue subsystem) behave as
	// before.
	const queryShadowId = attributes.query?.gatherpress_shadow_source_post_id;
	const queryShadowType = attributes.query?.gatherpress_shadow_source_post_type;
	const needsBackfill =
		!! ShadowFilter &&
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

	const helpText = inTemplateContext
		? __(
			'The filter only takes effect when this template renders on a shadow-source page (venue, tour, production, etc.).',
			'gatherpress'
		)
		: __(
			'When placed on a shadow-source page, only shows events tied to that page.',
			'gatherpress'
		);

	// Read the singular label so the label reflects what the currently
	// selected post type is actually called — a re-named gatherpress_venue post type with
	// `singular_name => 'Location'` shows "Filter by Current Location".
	const singularLabel = usePostTypeLabel(
		'singular_name',
		sourcePostType,
		__( 'Venue', 'gatherpress' )
	);

	return (
		<ToggleControl
			label={ sprintf(
				/* translators: %s: Singular post type label, e.g. "Venue". */
				__( 'Filter by Current %s', 'gatherpress' ),
				singularLabel
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
		__( 'Event', 'gatherpress' )
	);

	return (
		<RangeControl
			label={ sprintf(
				/* translators: %s: Singular post type label, e.g. "Event". */
				__( '%s Offset', 'gatherpress' ),
				singularLabel
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
		__( 'Event', 'gatherpress' )
	);

	// Read the plural label so the label reflects what the currently
	// selected post type is actually called — a custom event-supporting post type with
	// `name => 'Productions'` shows "Order Productions by".
	const pluralLabel = usePostTypeLabel(
		'name',
		postType,
		__( 'Events', 'gatherpress' )
	);

	return (
		<>
			<SelectControl
				__next40pxDefaultSize
				label={ sprintf(
					/* translators: %s: Plural post type label, e.g. "Events". */
					__( 'Order %s by', 'gatherpress' ),
					pluralLabel
				) }
				value={ orderBy }
				options={ [
					{
						label: sprintf(
							/* translators: %s: Singular post type label, e.g. "Event". */
							__( '%s Date', 'gatherpress' ),
							singularLabel
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
 * EventQueryControlsSlotFill component
 *
 * Provides the main container for all GatherPress event query controls.
 * Renders all controls depending on the current context (such as post type),
 * wrapping them in the appropriate SlotFill for the Query Controls sidebar section.
 *
 * @return {Element} SlotFill with all event query controls for GatherPress.
 */
export const EventQueryControlsSlotFill = () => {
	// If the is the correct variation, add the custom controls.
	const isEventContext = isEventPostType();

	// Reactive gate against the host editor's post type. Templates and template
	// parts have no concrete shadow-source context to bind to, but they may render on a
	// shadow-source page later, so we keep the toggle visible there with adjusted copy.
	// On any non-shadow-source, non-template host the toggle can never apply, so we hide
	// it to remove the mental load of an option that does nothing.
	const isShadowSourceContext = usePostTypeSupports(
		'gatherpress-shadow-source'
	);
	const inTemplateContext = isInFSETemplate();
	const showShadowSourceFilter = isShadowSourceContext || inTemplateContext;
	const currentPostType = useSelect(
		( select ) => select( 'core/editor' ).getCurrentPostType(),
		[]
	);

	return (
		<EventQueryControls>
			{ ( props ) => {
				const queryPostType = props.attributes?.query?.postType;

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


				return (
					<>
						<EventListTypeControls { ...props } />
						<EventIncludeUnfinishedControls { ...props } />

						{ showExcludeControl && <EventExcludeControls { ...props } /> }
						{ showShadowSourceFilterControl && (
							<ShadowSourceFilterControls
								{ ...props }
								inTemplateContext={ inTemplateContext }
							/>
						) }
						<EventCountControls { ...props } />
						<EventOffsetControls { ...props } />
						<EventOrderControls { ...props } />
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
			{ ( props ) => (
				<>
					<EventListTypeControls { ...props } />
					<EventIncludeUnfinishedControls { ...props } />
					<EventOrderControls { ...props } />
				</>
			) }
		</EventInheritedQueryControls>
	);
};
