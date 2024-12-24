/**
 * WordPress dependencies.
 */
import { __ } from '@wordpress/i18n';
import {
	useBlockProps,
	InnerBlocks,
	InspectorControls,
} from '@wordpress/block-editor';
import { PanelBody, ButtonGroup, Button } from '@wordpress/components';
import { useState, useEffect } from '@wordpress/element';
import { select, dispatch, subscribe } from '@wordpress/data';
import TEMPLATE from './template';

const Edit = ({ clientId }) => {
	const blockProps = useBlockProps();
	const [activeModalId, setActiveModalId] = useState(null);
	const innerBlocks = select('core/block-editor').getBlocks(clientId);
	const handleModalSelect = (modalId) => {
		if (activeModalId === modalId) {
			setActiveModalId(null);
			dispatch('core/block-editor').clearSelectedBlock();
		} else {
			setActiveModalId(modalId);
			dispatch('core/block-editor').selectBlock(modalId);
		}
	};

	useEffect(() => {
		const unsubscribe = subscribe(() => {
			const selectedBlockId =
				select('core/block-editor').getSelectedBlockClientId();

			if (activeModalId && selectedBlockId !== activeModalId) {
				setActiveModalId(null);
			}
		});

		return () => unsubscribe();
	}, [activeModalId]);

	return (
		<>
			<InspectorControls>
				<PanelBody title={__('Open a Modal', 'gatherpress')}>
					<ButtonGroup style={{ display: 'flex', gap: '0.5rem' }}>
						{innerBlocks.map((block) => {
							if ('gatherpress/modal' === block.name) {
								const modalName =
									block?.attributes?.metadata?.name ||
									__('Modal', 'gatherpress');

								return (
									<Button
										key={block.clientId}
										variant="secondary"
										onClick={() =>
											handleModalSelect(block.clientId)
										}
									>
										{modalName}
									</Button>
								);
							}
							return null;
						})}
					</ButtonGroup>
				</PanelBody>
			</InspectorControls>
			<div {...blockProps}>
				<InnerBlocks template={TEMPLATE} />
			</div>
		</>
	);
};

export default Edit;
