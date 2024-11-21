/**
 * WordPress dependencies.
 */
import { InnerBlocks, InspectorControls, useBlockProps } from '@wordpress/block-editor';
import {PanelBody, TextControl} from '@wordpress/components';
import { __ } from '@wordpress/i18n';
/**
 * Internal dependencies.
 */
import Rsvp from '../../components/Rsvp';
import { getFromGlobal } from '../../helpers/globals';
import EditCover from '../../components/EditCover';
import {useEffect, useState} from '@wordpress/element';
import {useDispatch, useSelect, dispatch, select } from '@wordpress/data';

/**
 * Edit component for the GatherPress RSVP block.
 *
 * This component renders the edit view of the GatherPress RSVP block.
 * It provides an interface for users to respond to the RSVP for the associated event.
 * The component includes the RSVP component and passes the event ID, current user,
 * and type of RSVP as props.
 *
 * @since 1.0.0
 *
 * @return {JSX.Element} The rendered React component.
 */
const Edit = () => {
	const blockProps = useBlockProps();
	const status = 'Attending';
	const [ initialLabel, setInitialLabel ] = useState( __('RSVP', 'gatherpress'));
	const [ interactedLabel, setInteractedLabel ] = useState( __('Edit RSVP', 'gatherpress'));


	// Get clientId of the current block to target the InnerBlocks within it
	const clientId = useSelect((select) =>
		select('core/block-editor').getSelectedBlock()?.clientId, []
	);

	// Get InnerBlocks within the 'gatherpress/rsvp-v2' block
	const innerBlocks = useSelect((select) =>
		clientId ? select('core/block-editor').getBlocks(clientId) : [],
	[clientId]);

	// Helper function to recursively search for the core/button block
	const findButtonBlock = (blocks) => {
		for (const block of blocks) {
			if (block.name === 'core/button') {
				return block;
			}
			if (block.innerBlocks.length > 0) {
				const found = findButtonBlock(block.innerBlocks);
				if (found) return found;
			}
		}
		return null;
	};

	useEffect(() => {
		// Find the `core/button` block within the nested InnerBlocks
		const buttonBlock = findButtonBlock(innerBlocks);

		if (buttonBlock) {
			// Update `text` attribute of the `core/button` block whenever `initialLabel` changes
			dispatch('core/block-editor').updateBlockAttributes(buttonBlock.clientId, {
				text: initialLabel,
			});
		}

	}, [initialLabel, innerBlocks]);

	// Use `useSelect` to get the currently selected block.
	const selectedBlock = useSelect((select) => {
		return select('core/block-editor').getSelectedBlock();
	}, []); // Empty dependency array to call it once on mount

	useEffect(() => {
		// Ensure that a block is selected and it is the one you want to target
		if (
			selectedBlock &&
			selectedBlock.name === 'core/button' &&
			selectedBlock.attributes?.className.includes('gatherpress-rsvp-v2')
		) {
			console.log(selectedBlock.attributes);
			console.log('Selected block is core/button');
			// Perform any actions related to the selected block here
		}
	}, [selectedBlock]); // Re-run the effect when the selected block changes

	const TEMPLATE = [
		[
			'core/buttons',
			{ align: 'center', layout: { type: 'flex', justifyContent: 'center' } },
			[
				[
					'core/button',
					{
						text: initialLabel,
						tagName: 'button',
						className: 'gatherpress-rsvp-v2',
					}
				]
			]
		],
		[
			'gatherpress/modal',
			{ className: 'gatherpress-rsvp-modal' },
			[
				[
					'core/heading',
					{
						level: 3,
						content: __('You\'re attending', 'gatherpress'),
					}
				],
				[
					'core/paragraph',
					{
						content: __('To set or change your attending status, simply click the Not Attending button below.', 'gatherpress'),
					}
				],
				[
					'core/buttons',
					{ align: 'left', layout: { type: 'flex', justifyContent: 'flex-start' } },
					[
						[
							'core/button',
							{
								text: __('Attend', 'gatherpress'),
								className: 'modal-button-1',
							}
						],
						[
							'core/button',
							{
								text: __('Close', 'gatherpress'),
								className: 'modal-button-2',
							}
						]
					]
				]
			]
		]
	];
	return (
		<>
			<InspectorControls>
				<PanelBody>
					<TextControl
						label={ __('Initial Label', 'gatherpress') }
						value={ initialLabel }
						onChange={ ( value ) => setInitialLabel( value ) }
					/>
					<TextControl
						label={ __('Interacted Label', 'gatherpress') }
						value={ interactedLabel }
						onChange={ ( value ) => setInteractedLabel( value ) }
					/>
				</PanelBody>
			</InspectorControls>
			<div {...blockProps}>
				<InnerBlocks
					template={TEMPLATE}
					templateLock="all"
					renderAppender={false}
				/>
			</div>
		</>
	);
};
export default Edit;
