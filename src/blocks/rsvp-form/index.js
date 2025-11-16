/**
 * WordPress dependencies.
 */
import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps, InnerBlocks, InspectorAdvancedControls } from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';
import { SelectControl } from '@wordpress/components';
import { createHigherOrderComponent } from '@wordpress/compose';
import { Fragment, useEffect } from '@wordpress/element';
import { addFilter } from '@wordpress/hooks';
import { select, dispatch } from '@wordpress/data';

/**
 * Internal dependencies.
 */
import edit from './edit';
import metadata from './block.json';

/**
 * Add gatherpressRsvpFormVisibility attribute to all blocks.
 *
 * @param {Object} settings Block settings.
 * @return {Object} Modified settings.
 */
function addFormVisibilityAttribute( settings ) {
	// Only add to blocks that don't already have this attribute.
	if ( settings?.attributes?.gatherpressRsvpFormVisibility ) {
		return settings;
	}

	return {
		...settings,
		attributes: {
			...( settings.attributes || {} ),
			gatherpressRsvpFormVisibility: {
				type: 'string',
				enum: [ 'default', 'showOnSuccess', 'hideOnSuccess' ],
				default: 'default',
			},
		},
	};
}

/**
 * Add success visibility controls to block edit component.
 *
 * @param {Function} BlockEdit Original BlockEdit component.
 * @return {Function} Wrapped BlockEdit component.
 */
const withFormVisibilityControls = createHigherOrderComponent( ( BlockEdit ) => {
	return ( props ) => {
		const { attributes, setAttributes, clientId } = props;
		const { gatherpressRsvpFormVisibility } = attributes;

		// Check if this block is inside an RSVP Form (but not the RSVP Form itself).
		const { getBlockParents, getBlock } = wp.data.select( 'core/block-editor' );
		const currentBlock = getBlock( clientId );
		const parents = getBlockParents( clientId );

		// Don't show controls if this IS the RSVP Form block itself.
		if ( 'gatherpress/rsvp-form' === currentBlock?.name ) {
			// Also prevent nested RSVP Forms by hiding this block if it's inside another RSVP Form.
			const isInsideRsvpForm = parents.some( ( parentId ) => {
				const parentBlock = getBlock( parentId );
				return 'gatherpress/rsvp-form' === parentBlock?.name;
			} );

			if ( isInsideRsvpForm ) {
				// Remove nested RSVP Forms automatically.
				useEffect( () => {
					const { removeBlock } = dispatch( 'core/block-editor' );
					removeBlock( clientId );
				}, [ clientId ] );

				// Return null while the block is being removed.
				return null;
			}

			return <BlockEdit { ...props } />;
		}

		const isInsideRsvpForm = parents.some( ( parentId ) => {
			const parentBlock = getBlock( parentId );
			return 'gatherpress/rsvp-form' === parentBlock?.name;
		} );

		// Only show controls if inside RSVP Form.
		if ( ! isInsideRsvpForm ) {
			return <BlockEdit { ...props } />;
		}

		return (
			<Fragment>
				<BlockEdit { ...props } />
				<InspectorAdvancedControls>
					<SelectControl
						label={ __( 'Form State', 'gatherpress' ) }
						help={ __(
							'Control when this block is visible based on RSVP form state.',
							'gatherpress'
						) }
						value={ gatherpressRsvpFormVisibility || 'default' }
						options={ [
							{
								label: __( 'Always visible (default)', 'gatherpress' ),
								value: 'default',
							},
							{
								label: __( 'Show on successful form submission', 'gatherpress' ),
								value: 'showOnSuccess',
							},
							{
								label: __( 'Hide on successful form submission', 'gatherpress' ),
								value: 'hideOnSuccess',
							},
						] }
						onChange={ ( value ) =>
							setAttributes( { gatherpressRsvpFormVisibility: value } )
						}
					/>
				</InspectorAdvancedControls>
			</Fragment>
		);
	};
}, 'withFormVisibilityControls' );

/**
 * Add data attribute to blocks based on their success visibility setting.
 *
 * @param {Object} props      Block save props.
 * @param {Object} blockType  Block type.
 * @param {Object} attributes Block attributes.
 * @return {Object} Modified props.
 */
function addFormVisibilityDataAttribute( props, blockType, attributes ) {
	const { gatherpressRsvpFormVisibility } = attributes;

	if ( gatherpressRsvpFormVisibility && 'default' !== gatherpressRsvpFormVisibility ) {
		return {
			...props,
			'data-gatherpress-rsvp-form-visibility': gatherpressRsvpFormVisibility,
		};
	}

	return props;
}

/**
 * Edit component for the GatherPress RSVP Form block.
 *
 * This component renders the edit view of the GatherPress RSVP Form block.
 * The block allows visitors to RSVP to events without requiring a site account.
 * It provides a form interface for event registration and integrates with the
 * WordPress comment system for processing RSVP submissions.
 *
 * @since 1.0.0
 *
 * @return {JSX.Element} The rendered React component for editing the block.
 */
registerBlockType( metadata, {
	edit,
	save: () => {
		return (
			<div { ...useBlockProps.save() }>
				<InnerBlocks.Content />
			</div>
		);
	},
} );

/**
 * Filter blocks that can be inserted based on context.
 * Prevents RSVP Form from being inserted when already inside an RSVP Form.
 *
 * @param {boolean|Array} canInsert    Whether the block can be inserted.
 * @param {Object}        blockType    The block type being checked.
 * @param {string}        rootClientId The client ID of the parent block.
 * @return {boolean} Whether the block can be inserted.
 */
function preventNestedRsvpFormInsertion( canInsert, blockType, rootClientId ) {
	// Only check for RSVP Form blocks.
	if ( 'gatherpress/rsvp-form' !== blockType.name ) {
		return canInsert;
	}

	// Check if we're trying to insert inside any RSVP Form (at any nesting level).
	// We need to check both rootClientId and when inserting at root level.
	const { getBlockParents, getBlock, getSelectedBlockClientId } = select( 'core/block-editor' );

	// Determine which block to check parents for.
	const checkClientId = rootClientId || getSelectedBlockClientId();

	if ( checkClientId ) {
		const parents = getBlockParents( checkClientId, true ); // Include the checkClientId in the check.

		// Check if any parent (including the direct parent) is an RSVP Form.
		const isInsideRsvpForm = parents.some( ( parentId ) => {
			const parentBlock = getBlock( parentId );
			return 'gatherpress/rsvp-form' === parentBlock?.name;
		} );

		if ( isInsideRsvpForm ) {
			return false; // Prevent insertion.
		}
	}

	return canInsert;
}

// Register the filters for form visibility functionality.
addFilter(
	'blocks.registerBlockType',
	'gatherpress/form-visibility-attribute',
	addFormVisibilityAttribute
);

addFilter(
	'editor.BlockEdit',
	'gatherpress/form-visibility-controls',
	withFormVisibilityControls
);

addFilter(
	'blocks.getSaveContent.extraProps',
	'gatherpress/form-visibility-data-attribute',
	addFormVisibilityDataAttribute
);

// Prevent RSVP Form from being inserted inside another RSVP Form.
addFilter(
	'blocks.canInsertBlockType',
	'gatherpress/prevent-nested-rsvp-form',
	preventNestedRsvpFormInsertion
);
