/**
 * WordPress dependencies
 */
import { InspectorControls } from '@wordpress/block-editor';
import { registerBlockVariation, unregisterBlockVariation } from '@wordpress/blocks';
import { PanelBody, ToggleControl } from '@wordpress/components';
import { createHigherOrderComponent } from '@wordpress/compose';
import { addFilter } from '@wordpress/hooks';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import { TAXONOMY_STATUS } from '../../../helpers/event-status';

import './style.scss';

/**
 * Event Status.
 *
 * The status an event is in is a term on the event, and core already has a
 * block that renders a post's terms. This points that block at the status
 * taxonomy rather than reimplementing it, so the status inherits every
 * typography, color and spacing control core maintains, and an event holding
 * more than one status renders them the way core renders any term list.
 *
 * @see https://developer.wordpress.org/block-editor/reference-guides/block-api/block-variations/
 */
const CORE_BLOCK = 'core/post-terms';
const CLASS_NAME = 'gatherpress-event-status';

// Unregister core's auto-generated variation for this taxonomy if it was registered.
unregisterBlockVariation( CORE_BLOCK, TAXONOMY_STATUS );

registerBlockVariation( CORE_BLOCK, {
	name: CLASS_NAME,
	title: __( 'Event Status', 'gatherpress' ),
	description: __(
		'Displays whether an event is going ahead, or has been canceled, postponed, rescheduled or moved online.',
		'gatherpress'
	),
	category: 'gatherpress',
	attributes: {
		className: CLASS_NAME,
		term: TAXONOMY_STATUS,
	},
	isActive: [ 'term' ],
	scope: [ 'block', 'inserter', 'transform' ],
} );

/**
 * Extends core/post-terms block with GatherPress event status attributes.
 *
 * @param {Object} settings Original block settings.
 * @param {string} name     Block name.
 *
 * @return {Object} Filtered block settings.
 */
function addEventStatusAttributes( settings, name ) {
	if ( CORE_BLOCK !== name ) {
		return settings;
	}

	return {
		...settings,
		attributes: {
			...settings.attributes,
			hideWhenScheduled: {
				type: 'boolean',
				default: false,
			},
		},
	};
}

addFilter(
	'blocks.registerBlockType',
	'gatherpress/event-status-attributes',
	addEventStatusAttributes
);

/**
 * Injects Status settings into core/post-terms inspector when displaying event status.
 */
const withEventStatusControls = createHigherOrderComponent( ( BlockEdit ) => {
	return ( props ) => {
		if (
			CORE_BLOCK !== props.name ||
			props.attributes?.term !== TAXONOMY_STATUS
		) {
			return <BlockEdit { ...props } />;
		}

		const { attributes, setAttributes } = props;
		const { hideWhenScheduled } = attributes;

		return (
			<>
				<BlockEdit { ...props } />
				<InspectorControls>
					<PanelBody title={ __( 'Status settings', 'gatherpress' ) }>
						<ToggleControl
							__nextHasNoMarginBottom
							label={ __( 'Hide when scheduled', 'gatherpress' ) }
							help={ __(
								'Only show the status badge when an event is canceled, postponed, rescheduled, moved online, or tentative.',
								'gatherpress'
							) }
							checked={ !! hideWhenScheduled }
							onChange={ ( value ) =>
								setAttributes( { hideWhenScheduled: value } )
							}
						/>
					</PanelBody>
				</InspectorControls>
			</>
		);
	};
}, 'withEventStatusControls' );

addFilter(
	'editor.BlockEdit',
	'gatherpress/event-status-controls',
	withEventStatusControls
);

