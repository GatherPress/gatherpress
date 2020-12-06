import { __ } from '@wordpress/i18n';
import { registerBlockType } from '@wordpress/blocks';
import Edit from './edit';

registerBlockType( 'gatherpress/upcoming-events', {
	title: __( 'Upcoming Events', 'gatherpress' ),
	icon: 'groups',
	category: 'gatherpress',
	attributes: {
		maxNumberOfEvents: {
			type: 'string',
			default: '5'
		},
	},
	edit: Edit,
	save: () => null
});
