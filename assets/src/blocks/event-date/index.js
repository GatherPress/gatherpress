/**
 * WordPress dependencies.
 */
import { __ } from '@wordpress/i18n';
import { registerBlockType } from '@wordpress/blocks';

/**
 * Internal dependencies.
 */
import edit from './edit';

registerBlockType( 'gatherpress/event-date', {
	apiVersion: 2,
	title: __( 'Event Date', 'gatherpress' ),
	icon: 'clock',
	example: {},
	category: 'gatherpress',
	attributes: {
		blockId: { type: 'string' },
	},
	edit,
	save: () => null,
} );
