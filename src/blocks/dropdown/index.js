/**
 * WordPress dependencies
 */
import { registerBlockType } from '@wordpress/blocks';

/**
 * Internal dependencies
 */
import metadata from './block.json';
import edit from './edit';
import save from './save';
import './style.scss';

/**
 * Register the block
 */
registerBlockType( metadata.name, {
	title: metadata.title,
	category: metadata.category,
	icon: metadata.icon,
	attributes: metadata.attributes,
	edit,
	save,
} );
