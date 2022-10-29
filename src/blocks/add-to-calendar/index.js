/**
 * WordPress dependencies.
 */
import { registerBlockType } from '@wordpress/blocks';

import './plugin-2';
import './maybe-deny-list';

/**
 * Internal dependencies.
 */
import edit from './edit';

import metadata from './block.json';

import './style.scss';

registerBlockType( metadata, {
	edit,
	save: () => null,
} );
