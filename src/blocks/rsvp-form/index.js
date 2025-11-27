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
import { select, dispatch, useSelect } from '@wordpress/data';

/**
 * Internal dependencies.
 */
import edit from './edit';
import metadata from './block.json';

/**
 * Add success visibility controls to block edit component.
 * Stores visibility settings on each block's metadata.gatherpressRsvpFormVisibility attribute.
 *
 * @param {Function} BlockEdit Original BlockEdit component.
 * @return {Function} Wrapped BlockEdit component.
 */
const withFormVisibilityControls = createHigherOrderComponent( ( BlockEdit ) => {
	return ( props ) => {
		const { clientId, attributes } = props;

		// Use useSelect for reactive data fetching.
		const {
			currentBlock,
			parents,
			rsvpFormParentId,
		} = useSelect(
			( selectFn ) => {
				const { getBlockParents, getBlock } = selectFn( 'core/block-editor' );
				const block = getBlock( clientId );
				const parentIds = getBlockParents( clientId );

				// Find the parent RSVP Form block.
				const formParentId = parentIds.find( ( parentId ) => {
					const parentBlock = getBlock( parentId );
					return 'gatherpress/rsvp-form' === parentBlock?.name;
				} );

				return {
					currentBlock: block,
					parents: parentIds,
					rsvpFormParentId: formParentId,
				};
			},
			[ clientId ]
		);

		// Don't show controls if this IS the RSVP Form block itself.
		if ( 'gatherpress/rsvp-form' === currentBlock?.name ) {
			// Also prevent nested RSVP Forms by hiding this block if it's inside another RSVP Form.
			const isInsideRsvpForm = parents.some( ( parentId ) => {
				const { getBlock } = select( 'core/block-editor' );
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

		// Only show controls if inside RSVP Form.
		if ( ! rsvpFormParentId ) {
			return <BlockEdit { ...props } />;
		}

		// Get current visibility from block's metadata attribute.
		const currentVisibility = attributes?.metadata?.gatherpressRsvpFormVisibility || {};
		const onSuccess = currentVisibility?.onSuccess || '';
		const whenPast = currentVisibility?.whenPast || '';

		const { updateBlockAttributes } = dispatch( 'core/block-editor' );

		// Handler to update visibility on this block's metadata.
		const updateVisibility = ( state, value ) => {
			const newMetadata = { ...( attributes.metadata || {} ) };
			const newVisibility = { ...( currentVisibility || {} ) };

			// Update the specific state.
			if ( ! value || '' === value ) {
				delete newVisibility[ state ];
			} else {
				newVisibility[ state ] = value;
			}

			// If both states are empty, remove the entire visibility object.
			if ( 0 === Object.keys( newVisibility ).length ) {
				delete newMetadata.gatherpressRsvpFormVisibility;
			} else {
				newMetadata.gatherpressRsvpFormVisibility = newVisibility;
			}

			updateBlockAttributes( clientId, {
				metadata: newMetadata,
			} );
		};

		return (
			<Fragment>
				<BlockEdit { ...props } />
				<InspectorAdvancedControls>
					<SelectControl
						label={ __( 'On Successful Submission', 'gatherpress' ) }
						help={ __(
							'Control visibility when the RSVP form is successfully submitted.',
							'gatherpress'
						) }
						value={ onSuccess }
						options={ [
							{
								label: __( 'Always visible', 'gatherpress' ),
								value: '',
							},
							{
								label: __( 'Show', 'gatherpress' ),
								value: 'show',
							},
							{
								label: __( 'Hide', 'gatherpress' ),
								value: 'hide',
							},
						] }
						onChange={ ( value ) => updateVisibility( 'onSuccess', value ) }
					/>
					<SelectControl
						label={ __( 'When Event Has Passed', 'gatherpress' ) }
						help={ __(
							'Control visibility when the event end time has passed.',
							'gatherpress'
						) }
						value={ whenPast }
						options={ [
							{
								label: __( 'Always visible', 'gatherpress' ),
								value: '',
							},
							{
								label: __( 'Show', 'gatherpress' ),
								value: 'show',
							},
							{
								label: __( 'Hide', 'gatherpress' ),
								value: 'hide',
							},
						] }
						onChange={ ( value ) => updateVisibility( 'whenPast', value ) }
					/>
				</InspectorAdvancedControls>
			</Fragment>
		);
	};
}, 'withFormVisibilityControls' );

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

// Register the filter for form visibility controls.
addFilter(
	'editor.BlockEdit',
	'gatherpress/form-visibility-controls',
	withFormVisibilityControls
);

// Prevent RSVP Form from being inserted inside another RSVP Form.
addFilter(
	'blocks.canInsertBlockType',
	'gatherpress/prevent-nested-rsvp-form',
	preventNestedRsvpFormInsertion
);
