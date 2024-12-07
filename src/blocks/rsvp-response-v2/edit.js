/**
 * WordPress dependencies.
 */
import {
	InnerBlocks,
	InspectorControls,
	useBlockProps,
} from '@wordpress/block-editor';
import { PanelBody } from '@wordpress/components';

/**
 * Edit component for the GatherPress RSVP block.
 *
 * This component defines the edit interface for the GatherPress RSVP block in the block editor.
 * It renders an Inspector Controls panel for additional settings and a structured layout using
 * `InnerBlocks` with a predefined template. The block is configured to support dynamic content
 * like RSVP templates displayed within a grid layout.
 *
 * @since 1.0.0
 *
 * @return {JSX.Element} The rendered edit interface for the block.
 */
const Edit = () => {
	const blockProps = useBlockProps();

	const TEMPLATE = [
		[
			'core/group',
			{
				layout: {
					type: 'grid',
					columns: 3,
					justifyContent: 'center',
					alignContent: 'space-around',
					minimumColumnWidth: '8rem',
				},
			},
			[['gatherpress/rsvp-template', {}]],
		],
	];

	return (
		<>
			<InspectorControls>
				<PanelBody></PanelBody>
			</InspectorControls>
			<div {...blockProps}>
				<InnerBlocks template={TEMPLATE} />
			</div>
		</>
	);
};
export default Edit;
