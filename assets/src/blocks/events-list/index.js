/**
 * External dependencies.
 */
import { __ } from '@wordpress/i18n';
import { registerBlockType, registerBlockStyle } from '@wordpress/blocks';
/**
 * Internal dependencies.
 */
import Edit from './edit';

registerBlockType( 'gatherpress/events-list', {
	apiVersion: 2,
	title: __( 'Events List', 'gatherpress' ),
	icon: 'groups',
	category: 'gatherpress',
	attributes: {
		maxNumberOfEvents: {
			type: 'integer',
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
