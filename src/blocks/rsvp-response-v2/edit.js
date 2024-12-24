/**
 * WordPress dependencies.
 */
import { __ } from '@wordpress/i18n';
import {
	BlockControls,
	InnerBlocks,
	useBlockProps,
	InspectorControls,
	RichText,
} from '@wordpress/block-editor';
import {
	PanelBody,
	ToggleControl,
	ToolbarButton,
	ToolbarGroup,
} from '@wordpress/components';
import { useState } from '@wordpress/element';

/**
 * Internal dependencies.
 */
import RsvpManager from './rsvp-manager';

/**
 * Edit component for the GatherPress RSVP block.
 *
 * This component defines the edit interface for the GatherPress RSVP block in the block editor.
 * It renders an Inspector Controls panel for additional settings and a structured layout using
 * `InnerBlocks` with a predefined template. The block is configured to support dynamic content
 * like RSVP templates displayed within a grid layout.
 *
 * @param {Object}   root0               - The props object passed to the component.
 * @param {Object}   root0.attributes    - The block attributes.
 * @param {Function} root0.setAttributes - Function to update block attributes.
 * @since 1.0.0
 *
 * @return {JSX.Element} The rendered edit interface for the block.
 */
const Edit = ({ attributes, setAttributes }) => {
	const blockProps = useBlockProps();
	const [editMode, setEditMode] = useState(false);
	const onEditClick = (e) => {
		e.preventDefault();
		setEditMode(!editMode);
	};
	const [defaultStatus, setDefaultStatus] = useState('attending');
	const [showEmptyRsvpMessage, setShowEmptyRsvpMessage] = useState(false);
	const { emptyRsvpMessage } = attributes;

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
		<div {...blockProps}>
			<InspectorControls>
				<PanelBody>
					<ToggleControl
						label={__(
							'Show Empty RSVP Message Editor',
							'gatherpress'
						)}
						checked={showEmptyRsvpMessage}
						onChange={(value) => setShowEmptyRsvpMessage(value)}
						help={__(
							'Toggle to show or hide the editor for the empty RSVP message.',
							'gatherpress'
						)}
					/>
				</PanelBody>
			</InspectorControls>
			{!showEmptyRsvpMessage && (
				<>
					<BlockControls>
						<ToolbarGroup>
							<ToolbarButton
								label={__('Edit', 'gatherpress')}
								text={
									editMode
										? __('Preview', 'gatherpress')
										: __('Edit', 'gatherpress')
								}
								onClick={onEditClick}
							/>
						</ToolbarGroup>
					</BlockControls>
					{editMode && (
						<RsvpManager
							defaultStatus={defaultStatus}
							setDefaultStatus={setDefaultStatus}
						/>
					)}
					{!editMode && <InnerBlocks template={TEMPLATE} />}
				</>
			)}
			{showEmptyRsvpMessage && (
				<RichText
					tagName="p" // The HTML tag for the RichText content
					value={emptyRsvpMessage}
					onChange={(value) =>
						setAttributes({ emptyRsvpMessage: value })
					}
					placeholder={__(
						"No one has RSVP'd yet. Be the first!",
						'gatherpress'
					)}
					allowedFormats={['core/bold', 'core/italic', 'core/link']}
				/>
			)}
		</div>
	);
};
export default Edit;
