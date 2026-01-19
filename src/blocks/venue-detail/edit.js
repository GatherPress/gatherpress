/**
 * WordPress dependencies.
 */
import { __ } from '@wordpress/i18n';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, SelectControl } from '@wordpress/components';
import { useSelect } from '@wordpress/data';

/**
 * Internal dependencies.
 */
import { PT_VENUE } from '../../helpers/namespace';

/**
 * Render the appropriate HTML based on field type.
 *
 * @param {string} fieldType   - The type of field (address, phone, url, text).
 * @param {string} value       - The field value.
 * @param {string} placeholder - Placeholder text.
 * @param {Object} blockProps  - Block props from useBlockProps.
 *
 * @return {JSX.Element} The rendered element.
 */
const renderField = ( fieldType, value, placeholder, blockProps ) => {
	const placeholderElement = (
		<span className="gatherpress-venue-detail__placeholder">
			{ placeholder || __( 'Venue detail…', 'gatherpress' ) }
		</span>
	);

	switch ( fieldType ) {
		case 'address':
			return (
				<div { ...blockProps }>
					<address>{ value || placeholderElement }</address>
				</div>
			);

		case 'phone':
			return (
				<div { ...blockProps }>
					{ value ? (
						<a href={ `tel:${ value }` }>{ value }</a>
					) : (
						placeholderElement
					) }
				</div>
			);

		case 'url':
			return (
				<div { ...blockProps }>
					{ value ? (
						<a
							href={ value }
							target="_blank"
							rel="noopener noreferrer"
						>
							{ value }
						</a>
					) : (
						placeholderElement
					) }
				</div>
			);

		default:
			return (
				<div { ...blockProps }>
					{ value || placeholderElement }
				</div>
			);
	}
};

/**
 * Edit component for the Venue Detail block.
 *
 * @since 1.0.0
 *
 * @param {Object}   props               - Component properties.
 * @param {Object}   props.attributes    - Block attributes.
 * @param {Function} props.setAttributes - Function to set block attributes.
 * @param {Object}   props.context       - Block context.
 *
 * @return {JSX.Element} The rendered React component.
 */
const Edit = ( { attributes, setAttributes, context } ) => {
	const { placeholder, fieldType, metadata } = attributes;
	const blockProps = useBlockProps();

	// Get the venue post ID from context (provided by venue-v2 block).
	const venuePostId = context?.postId || 0;

	// Get the bound meta field name from metadata.bindings.
	const metaFieldName = metadata?.bindings?.content?.args?.key;

	// Get the meta field value from the venue post.
	const metaValue = useSelect(
		( select ) => {
			if ( ! venuePostId || ! metaFieldName ) {
				return '';
			}

			const { getEntityRecord } = select( 'core' );
			const venuePost = getEntityRecord( 'postType', PT_VENUE, venuePostId );

			if ( ! venuePost || ! venuePost.meta ) {
				return '';
			}

			return venuePost.meta[ metaFieldName ] || '';
		},
		[ venuePostId, metaFieldName ]
	);

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
			{ renderField( fieldType, metaValue, placeholder, blockProps ) }
		</>
	);
};

export default Edit;
