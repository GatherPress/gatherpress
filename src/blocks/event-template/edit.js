
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

    const EVENT_TEMPLATE = [
        [ 'core/heading', { content: 'Event Title' } ],
		['core/paragraph', { content: 'Event Summary -- this is from the GP JS block template called `event-template`' }],
		['core/columns', {},
			[
				['core/column', {
				},
					[
						['core/paragraph', {
							content: 'Event Start'
						}]
					],
				],
				['core/column', {
				},
					[
						['gatherpress-event/event-start', {}]
					],
				]
			]
		],
		['core/columns', {},
			[
				['core/column', {
				},
					[
						['core/paragraph', {
							content: 'Event End'
						}]
					],
				],
				['core/column', {
				},
					[
						['gatherpress-event/event-end', {}]
					],
				]
			]
		],
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
                    template={ EVENT_TEMPLATE }
                    templateLock="all"
                />
            </div>
        </>
	);
}
