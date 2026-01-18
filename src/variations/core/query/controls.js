/**
 * WordPress dependencies
 */
import { addFilter } from '@wordpress/hooks';
import { InspectorControls } from '@wordpress/block-editor';
import { PanelBody } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { useEffect } from '@wordpress/element';
import { registerPlugin } from '@wordpress/plugins';

/**
 *  Internal dependencies
 */
import { NAME } from '.';
import GatherPressQueryControls from './slots/query-controls';
import GatherPressInheritedQueryControls from './slots/inherited-query-controls';
import {
	GatherPressQueryControlsSlotFill,
	GatherPressInheritedQueryControlsSlotFill,
} from './components';

/**
 * Determines if the current block instance is the GatherPress event query variation.
 *
 * @param {Object} props            - Props passed to the block's edit component.
 * @param {Object} props.attributes - Block attributes.
 * @return {boolean} True if the block's namespace matches 'gatherpress-event-query', otherwise false.
 */
const isGatherPressQueryLoop = ( props ) => {
	const {
		attributes: { namespace },
	} = props;
	return namespace && namespace === NAME;
};

/**
 * Watches the Query block's `postType` attribute and, if changed to "gatherpress_event",
 * automatically transforms it into a GatherPress "Event Query" variation by updating
 * relevant attributes.
 *
 * @param {Object}   props
 * @param {Object}   props.attributes    - Block attributes.
 * @param {Function} props.setAttributes - Function to update block attributes.
 * @return {void}
 */
const QueryPosttypeObserver = ( { attributes, setAttributes } ) => {
	const { postType } = attributes.query;
	useEffect( () => {
		if ( 'gatherpress_event' === postType ) {
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
		// Dependency array ensures this runs
		// whenever postType, attributes, or setAttributes changes.
	}, [ postType, attributes, setAttributes ] );
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
const withGatherPressQueryControls = ( BlockEdit ) => ( props ) => {
	// Early return if block is not a query or not a supported variation.
	if ( ! isGatherPressQueryLoop( props ) && 'core/query' !== props.name ) {
		return <BlockEdit { ...props } />;
	}
	/// If it's a generic core/query, observe for transformation to GatherPress event query.
	if ( ! isGatherPressQueryLoop( props ) ) {
		return (
			<>
				<QueryPosttypeObserver { ...props } />
				<BlockEdit { ...props } />;
			</>
		);
	}
	// For a GatherPress event query, inject controls panel (will show full or inherited controls).
	return (
		<>
			<BlockEdit { ...props } />
			<InspectorControls>
				<PanelBody title={ __( 'Event Query Settings', 'gatherpress' ) }>
					{ false === props.attributes.query.inherit ? (
						<GatherPressQueryControls.Slot
							fillProps={ { ...props } }
						/>
					) : (
						<GatherPressInheritedQueryControls.Slot
							fillProps={ { ...props } }
						/>
					) }
				</PanelBody>
			</InspectorControls>
		</>
	);
};

/**
 * Registers the withGatherPressQueryControls HOC as a filter to extend core/query blocks
 * with custom InspectorControls for GatherPress event queries.
 */
addFilter( 'editor.BlockEdit', 'core/query', withGatherPressQueryControls );

/**
 * Registers the Query Controls SlotFills for the plugin interface, allowing
 * the relevant GatherPress query controls and inherited controls to be displayed.
 */
registerPlugin( 'gatherpress-query-controls-slotfill', {
	render: GatherPressQueryControlsSlotFill,
} );
registerPlugin( 'gatherpress-inherited-query-controls-slotfill', {
	render: GatherPressInheritedQueryControlsSlotFill,
} );
