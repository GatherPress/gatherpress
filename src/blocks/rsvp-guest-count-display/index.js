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
 * Edit component for the GatherPress RSVP Guest Count Display block.
 *
 * This component renders the edit view of the RSVP GatherPress Guest Count Display block.
 *
 * @since 1.0.0
 *
 * @return {JSX.Element} The rendered React component.
 */
registerBlockType( metadata, {
	edit,
	save: () => null,
} );
