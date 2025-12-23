/**
 * WordPress dependencies.
 */
import { __ } from '@wordpress/i18n';
import { useBlockProps, InspectorControls } from '@wordpress/block-editor';
import {
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalNumberControl as NumberControl,
	PanelBody,
	SelectControl,
	TextControl,
	ToggleControl,
} from '@wordpress/components';

/**
 * Internal dependencies.
 */
import DefaultField from './types/default';
import RadioField from './types/radio';
import CheckboxField from './types/checkbox';
import TextareaField from './types/textarea';
import HiddenField from './types/hidden';
import DefaultFieldPanels from './panels/default-field-panels';
import RadioFieldPanels from './panels/radio-field-panels';
import CheckboxFieldPanels from './panels/checkbox-field-panels';
import FieldValue from './helpers';

/**
 * Edit component for the Form Field block.
 *
 * @param {Object}   props               The block props.
 * @param {Object}   props.attributes    The block attributes.
 * @param {Function} props.setAttributes Function to set block attributes.
 * @return {JSX.Element} The edit component.
 */
export default function Edit( { attributes, setAttributes } ) {
	const {
		fieldType,
		fieldName,
		minValue,
		maxValue,
		placeholder,
		required,
		autocomplete,
	} = attributes;

	// Handle data attributes for conditional rendering.
	const additionalProps = {};
	if ( attributes[ 'data-gatherpress-no-render' ] ) {
		additionalProps[ 'data-gatherpress-no-render' ] = attributes[ 'data-gatherpress-no-render' ];
	}

	const blockProps = useBlockProps( additionalProps );

	/**
	 * Generate field name from label.
	 *
	 * @param {string} labelValue The label value to convert.
	 * @return {string} The generated field name.
	 */
	const generateFieldName = ( labelValue ) => {
		return labelValue
			.toLowerCase()
			.trim()
			.replaceAll( /[^a-z0-9\s]/g, '' ) // Remove special characters.
			.split( /\s+/ ) // Split on whitespace sequences.
			.filter( ( part ) => 0 < part.length ) // Remove empty strings.
			.join( '_' ); // Join with underscores.
	};

	/**
	 * Get the default autocomplete value based on field type.
	 *
	 * @param {string} value - The field type.
	 * @return {string} The default autocomplete value.
	 */
	const getDefaultAutocomplete = ( value ) => {
		switch ( value ) {
			case 'email':
				return 'email';
			case 'url':
				return 'url';
			case 'tel':
				return 'tel';
			default:
				return 'on';
		}
	};

	/**
	 * Get the appropriate field component based on field type.
	 *
	 * @return {JSX.Element} The field component.
	 */
	const getFieldComponent = () => {
		const commonProps = {
			attributes,
			setAttributes,
			blockProps,
			generateFieldName,
		};

		switch ( fieldType ) {
			case 'radio':
				return <RadioField { ...commonProps } />;
			case 'checkbox':
				return <CheckboxField { ...commonProps } />;
			case 'textarea':
				return <TextareaField { ...commonProps } />;
			case 'hidden':
				return <HiddenField { ...commonProps } />;
			default:
				return <DefaultField { ...commonProps } />;
		}
	};

	/**
	 * Get field-specific panels based on field type.
	 *
	 * @return {JSX.Element} The field-specific panels.
	 */
	const getFieldPanels = () => {
		const commonProps = { attributes, setAttributes };

		switch ( fieldType ) {
			case 'radio':
				return <RadioFieldPanels { ...commonProps } />;
			case 'checkbox':
				return <CheckboxFieldPanels { ...commonProps } />;
			case 'hidden':
				return <></>;
			default:
				return <DefaultFieldPanels { ...commonProps } />;
		}
	};

	return (
		<>
			{ getFieldComponent() }
			<InspectorControls>
				<PanelBody title={ __( 'Field Settings', 'gatherpress' ) }>
					<SelectControl
						label={ __( 'Field Type', 'gatherpress' ) }
						value={ fieldType }
						options={ [
							{ label: __( 'Text', 'gatherpress' ), value: 'text' },
							{
								label: __( 'Email', 'gatherpress' ),
								value: 'email',
							},
							{
								label: __( 'Telephone', 'gatherpress' ),
								value: 'tel',
							},
							{ label: __( 'URL', 'gatherpress' ), value: 'url' },
							{
								label: __( 'Number', 'gatherpress' ),
								value: 'number',
							},
							{
								label: __( 'Textarea', 'gatherpress' ),
								value: 'textarea',
							},
							{
								label: __( 'Checkbox', 'gatherpress' ),
								value: 'checkbox',
							},
							{
								label: __( 'Radio', 'gatherpress' ),
								value: 'radio',
							},
							{
								label: __( 'Hidden', 'gatherpress' ),
								value: 'hidden',
							},
						] }
						onChange={ ( value ) => {
							setAttributes( {
								fieldType: value,
								fieldValue: '', // Reset fieldValue when type changes.
								autocomplete: getDefaultAutocomplete( value ),
							} );
						} }
					/>
					<TextControl
						label={ __( 'Field Name', 'gatherpress' ) }
						value={ fieldName }
						onChange={ ( value ) => {
							// Only allow alphanumeric, underscore, and hyphen.
							const sanitized = value.replaceAll(
								/[^a-zA-Z0-9_-]/g,
								'',
							);

							setAttributes( { fieldName: sanitized } );
						} }
						help={ __(
							'The name attribute for the form field.',
							'gatherpress',
						) }
					/>

					{ 'hidden' !== fieldType && (
						<ToggleControl
							label={ __( 'Required', 'gatherpress' ) }
							checked={ required }
							onChange={ ( value ) =>
								setAttributes( { required: value } )
							}
							help={ __(
								'Make this field required.',
								'gatherpress',
							) }
						/>
					) }

					<FieldValue
						fieldType={ fieldType }
						attributes={ attributes }
						setAttributes={ setAttributes }
					/>

					{ ! [ 'hidden', 'checkbox', 'radio' ].includes( fieldType ) && (
						<>
							<TextControl
								label={ __( 'Placeholder', 'gatherpress' ) }
								value={ placeholder }
								onChange={ ( value ) =>
									setAttributes( { placeholder: value } )
								}
								help={ __(
									'Placeholder text shown inside the field.',
									'gatherpress',
								) }
							/>
							<NumberControl
								label={
									'number' === fieldType
										? __( 'Minimum Value', 'gatherpress' )
										: __( 'Minimum Length', 'gatherpress' )
								}
								value={ minValue }
								onChange={ ( value ) =>
									setAttributes( {
										minValue:
											'' === value
												? undefined
												: parseInt( value, 10 ),
									} )
								}
								min={ 0 }
								help={
									'number' === fieldType
										? __(
											'Minimum allowed value for this number field.',
											'gatherpress',
										)
										: __(
											'Minimum number of characters required.',
											'gatherpress',
										)
								}
							/>
							<NumberControl
								label={
									'number' === fieldType
										? __( 'Maximum Value', 'gatherpress' )
										: __( 'Maximum Length', 'gatherpress' )
								}
								value={ maxValue }
								onChange={ ( value ) =>
									setAttributes( {
										maxValue:
											'' === value
												? undefined
												: parseInt( value, 10 ),
									} )
								}
								min={ 0 }
								help={
									'number' === fieldType
										? __(
											'Maximum allowed value for this number field.',
											'gatherpress',
										)
										: __(
											'Maximum number of characters allowed.',
											'gatherpress',
										)
								}
							/>
						</>
					) }

					{ ! [ 'hidden' ].includes( fieldType ) && (
						<TextControl
							label={ __( 'Autocomplete', 'gatherpress' ) }
							value={ autocomplete }
							onChange={ ( value ) => {
								// Only allow lowercase alphanumeric, underscore, hyphen, space.
								const sanitized = value.replaceAll(
									/[^a-z0-9_\s-]/g,
									'',
								);

								setAttributes( { autocomplete: sanitized } );
							} }
							help={
								<>
									{ __(
										'Controls browser autocomplete behavior. Use "on", "off", or specific values like "email", "name", etc.',
										'gatherpress',
									) }
									<br />
									<a
										href="https://developer.mozilla.org/en-US/docs/Web/HTML/Attributes/autocomplete"
										target="_blank"
										rel="noopener noreferrer"
									>
										{ __( 'Learn more', 'gatherpress' ) }
									</a>
								</>
							}
						/>
					) }
				</PanelBody>
				{ getFieldPanels() }
			</InspectorControls>
		</>
	);
}
