/**
 * WordPress dependencies.
 */
import { __ } from '@wordpress/i18n';
import { registerBlockType } from '@wordpress/blocks';

/**
 * Internal dependencies.
 */
import edit from './edit';

registerBlockType( 'gatherpress/venue', {
	apiVersion: 2,
	title: __( 'Venue', 'gatherpress' ),
	icon: 'location',
	category: 'gatherpress',
	attributes: {
		blockId: { type: 'string' },
		venueId: {
			type: 'integer',
			default: null,
		},
	},
	edit,
	save: () => null,
} );
