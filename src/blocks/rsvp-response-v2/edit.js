/**
 * WordPress dependencies.
 */
import {
	InnerBlocks,
	InspectorControls,
	useBlockProps,
} from '@wordpress/block-editor';
import { PanelBody, SelectControl, TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies.
 */
import Rsvp from '../../components/Rsvp';
import { getFromGlobal } from '../../helpers/globals';
import EditCover from '../../components/EditCover';
import { useEffect, useState } from '@wordpress/element';
import { useDispatch, useSelect, dispatch, select } from '@wordpress/data';

/**
 * Edit component for the GatherPress RSVP block.
 *
 * This component renders the edit view of the GatherPress RSVP block.
 * It provides an interface for users to respond to the RSVP for the associated event.
 * The component includes the RSVP component and passes the event ID, current user,
 * and type of RSVP as props.
 *
 * @param  root0
 * @param  root0.attributes
 * @param  root0.setAttributes
 * @since 1.0.0
 *
 * @return {JSX.Element} The rendered React component.
 */
const Edit = ({ attributes, setAttributes }) => {
	const blockProps = useBlockProps();
	// const generateTemplate = (users) => {
	// 	return [
	// 		[
	// 			'core/group',
	// 			{
	// 				layout: {
	// 					type: 'grid',
	// 					columns: 3,
	// 					justifyContent: 'center',
	// 					alignContent: 'space-around',
	// 				},
	// 				className: 'custom-grid-group',
	// 			},
	// 			users.map((user) => [
	// 				'core/group',
	// 				{ className: 'custom-grid-item' },
	// 				[
	// 					[
	// 						'core/image',
	// 						{
	// 							url: user.photo,
	// 							linkDestination: 'custom',
	// 							className: 'rounded-image is-style-rounded',
	// 							href: user.profile,
	// 						},
	// 					],
	// 					['core/paragraph', { content: user.name }],
	// 				],
	// 			]),
	// 		],
	// 	];
	// };

	const TEMPLATE = [
		[
			'core/group',
			{
				className: 'rsvp-grid',
				layout: {
					type: 'grid',
					columns: 3,
					justifyContent: 'center',
					alignContent: 'space-around',
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
