/**
 * WordPress dependencies.
 */
import { registerBlockType, registerBlockVariation } from '@wordpress/blocks';
import { addFilter } from '@wordpress/hooks';
/**
 * Internal dependencies.
 */
import edit from './edit';
import metadata from './block.json';
import './style.scss';
import save from './save';

/**
 * Edit component for the GatherPress RSVP block.
 *
 * This component renders the edit view of the GatherPress RSVP block.
 * It provides an interface for users to RSVP to an event.
 *
 * @since 1.0.0
 *
 * @return {JSX.Element} The rendered React component.
 */
registerBlockType(metadata, {
	edit,
	save,
});

registerBlockVariation(
	'core/buttons',
	{
		name: 'event-button',
		title: 'Event Button',
		innerBlocks: [
			{
				name: 'core/button',
				attributes: {
					text: 'Edit RSVP',
					tagName: 'button',
				},
			},
		]
	}
)

registerBlockVariation(
	'core/columns',
	{
		name: 'event-test',
		title: 'Event Test',
		attributes: {
			templateLock: 'contentOnly',
			align: 'center',
		},
		innerBlocks: [
			['core/column', {}, [
				['core/button', { text: 'Edit RSVP', tagName: 'button', title: 'foobar', modalId: '123' }],
			]],
			['core/column', {}, [
				['core/paragraph', { text: 'Attending' }],
			]],
		]
	}
);
