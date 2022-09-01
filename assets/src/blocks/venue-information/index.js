/**
 * WordPress dependencies.
 */
import { __ } from '@wordpress/i18n';
import { registerBlockType } from '@wordpress/blocks';

/**
 * Internal dependencies.
 */
import edit from './edit';

registerBlockType( 'gatherpress/venue-information', {
	apiVersion: 2,
	title: __( 'Venue Information', 'gatherpress' ),
	icon: 'groups',
	category: 'gatherpress',
	attributes: {
		blockId: { type: 'string' },
		fullAddress: {
			type: 'string',
			default: '',
		},
		phoneNumber: {
			type: 'string',
			default: '',
		},
		website: {
			type: 'string',
			default: '',
		},
	},
	edit,
	save: () => null,
} );
