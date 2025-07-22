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
 * Determines if the active variation is this one
 *
 * @param {*} props
 * @return {boolean} Is this the correct variation?
 */
const isGatherPressQueryLoop = (props) => {
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
 */
const QueryPosttypeObserver = ({ attributes, setAttributes }) => {
	const { postType } = attributes.query;
	useEffect(() => {
		if ('gatherpress_event' === postType) {
			const newAttributes = {
				...attributes,
				namespace: NAME,
				query: {
					...attributes.query,
					gatherpress_events_query: 'upcoming',
					include_unfinished: 1,
					order: 'asc',
					orderBy: 'datetime',
					inherit: false,
				},
			};
			setAttributes(newAttributes);
		}
		// Dependency array, every time the postType is changed,
		// the useEffect callback will be called.
	}, [postType, attributes, setAttributes]);
};

/**
 * Custom controls
 *
 * @param {*} BlockEdit
 * @return {Element} BlockEdit instance
 */
const withGatherPressQueryControls = (BlockEdit) => (props) => {
	// If this is something totally different, return early.
	if (!isGatherPressQueryLoop(props) && 'core/query' !== props.name) {
		return <BlockEdit {...props} />;
	}
	// Regular core/query blocks should become this addition.
	if (!isGatherPressQueryLoop(props)) {
		return (
			<>
				<QueryPosttypeObserver {...props} />
				<BlockEdit {...props} />;
			</>
		);
	}
	return (
		<>
			<BlockEdit {...props} />
			<InspectorControls>
				<PanelBody title={__('Event Query Settings', 'gatherpress')}>
					{props.attributes.query.inherit === false ? (
						<GatherPressQueryControls.Slot
							fillProps={{ ...props }}
						/>
					) : (
						<GatherPressInheritedQueryControls.Slot
							fillProps={{ ...props }}
						/>
					)}
				</PanelBody>
			</InspectorControls>
		</>
	);
};

addFilter('editor.BlockEdit', 'core/query', withGatherPressQueryControls);

registerPlugin('gatherpress-query-controls-slotfill', {
	render: GatherPressQueryControlsSlotFill,
});
registerPlugin('gatherpress-inherited-query-controls-slotfill', {
	render: GatherPressInheritedQueryControlsSlotFill,
});
