/**
 * WordPress dependencies.
 */
import { __ } from '@wordpress/i18n';
import { registerBlockType } from '@wordpress/blocks';

/**
 * Internal dependencies.
 */
import edit from './edit';

registerBlockType( 'gatherpress/add-to-calendar', {
	apiVersion: 2,
	title: __( 'Add to calendar', 'gatherpress' ),
	icon: 'calendar',
	category: 'gatherpress',
	attributes: {
		blockId: { type: 'string' },
	},
	edit,
	save: () => null,
} );
