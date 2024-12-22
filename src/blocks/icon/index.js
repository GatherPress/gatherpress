/**
 * WordPress dependencies.
 */
import { registerBlockType } from '@wordpress/blocks';

/**
 * Internal dependencies.
 */
import edit from './edit';
import metadata from './block.json';
import './style.scss';

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
	save: () => null,
});
