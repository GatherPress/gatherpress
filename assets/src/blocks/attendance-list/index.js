import { __ } from '@wordpress/i18n';
import { registerBlockType } from '@wordpress/blocks';
import Edit from './edit';

registerBlockType( 'gatherpress/attendance-list', {
	apiVersion: 2,
	title: __( 'Attendance List', 'gatherpress' ),
	icon: 'groups',
	category: 'gatherpress',
	attributes: {
		blockId: { type: 'string' },
		content: { type: 'string' },
		color: { type: 'string' }
	},
	edit: Edit,
	save: () => null
});
