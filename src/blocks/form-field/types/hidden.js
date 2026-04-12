/**
 * WordPress dependencies.
 */
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies.
 */
import { getWrapperClasses } from '../helpers';

/**
 * Renders a hidden field component with visual indicator for the block editor.
 *
 * @param {Object} props            - Component props.
 * @param {Object} props.attributes - Block attributes object.
 * @param {Object} props.blockProps - WordPress block wrapper properties.
 * @return {JSX.Element} The hidden field component with visual placeholder.
 */
export default function HiddenField( { attributes, blockProps } ) {
	const { fieldType, fieldName, fieldValue } = attributes;

	return (
		<div
			{ ...blockProps }
			className={ getWrapperClasses( fieldType, blockProps ) }
		>
			<div
				className="gatherpress-hidden-field-indicator"
				style={ {
					padding: '12px',
					border: '2px dashed #ccc',
					borderRadius: '4px',
					textAlign: 'center',
					opacity: 0.7,
					fontSize: '14px',
					color: '#666',
				} }
			>
				<span
					className="dashicons dashicons-hidden"
					style={ { marginRight: '8px' } }
				></span>
				{ __( 'Hidden Field', 'gatherpress' ) }
				{ fieldName && `: ${ fieldName }` }
				{ fieldValue && (
					<div style={ { fontSize: '12px', marginTop: '4px' } }>
						{ __( 'Value:', 'gatherpress' ) } { fieldValue }
					</div>
				) }
			</div>
		</div>
	);
}
