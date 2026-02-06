/**
 * WordPress dependencies
 */
import {
	RangeControl,
	SelectControl,
	ToggleControl,
} from '@wordpress/components';
import { useSelect } from '@wordpress/data';
import { __, _x, sprintf } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import GatherPressQueryControls from './slots/query-controls';
import GatherPressInheritedQueryControls from './slots/inherited-query-controls';
import { isEventPostType } from '../../../helpers/event';

/**
 * EventCountControls component
 *
 * Displays a RangeControl slider allowing the user to set
 * how many events to show per page in the event list.
 *
 * @param {Object}   props
 * @param {Object}   props.attributes    Block attributes.
 * @param {Function} props.setAttributes Function to update block attributes.
 * @return {Element}                     RangeControl for event "per page" count.
 */
export const EventCountControls = ( { attributes, setAttributes } ) => {
	const { query: { perPage, offset = 0 } = {} } = attributes;

	return (
		<RangeControl
			label={ __( 'Events Per Page', 'gatherpress' ) }
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
 * @return {Element}                        ToggleControl to exclude current event.
 */
export const EventExcludeControls = ( { attributes, setAttributes } ) => {
	const { query: { exclude_current: excludeCurrent } = {} } = attributes;

	const currentPost = useSelect( ( select ) => {
		return select( 'core/editor' ).getCurrentPost();
	}, [] );

	if ( ! currentPost ) {
		return <div>{ __( 'Loading…', 'gatherpress' ) }</div>;
	}

	return (
		<ToggleControl
			label={ __( 'Exclude Current Event', 'gatherpress' ) }
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
 * @return {Element}                        ToggleControl for unfinished events.
 */
export const EventIncludeUnfinishedControls = ( {
	attributes,
	setAttributes,
} ) => {
	const {
		query: {
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

	return (
		<ToggleControl
			label={ __( 'Include unfinished events', 'gatherpress' ) }
			help={ sprintf(
				/* translators: %s: 'upcoming' or 'past' */
				_x(
					'%s events that have started but are not yet finished.',
					"'Shows' or 'Hides'",
					'gatherpress',
				),
				effectiveValue
					? __( 'Shows', 'gatherpress' )
					: __( 'Hides', 'gatherpress' ),
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
 * Toggled via a ToggleControl between upcoming (future) or past (archived) events,
 * stored as `gatherpress_event_query` in attributes.
 *
 * @param {Object}   props
 * @param {Object}   props.attributes    Block attributes.
 * @param {Function} props.setAttributes Function to update block attributes.
 * @return {Element}                        ToggleControl for event list type.
 */
export const EventListTypeControls = ( { attributes, setAttributes } ) => {
	const {
		query: { gatherpress_event_query: eventListType = 'upcoming' } = {},
	} = attributes;

	const currentPost = useSelect( ( select ) => {
		return select( 'core/editor' ).getCurrentPost();
	}, [] );

	if ( ! currentPost ) {
		return <div>{ __( 'Loading…', 'gatherpress' ) }</div>;
	}

	return (
		<ToggleControl
			label={ __( 'Upcoming or past events.', 'gatherpress' ) }
			help={ sprintf(
				/* translators: %s: 'upcoming' or 'past' */
				_x(
					'Currently shows %s events.',
					"'upcoming' or 'past'",
					'gatherpress',
				),
				eventListType,
			) }
			checked={ 'upcoming' === eventListType }
			onChange={ ( value ) => {
				// When switching event type, explicitly set include_unfinished to the
				// default for the new event type to ensure WordPress recognizes the state change
				const newEventType = value ? 'upcoming' : 'past';
				const defaultIncludeUnfinished = ( 'upcoming' === newEventType ) ? 1 : 0;

				setAttributes( {
					query: {
						...attributes.query,
						gatherpress_event_query: newEventType,
						include_unfinished: defaultIncludeUnfinished,
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
 * @return {Element}                        RangeControl for event query offset.
 */
export const EventOffsetControls = ( { attributes, setAttributes } ) => {
	const { query: { offset = 0 } = {} } = attributes;
	return (
		<RangeControl
			label={ __( 'Event Offset', 'gatherpress' ) }
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
 * @return {Element}                        Controls for event sorting and order.
 */
export const EventOrderControls = ( { attributes, setAttributes } ) => {
	const { query: { order, orderBy } = {} } = attributes;
	let label;
	if ( 'rand' === orderBy ) {
		label = __( 'Random Order', 'gatherpress' );
	} else if ( 'asc' === order ) {
		label = __( 'Ascending Order', 'gatherpress' );
	} else {
		label = __( 'Descending Order', 'gatherpress' );
	}
	return (
		<>
			<SelectControl
				label={ __( 'Order Events by', 'gatherpress' ) }
				value={ orderBy }
				options={ [
					{
						label: __( 'Event Date', 'gatherpress' ),
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
 * GatherPressQueryControlsSlotFill component
 *
 * Provides the main container for all GatherPress event query controls.
 * Renders all controls depending on the current context (such as post type),
 * wrapping them in the appropriate SlotFill for the Query Controls sidebar section.
 *
 * @return {Element} SlotFill with all event query controls for GatherPress.
 */
export const GatherPressQueryControlsSlotFill = () => {
	// If the is the correct variation, add the custom controls.
	const isEventContext = isEventPostType();
	return (
		<GatherPressQueryControls>
			{ ( props ) => (
				<>
					<EventListTypeControls { ...props } />
					<EventIncludeUnfinishedControls { ...props } />

					{ isEventContext && <EventExcludeControls { ...props } /> }
					<EventCountControls { ...props } />
					<EventOffsetControls { ...props } />
					<EventOrderControls { ...props } />
				</>
			) }
		</GatherPressQueryControls>
	);
};

/**
 * GatherPressInheritedQueryControlsSlotFill component
 *
 * Provides a condensed container for controls used when
 * a query is "inherited" (such as for nested queries), omitting
 * some controls that are irrelevant in inherited context.
 *
 * @return {Element} SlotFill with inherited event query controls for GatherPress.
 */
export const GatherPressInheritedQueryControlsSlotFill = () => {
	return (
		<GatherPressInheritedQueryControls>
			{ ( props ) => (
				<>
					<EventListTypeControls { ...props } />
					<EventIncludeUnfinishedControls { ...props } />
					<EventOrderControls { ...props } />
				</>
			) }
		</GatherPressInheritedQueryControls>
	);
};
