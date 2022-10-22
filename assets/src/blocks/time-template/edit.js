
import { __ } from '@wordpress/i18n';

import {
	InnerBlocks,
	InspectorControls,
	useBlockProps
} from '@wordpress/block-editor';

import {
	PanelBody,
	PanelRow
} from '@wordpress/components';

import './editor.scss';

export default function Edit() {
	const blockProps = useBlockProps();

	const TIME_TEMPLATE = [
		['core/columns', {},
			[
				['core/column', {},
					[
						['gatherpress/initial-time', {}]
					]
				],
				['core/column', {},
					[
						['gatherpress/end-time', {}]
					]
				],
			]
		]
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
					template={TIME_TEMPLATE}
				/>
			</div>
		</>
	);
}
