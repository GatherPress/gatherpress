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
 * Edit component for the RSVP Response Toggle block.
 *
 * This component renders the edit view of the RSVP Response Toggle block.
 * It manages the block's settings and preview in the WordPress editor.
 *
 * @since 1.0.0
 *
 * @return {JSX.Element} The rendered React component.
 */
registerBlockType( metadata, {
	edit,
	save: () => null,
} );
