/**
 * WordPress dependencies
 */
import { InspectorControls } from '@wordpress/block-editor';
import { PanelBody } from '@wordpress/components';
import { useEffect } from '@wordpress/element';
import { addFilter } from '@wordpress/hooks';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import {
	EventListTypeControls,
	EventIncludeUnfinishedControls,
	EventOrderControls,
} from '../../variations/core/query/components';
import { usePostTypeSupports } from '../../helpers/event';

/**
 * Component that auto-sets GatherPress event defaults when the post type
 * changes to gatherpress_event within an AQL block.
 *
 * @param {Object}   props
 * @param {Object}   props.attributes    Block attributes.
 * @param {Function} props.setAttributes Function to update block attributes.
 * @return {null} Renders nothing.
 */
const AQLEventDefaults = ( { attributes, setAttributes } ) => {
	const { postType, gatherpress_event_query: eventQuery } = attributes.query;
	// Reactive so the gate flips when the post-type registry resolves (#1608).
	const supportsEventDate = usePostTypeSupports(
		'gatherpress-event-date',
		postType,
	);

	useEffect( () => {
		// Set GatherPress defaults when post type supports event-date
		// but event query params haven't been set yet.
		if ( supportsEventDate && ! eventQuery ) {
			setAttributes( {
				query: {
					...attributes.query,
					gatherpress_event_query: 'upcoming',
					include_unfinished: 1,
					order: 'asc',
					orderBy: 'datetime',
				},
			} );
		}
	}, [ supportsEventDate, eventQuery, attributes, setAttributes ] );

	return null;
};

/**
 * Renders the AQL event controls panel and the defaults observer.
 *
 * Extracted from the HOC so `usePostTypeSupports` only runs for AQL blocks,
 * not every block in the editor. Reactive so the panel appears on first
 * selection of an event-supporting post type (#1608).
 *
 * @param {Object}   props           Block edit props plus BlockEdit injection.
 * @param {Function} props.BlockEdit The wrapped block's BlockEdit component.
 * @return {JSX.Element} Edit output augmented with event controls when applicable.
 */
const AQLBlockEdit = ( { BlockEdit, ...props } ) => {
	const isEvent = usePostTypeSupports(
		'gatherpress-event-date',
		props.attributes.query?.postType,
	);

	if ( isEvent ) {
		return (
			<>
				<AQLEventDefaults { ...props } />
				<BlockEdit { ...props } />
				<InspectorControls>
					<PanelBody
						title={ __( 'Event Query Settings', 'gatherpress' ) }
					>
						<EventListTypeControls { ...props } />
						<EventIncludeUnfinishedControls { ...props } />
						<EventOrderControls { ...props } />
					</PanelBody>
				</InspectorControls>
			</>
		);
	}

	// For AQL blocks with other post types, just observe for defaults.
	return (
		<>
			<AQLEventDefaults { ...props } />
			<BlockEdit { ...props } />
		</>
	);
};

/**
 * Higher Order Component that adds GatherPress event controls to AQL blocks
 * as a separate InspectorControls panel, rendered as a sibling to AQL's
 * "Advanced Query Settings" panel.
 *
 * @param {Function} BlockEdit The block's edit component.
 * @return {Function} Enhanced edit component.
 */
const withAQLEventControls = ( BlockEdit ) => ( props ) => {
	if ( 'core/query' !== props.name ) {
		return <BlockEdit { ...props } />;
	}

	// Only handle AQL blocks.
	if ( 'advanced-query-loop' !== props.attributes.namespace ) {
		return <BlockEdit { ...props } />;
	}

	return <AQLBlockEdit BlockEdit={ BlockEdit } { ...props } />;
};

addFilter(
	'editor.BlockEdit',
	'gatherpress/aql-integration',
	withAQLEventControls
);
