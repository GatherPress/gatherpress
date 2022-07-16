import { __ } from '@wordpress/i18n';
import { registerBlockType } from '@wordpress/blocks';
import Edit from './edit';

registerBlockType( 'gatherpress/upcoming-events', {
	apiVersion: 2,
	title: __( 'Upcoming Events', 'gatherpress' ),
	icon: 'groups',
	category: 'gatherpress',
	attributes: {
		blockId: {
			type: 'string',
		},
		maxNumberOfEvents: {
			type: 'string',
			default: '5'
		},
		type: {
			type: 'string',
			default: 'upcoming'
		}
	},
	edit: Edit,
	save: () => null
});
