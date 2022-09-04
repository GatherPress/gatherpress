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
		descriptionLimit: {
			type: 'string',
			default: '24',
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
		showAttendeeList: {
			type: 'boolean',
			default: true,
		},
		showFeaturedImage: {
			type: 'boolean',
			default: true,
		},
		showDescription: {
			type: 'boolean',
			default: true,
		},
		showRsvpButton: {
			type: 'boolean',
			default: true,
		},
	},
	edit: Edit,
	save: () => null,
} );
