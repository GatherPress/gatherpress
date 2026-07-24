/**
 * External dependencies
 */
import 'leaflet/dist/leaflet.css';
// eslint-disable-next-line import/no-extraneous-dependencies
import 'leaflet-gesture-handling/dist/leaflet-gesture-handling.css';

/**
 * WordPress dependencies
 */
import { registerBlockType } from '@wordpress/blocks';

/**
 * Internal dependencies
 */
import edit from './edit';
import metadata from './block.json';
import './style.scss';

/**
 * Register the Venue Map block.
 *
 * @since 0.33.0
 */
registerBlockType( metadata.name, {
	edit,
} );
