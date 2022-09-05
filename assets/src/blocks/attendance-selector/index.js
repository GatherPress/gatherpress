import { __ } from '@wordpress/i18n';
import { registerBlockType } from '@wordpress/blocks';
import edit from './edit';

registerBlockType('gatherpress/attendance-selector', {
	apiVersion: 2,
	title: __('Attendance Selector', 'gatherpress'),
	icon: 'groups',
	example: {},
	category: 'gatherpress',
	attributes: {
		content: { type: 'string' },
		color: { type: 'string' },
	},
	edit,
	save: () => null,
});
