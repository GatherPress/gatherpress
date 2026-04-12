/**
 * WordPress dependencies.
 */
import {
	useBlockProps,
	InnerBlocks,
	InspectorControls,
} from '@wordpress/block-editor';
import { PanelBody, RangeControl } from '@wordpress/components';

const Edit = ( { attributes, setAttributes } ) => {
	const { style = {} } = attributes;
	const { dimensions = {}, color = {} } = style;
	const maxWidth = parseInt( dimensions.maxWidth || '400px', 10 );

	const blockProps = useBlockProps( {
		style: {
			...color,
			...dimensions,
		},
	} );
	const TEMPLATE = [ [ 'core/paragraph', {} ] ];

	return (
		<>
			<InspectorControls>
				<PanelBody title="Width Settings">
					<RangeControl
						label="Max Width"
						value={ maxWidth }
						onChange={ ( value ) =>
							setAttributes( {
								style: {
									...style,
									dimensions: {
										...dimensions,
										maxWidth: `${ value }px`,
									},
								},
							} )
						}
						min={ 300 }
						max={ 900 }
					/>
				</PanelBody>
			</InspectorControls>
			<div { ...blockProps }>
				<InnerBlocks template={ TEMPLATE } />
			</div>
		</>
	);
};
export default Edit;
