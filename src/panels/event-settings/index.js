/**
 * WordPress dependencies.
 */
import { __ } from '@wordpress/i18n';
import { dispatch, useDispatch, useSelect, select } from '@wordpress/data';
import { useEffect, useState } from '@wordpress/element';
import { createBlock } from '@wordpress/blocks';

import {
	SelectControl,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalDivider as Divider,
	// eslint-disable-next-line @wordpress/no-unsafe-wp-apis
	__experimentalVStack as VStack,
} from '@wordpress/components';
import { registerPlugin } from '@wordpress/plugins';
import { PluginDocumentSettingPanel } from '@wordpress/edit-post';

/**
 * Internal dependencies.
 */
import { isEventPostType } from '../../helpers/event';
import DateTimePanel from './datetime';
import VenuePanel from '../../components/VenueSelector';

const EventSettings = () => {
	const [hasOnlineBlock, setHasOnlineBlock] = useState(false);
	const { editPost } = useDispatch('core/editor');
	const { removeBlock } = useDispatch('core/block-editor');
	const { insertBlock } = useDispatch('core/block-editor');
	const allVenues = useSelect(() => {
		return select('core').getEntityRecords('taxonomy', '_gp_venue', {
			per_page: -1,
			context: 'view',
		});
	}, []);
	const venueTermId = useSelect(() =>
		select('core/editor').getEditedPostAttribute('_gp_venue')
	);
	let onlineId;
	if (allVenues) {
		allVenues.map((venue) => {
			if (venue.slug === 'online') {
				onlineId = venue.id;
			}
		});
	}

	const { blocks } = useSelect(() => ({
		blocks: select('core/block-editor').getBlocks(),
	}));
	const currentOnlineEventBlocks = blocks.filter(
		(block) => block.name === 'gatherpress/online-event'
	);
	const onlineBlock = blocks.filter(
		(block) => block.name === 'gatherpress/online-event'
	);
	let onlineClientId;
	if (onlineBlock.length > 0) {
		onlineClientId = onlineBlock[0].clientId;
	}
	const venueBlock = blocks.filter(
		(block) => block.name === 'gatherpress/event-venue'
	);
	let venueClientId;
	if (venueBlock.length > 0) {
		venueClientId = venueBlock[0].clientId;
	}

	useEffect(() => {
		if (currentOnlineEventBlocks.length > 0 && onlineId) {
			setHasOnlineBlock(true);
			editPost({ _gp_venue: [onlineId] });
			removeBlock(venueClientId);
		} else {
			setHasOnlineBlock(false);
			if (venueTermId.includes(12)) {
				editPost({ _gp_venue: [] })
			}
		}
	}, [currentOnlineEventBlocks]);

	return (
		isEventPostType() && (
			<PluginDocumentSettingPanel
				name="gp-event-settings"
				title={__('Event settings', 'gatherpress')}
				initialOpen={true}
				className="gp-event-settings"
				icon="nametag"
			>
				<VStack spacing={2}>
					<DateTimePanel />
					<Divider />
					{!hasOnlineBlock && <VenuePanel />}
				</VStack>

				<div>
					<SelectControl
						label={__('Online Event', 'gatherpress')}
						value={hasOnlineBlock}
						onChange={(newValue) => {
							if (newValue === 'false') {
								removeBlock(onlineClientId);
							} else {
								const newBlock = createBlock(
									'gatherpress/online-event'
								);
								insertBlock(newBlock);
							}
						}}
						options={[
							{ label: 'Yes', value: true },
							{ label: 'No', value: false },
						]}
					/>
				</div>
			</PluginDocumentSettingPanel>
		)
	);
};

registerPlugin('gp-event-settings', {
	render: EventSettings,
});

dispatch('core/edit-post').toggleEditorPanelOpened(
	'gp-event-settings/gp-event-settings'
);
