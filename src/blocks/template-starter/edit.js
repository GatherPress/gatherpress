
import { __ } from '@wordpress/i18n';

import {
    InnerBlocks,
	InspectorControls,
	MediaUploadCheck,
	RichText,
	useBlockProps
} from '@wordpress/block-editor';

import {
	PanelBody,
	PanelRow
} from '@wordpress/components';

import './editor.scss';

export default function Edit({ attributes, setAttributes }) {
	const blockProps = useBlockProps();

    const SAMPLE_TEMPLATE = [
        [ 'core/heading', { placeholder: 'Mission Title' } ],
        [ 'core/image', {} ],
        [ 'core/paragraph', { placeholder: 'Mission Summary' } ],
    ];

 	return (
        <>
			<InspectorControls>
				<PanelBody
					title={blockProps['data-title']}
					initialOpen={true}
				>
					<PanelRow>
						<h3>Why useBlockProps:</h3>
					</PanelRow>
					<PanelRow>
						<label>id:</label> {blockProps.id}
					</PanelRow>
					<PanelRow>
						<label>className:</label> {blockProps.className}
					</PanelRow>
					<PanelRow>
						<label>aria-label:</label> {blockProps['aria-label']}
					</PanelRow>
					<PanelRow>
						<label>data-block:</label> {blockProps['data-block']}
					</PanelRow>
					<PanelRow>
						<label>data-type:</label> {blockProps['data-type']} 
					</PanelRow>
					<PanelRow>
						<label>data-title:</label> {blockProps['data-title']}
					</PanelRow>
				</PanelBody>
			</InspectorControls>
            <div {...blockProps}>
                <InnerBlocks
                    template={ SAMPLE_TEMPLATE }
                    templateLock="all"
                />
            </div>
        </>
	);
}
