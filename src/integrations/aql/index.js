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
import { isPostTypeSupporting } from '../../helpers/event';

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

	useEffect( () => {
		// Set GatherPress defaults when post type supports event-date
		// but event query params haven't been set yet.
		if ( isPostTypeSupporting( 'gatherpress-event-date', postType ) && ! eventQuery ) {
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
	}, [ postType, eventQuery, attributes, setAttributes ] );

	return null;
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

	const { namespace } = props.attributes;

	// Only handle AQL blocks.
	if ( 'advanced-query-loop' !== namespace ) {
		return <BlockEdit { ...props } />;
	}

	const isEvent = isPostTypeSupporting(
		'gatherpress-event-date',
		props.attributes.query?.postType
	);

	// For AQL blocks with gatherpress_event post type, add the controls panel.
	if ( isEvent ) {
		return (
			<>
				<AQLEventDefaults { ...props } />
				<BlockEdit { ...props } />
				<InspectorControls>
					<PanelBody
						title={ __(
							'Event Query Settings',
							'gatherpress'
						) }
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

addFilter(
	'editor.BlockEdit',
	'gatherpress/aql-integration',
	withAQLEventControls
);
