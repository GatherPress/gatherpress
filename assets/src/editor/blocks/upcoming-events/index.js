import { __ } from '@wordpress/i18n';
import { registerBlockType } from '@wordpress/blocks';
import Edit from './edit';

registerBlockType( 'gatherpress/upcoming-events', {
	title: __( 'Upcoming Events', 'gatherpress' ),
	icon: 'groups',
	category: 'gatherpress',
	attributes: {
		content: { type: 'string' },
		color: { type: 'string' }
	},
	edit: Edit,
	save: () => null
});
