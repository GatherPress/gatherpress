/**
 * WordPress dependencies.
 */
import { registerBlockType } from '@wordpress/blocks';
import { useBlockProps, InnerBlocks, InspectorAdvancedControls } from '@wordpress/block-editor';
import { __ } from '@wordpress/i18n';
import { SelectControl } from '@wordpress/components';
import { createHigherOrderComponent } from '@wordpress/compose';
import { Fragment } from '@wordpress/element';
import { addFilter } from '@wordpress/hooks';

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
				// Hide nested RSVP Forms by returning an empty div with an error message.
				return (
					<div style={ {
						padding: '16px',
						border: '2px dashed #cc1818',
						backgroundColor: '#fcf0f1',
						color: '#cc1818',
						borderRadius: '4px',
					} }>
						{ __( 'RSVP Forms cannot be nested inside other RSVP Forms.', 'gatherpress' ) }
					</div>
				);
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
