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
 * Stores visibility settings on the parent RSVP Form block's
 * innerBlocksVisibility attribute, keyed by block clientId.
 *
 * @param {Function} BlockEdit Original BlockEdit component.
 * @return {Function} Wrapped BlockEdit component.
 */
const withFormVisibilityControls = createHigherOrderComponent( ( BlockEdit ) => {
	return ( props ) => {
		const { clientId } = props;

		// Use useSelect for reactive data fetching.
		const {
			currentBlock,
			parents,
			rsvpFormParentId,
			rsvpFormBlock,
			innerBlocksVisibility,
		} = useSelect(
			( selectFn ) => {
				const { getBlockParents, getBlock, getBlockAttributes } = selectFn( 'core/block-editor' );
				const block = getBlock( clientId );
				const parentIds = getBlockParents( clientId );

				// Find the parent RSVP Form block.
				const formParentId = parentIds.find( ( parentId ) => {
					const parentBlock = getBlock( parentId );
					return 'gatherpress/rsvp-form' === parentBlock?.name;
				} );

				const formBlock = formParentId ? getBlock( formParentId ) : null;
				const formAttributes = formParentId ? getBlockAttributes( formParentId ) : {};

				return {
					currentBlock: block,
					parents: parentIds,
					rsvpFormParentId: formParentId,
					rsvpFormBlock: formBlock,
					innerBlocksVisibility: formAttributes?.innerBlocksVisibility || {},
				};
			},
			[ clientId ]
		);

		const { updateBlockAttributes } = dispatch( 'core/block-editor' );

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

		// Find the index of this block within the RSVP Form's inner blocks.
		// Use a recursive function to handle nested blocks.
		const findBlockIndex = ( blocks, targetId, path = '' ) => {
			for ( let i = 0; i < blocks.length; i++ ) {
				const block = blocks[ i ];
				const currentPath = path ? `${ path }-${ i }` : `${ i }`;

				if ( block.clientId === targetId ) {
					return currentPath;
				}

				if ( block.innerBlocks && block.innerBlocks.length > 0 ) {
					const found = findBlockIndex( block.innerBlocks, targetId, currentPath );
					if ( found ) {
						return found;
					}
				}
			}
			return null;
		};

		const blockPath = findBlockIndex( rsvpFormBlock?.innerBlocks || [], clientId );
		const currentVisibility = blockPath ? ( innerBlocksVisibility[ blockPath ] || 'default' ) : 'default';

		// Handler to update visibility on the parent RSVP Form.
		const updateVisibility = ( value ) => {
			if ( ! blockPath ) {
				return;
			}

			const newVisibility = { ...innerBlocksVisibility };

			if ( value === 'default' ) {
				// Remove the entry if set to default.
				delete newVisibility[ blockPath ];
			} else {
				newVisibility[ blockPath ] = value;
			}

			updateBlockAttributes( rsvpFormParentId, {
				innerBlocksVisibility: newVisibility,
			} );
		};

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
						value={ currentVisibility }
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
						onChange={ updateVisibility }
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
