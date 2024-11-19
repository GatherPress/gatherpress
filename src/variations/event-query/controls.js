/**
 * WordPress dependencies
 */
import { addFilter } from '@wordpress/hooks';
import { InspectorControls } from '@wordpress/block-editor';
import { PanelBody } from '@wordpress/components';
import { __ } from '@wordpress/i18n';


import { useEffect } from '@wordpress/element';
/**
 *  Internal dependencies
 */
import { NAME } from '.';
import GPQLControls from './slots/gpql-controls';
import GPQLControlsInheritedQuery from './slots/gpql-controls-inherited-query';
import { EventCountControls } from './components/event-count-controls';
import { EventExcludeControls } from './components/event-exclude-controls';
import { EventListTypeControls } from './components/event-list-type-controls';
import { EventOffsetControls } from './components/event-offset-controls';
import { EventOrderControls } from './components/event-order-controls';
import { EventIncludeUnfinishedControls } from './components/event-include-unfinished-controls';

// import { PostDateQueryControls } from './components/post-date-query-controls';



import { isEventPostType } from '../../helpers/event';

/**
 * Determines if the active variation is this one
 *
 * @param {*} props
 * @return {boolean} Is this the correct variation?
 */
const isGatherPressQueryLoop = ( props ) => {
	const {
		attributes: { namespace },
	} = props;
	return namespace && namespace === NAME;
};

/**
 * UX helper for when using a regular query block
 * and "Event" gets selected as post type,
 * the UI changes to everything necessary for events.
 *
 * By adding the relevant attributes,
 * the block is transformed into the "Event Query" block variation.
 *
 * @param {*} props 
 * @returns 
 */
const QueryPosttypeObserver = ( props ) => {
	const { postType } = props.attributes.query;
	useEffect(() => {
		if ('gatherpress_event' === postType ) {
			const newAttributes = {
				...props.attributes,
				namespace: NAME,
				query: {
					...props.attributes.query,
					gatherpress_events_query: 'upcoming',
					include_unfinished: 1,
					order: 'asc',
					orderBy: 'datetime',
					inherit: false
				}
			};
			props.setAttributes(newAttributes);
		}
		// Dependency array, every time the postType is changed,
		//  the useEffect callback will be called.
	}, [ postType ]);
	return;
	
}


/**
 * Custom controls
 *
 * @param {*} BlockEdit
 * @return {Element} BlockEdit instance
 */
const withGatherPressQueryControls = ( BlockEdit ) => ( props ) => {
	// If this is something totally different, return early.
	if ( ! isGatherPressQueryLoop( props ) && 'core/query' !== props.name ) {
		return <BlockEdit { ...props } />;
	}
	// Regular core/query blocks should become this addition.
	if ( ! isGatherPressQueryLoop( props ) ) {
		return (
			<>
				<QueryPosttypeObserver { ...props } />
				<BlockEdit { ...props } />;
			</>
		);
	}
	// If the is the correct variation, add the custom controls.
	const isEventContext = isEventPostType();
	// If the inherit prop is false, add all the controls.
	const { attributes } = props;
	if ( attributes.query.inherit === false ) {
		return (
			<>
				<BlockEdit { ...props } />
				<InspectorControls>
					<PanelBody
						title={ __(
							'Event Query Settings',
							'gatherpress'
						) }
					>

						{/* Toggle between 'upcoming' & 'past' events. */}
						<EventListTypeControls { ...props } />
						<EventIncludeUnfinishedControls { ...props } />
						
						{ isEventContext && (
							<EventExcludeControls { ...props } />
						)}						
						<EventCountControls { ...props } />
						<EventOffsetControls { ...props } />
						<EventOrderControls { ...props } />

						{/* <PostDateQueryControls { ...props } /> */}
						<GPQLControls.Slot fillProps={ { ...props } } />
					</PanelBody>
				</InspectorControls>
			</>
		);
	}
	// Add some controls if the inherit prop is true.
	return (
		<>
			<BlockEdit { ...props } />
			<InspectorControls>
				<PanelBody
					title={ __(
						'GatherPress Query Settings',
						'gatherpress'
					) }
				>
					<EventOrderControls { ...props } />
					<GPQLControlsInheritedQuery.Slot
						fillProps={ { ...props } }
					/>
				</PanelBody>
			</InspectorControls>
		</>
	);
	
};

addFilter( 'editor.BlockEdit', 'core/query', withGatherPressQueryControls );
