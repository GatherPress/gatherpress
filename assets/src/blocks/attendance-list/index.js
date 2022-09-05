import { __ } from '@wordpress/i18n';
import edit from './edit';

import './style.scss';

import metadata from './block.json';

registerBlockType ( metadata, {
	edit,
	save: () => null,
} );
