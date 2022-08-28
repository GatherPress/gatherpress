import { __ } from '@wordpress/i18n';
import { registerBlockType } from '@wordpress/blocks';
import edit from './edit';

registerBlockType( 'gatherpress/venue-information', {
	apiVersion: 2,
	title: __( 'Venue Information', 'gatherpress' ),
	icon: 'groups',
	category: 'gatherpress',
	attributes: {
		blockId: { type: 'string' },
		fullAddress: { type: 'string' },
		phoneNumber: { type: 'string' },
		website: { type: 'string' },
	},
	edit,
	save: () => null,
} );
