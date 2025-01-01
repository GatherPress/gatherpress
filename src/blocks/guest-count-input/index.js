/**
 * WordPress dependencies.
 */
import { registerBlockType } from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies.
 */
import edit from './edit';
import metadata from './block.json';
import './style.scss';

/**
 * Edit component for the GatherPress Guest Count Input block.
 *
 * This component renders the edit view of the GatherPress Guest Count Input block.
 *
 * @since 1.0.0
 *
 * @return {JSX.Element} The rendered React component.
 */
registerBlockType(metadata, {
	edit,
	save: () => null,
});
