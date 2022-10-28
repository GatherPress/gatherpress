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

import '../../panels';

import '../../settings';

// import '../frontend';

registerBlockType( metadata, {
	edit,
	save: () => null,
} );
