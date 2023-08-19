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
import { create } from 'lodash';

const EventSettings = () => {
	const [hasOnlineBlock, setHasOnlineBlock] = useState(false);
	const { editPost } = useDispatch('core/editor');
	const { removeBlock } = useDispatch('core/block-editor');
	const { insertBlock } = useDispatch( 'core/editor' );
	/**
	 * Need to use logic similar to this to detect the existance of a block. Grab the client and and UseEffect to detect the change
	 * https://github.com/MeredithCorp/onecms-block-editor/blob/160b5a4990749c1d8e909ee6252fc4bd52a09329/app/block-editor/extensions/universal-taxonomy/index.js#L224
	 */
	const allVenues = useSelect((select) => {
		return select('core').getEntityRecords('taxonomy', '_gp_venue', {
			per_page: -1,
			context: 'view',
		});
	}, []);
	const venueTermId = useSelect((select) =>
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

	const result = blocks.filter(
		(block) => block.name === 'gatherpress/online-event'
	);

	/** */
	const onlineBlock = blocks.filter(
		(block) => (block.name === 'gatherpress/online-event')
	);
	let onlineClentId;
	if ( onlineBlock.length > 0 ) {
		onlineClentId = onlineBlock[0].clientId;
	}

	/** */
	useEffect(() => {
		if (result.length > 0 && onlineId) {
			setHasOnlineBlock(true);
			editPost({ _gp_venue: [onlineId] });
		} else {
			setHasOnlineBlock(false);
			if ( venueTermId.includes(12) ) {
				editPost({ _gp_venue: [] })
			}
		}
	}, [result]);

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
								removeBlock(onlineClentId);
							} else {
								const newBlock = createBlock('gatherpress/online-event');
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
