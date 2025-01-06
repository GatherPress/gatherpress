/**
 * WordPress dependencies.
 */
import { __ } from '@wordpress/i18n';
import {
	BlockControls,
	InnerBlocks,
	useBlockProps,
	InspectorControls,
} from '@wordpress/block-editor';
import {
	PanelBody,
	ToggleControl,
	ToolbarButton,
	ToolbarGroup,
	Spinner,
} from '@wordpress/components';
import { useState, useEffect } from '@wordpress/element';
import { useSelect, useDispatch } from '@wordpress/data';
import { BlockContextProvider } from '@wordpress/block-editor';

/**
 * Internal dependencies.
 */
import RsvpManager from './rsvp-manager';
import TEMPLATE from './template';
import { getFromGlobal } from '../../helpers/globals';

/**
 * Fetch RSVP responses from the API.
 *
 * @param {number} postId The post ID for which to fetch RSVP responses.
 * @returns {Promise<Object>} The RSVP responses data.
 */
async function fetchRsvpResponses(postId) {
	const apiUrl = getFromGlobal('urls.eventApiUrl');
	const response = await fetch(`${apiUrl}/rsvp-responses?post_id=${postId}`);

	if (!response.ok) {
		throw new Error('Failed to fetch RSVP responses');
	}

	return response.json();
}

/**
 * Edit component for the GatherPress RSVP Response block.
 *
 * @param {Object} root0          - The props object passed to the component.
 * @param {string} root0.clientId - The block client ID.
 * @since 1.0.0
 *
 * @return {JSX.Element} The rendered edit interface for the block.
 */
const Edit = ({ clientId, context }) => {
	const blockProps = useBlockProps();
	const [editMode, setEditMode] = useState(false);
	const [showEmptyRsvpMessage, setShowEmptyRsvpMessage] = useState(false);
	const [defaultStatus, setDefaultStatus] = useState('attending');
	const [responses, setResponses] = useState(null);
	const [loading, setLoading] = useState(true);
	const [error, setError] = useState(null);
	const postId = context?.postId ?? null;
	const { updateBlockAttributes } = useDispatch('core/block-editor');
	const innerBlocks = useSelect(
		(select) => select('core/block-editor').getBlocks(clientId),
		[clientId]
	);

	useEffect(() => {
		innerBlocks.forEach((block) => {
			const blockElement = global.document.getElementById(
				`block-${block.clientId}`
			);

			if (blockElement) {
				const isRsvpResponsesBlock =
					block.attributes?.className?.includes(
						'gatherpress--rsvp-responses'
					);
				const isEmptyRsvpBlock = block.attributes?.className?.includes(
					'gatherpress--empty-rsvp'
				);

				if (showEmptyRsvpMessage && isEmptyRsvpBlock) {
					blockElement.style.display = '';
				} else if (showEmptyRsvpMessage && isRsvpResponsesBlock) {
					blockElement.style.display = 'none';
				} else if (!showEmptyRsvpMessage && isEmptyRsvpBlock) {
					blockElement.style.display = 'none';
				} else if (!showEmptyRsvpMessage && isRsvpResponsesBlock) {
					blockElement.style.display = '';
				}
			}
		});
	}, [showEmptyRsvpMessage, innerBlocks, editMode]);

	// Fetch responses when postId changes.
	useEffect(() => {
		if (!postId) {
			setResponses(null);
			setLoading(false);
			return;
		}

		setLoading(true);
		setError(null);

		fetchRsvpResponses(postId)
			.then((response) => {
				// console.log('Fetched RSVP Responses:', response.data); // Log the responses for testing
				setResponses(response.data);
				setLoading(false);
			})
			.catch((err) => {
				setError(err.message);
				setLoading(false);
			});
	}, [postId]);

	const onEditClick = (e) => {
		e.preventDefault();
		setEditMode(!editMode);
	};

	if (loading) {
		return (
			<div {...blockProps}>
				<Spinner />
			</div>
		);
	}

	if (error) {
		return (
			<div {...blockProps}>
				<p>{__('Failed to load RSVP responses.', 'gatherpress')}</p>
			</div>
		);
	}

	return (
		<div {...blockProps}>
			<BlockContextProvider value={{ 'gatherpress/rsvpResponses': responses }}>
				<InspectorControls>
					<PanelBody>
						<ToggleControl
							label={__('Show Empty RSVP Block', 'gatherpress')}
							checked={showEmptyRsvpMessage}
							onChange={(value) => setShowEmptyRsvpMessage(value)}
							help={__(
								'Toggle to show or hide the Empty RSVP block.',
								'gatherpress'
							)}
						/>
					</PanelBody>
				</InspectorControls>
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
			</BlockContextProvider>
		</div>
	);
};

export default Edit;
