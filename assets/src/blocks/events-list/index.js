/**
 * External dependencies.
 */
import { __ } from '@wordpress/i18n';
import { registerBlockType } from '@wordpress/blocks';
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
		eventOptions: {
			type: 'object',
			default: {
				descriptionLimit: '55',
				imageSize: 'default',
				showAttendeeList: true,
				showFeaturedImage: true,
				showDescription: true,
				showRsvpButton: true
			}
		},
		maxNumberOfEvents: {
			type: 'integer',
			default: '5',
		},
		topics: {
			type: 'array',
			items: {
				type: 'object',
			},
		},
		type: {
			type: 'string',
			default: 'upcoming',
		},
	},
	edit: Edit,
	save: () => null,
} );
