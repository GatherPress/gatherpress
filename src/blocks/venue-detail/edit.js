/**
 * WordPress dependencies.
 */
import { __ } from '@wordpress/i18n';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, SelectControl } from '@wordpress/components';

/**
 * Internal dependencies.
 */
import { useVenueData, useGeocoding, useBlockInsertion } from './hooks';
import { AddressField, PhoneField, UrlField, TextField } from './fields';

/**
 * Edit component for the Venue Detail block.
 *
 * Provides inline editing of venue meta fields with automatic
 * cross-post editing warnings (like post-title block).
 *
 * @since 1.0.0
 *
 * @param {Object}   props                   - Component properties.
 * @param {Object}   props.attributes        - Block attributes.
 * @param {Function} props.setAttributes     - Function to set block attributes.
 * @param {Object}   props.context           - Block context.
 * @param {string}   props.clientId          - Block client ID.
 * @param {Function} props.insertBlocksAfter - Function to insert blocks after this block.
 * @return {JSX.Element} The rendered React component.
 */
const Edit = ( {
	attributes,
	setAttributes,
	context,
	clientId,
	insertBlocksAfter,
} ) => {
	const { placeholder, fieldType, linkTarget, cleanUrl } = attributes;
	const blockProps = useBlockProps();

	// Get venue data and update functions.
	const {
		venuePostId,
		fieldValue,
		updateFieldValue,
		updateWebsiteUrl,
		updateVenueField,
	} = useVenueData( context, fieldType );

	// Handle geocoding for address fields.
	useGeocoding( fieldType, fieldValue, updateVenueField );

	// Handle Enter key block insertion.
	const { handleKeyDown } = useBlockInsertion( clientId, insertBlocksAfter );

	// Default placeholder text.
	const placeholderText = placeholder || __( 'Venue detail…', 'gatherpress' );

	// Check if editing is disabled (no venue context).
	const isDisabled = ! venuePostId;

	// Render the appropriate field component based on field type.
	const renderField = () => {
		const commonProps = {
			value: fieldValue,
			onChange: isDisabled ? () => {} : updateFieldValue,
			placeholder: placeholderText,
			onKeyDown: isDisabled ? () => {} : handleKeyDown,
			disabled: isDisabled,
		};

		switch ( fieldType ) {
			case 'address':
				return <AddressField { ...commonProps } />;

			case 'phone':
				return <PhoneField { ...commonProps } />;

			case 'url':
				return (
					<UrlField
						{ ...commonProps }
						onChange={ isDisabled ? () => {} : updateWebsiteUrl }
						linkTarget={ linkTarget }
						cleanUrl={ cleanUrl }
						setAttributes={ setAttributes }
					/>
				);

			default:
				return <TextField { ...commonProps } />;
		}
	};

	return (
		<>
			<InspectorControls>
				<PanelBody title={ __( 'Field settings', 'gatherpress' ) }>
					<SelectControl
						label={ __( 'Field type', 'gatherpress' ) }
						value={ fieldType }
						options={ [
							{
								label: __( 'Text', 'gatherpress' ),
								value: 'text',
							},
							{
								label: __( 'Address', 'gatherpress' ),
								value: 'address',
							},
							{
								label: __( 'Phone', 'gatherpress' ),
								value: 'phone',
							},
							{
								label: __( 'URL', 'gatherpress' ),
								value: 'url',
							},
						] }
						onChange={ ( value ) =>
							setAttributes( { fieldType: value } )
						}
						help={ __(
							'Choose how this field should be displayed and formatted.',
							'gatherpress'
						) }
					/>
				</PanelBody>
			</InspectorControls>
			<div { ...blockProps }>{ renderField() }</div>
		</>
	);
};

export default Edit;
