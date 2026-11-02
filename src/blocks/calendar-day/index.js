/**
 * Calendar Day Block Registration
 *
 * @package
 * @since TBD
 */

import { registerBlockType } from '@wordpress/blocks';

/**
 * Internal dependencies
 */
import metadata from './block.json';
import Edit from './edit';
import save from './save';
import './style.scss';

// The gatherpress/calendar-day binding source, which reads the Date Format, is in
// calendar/bindings.js. A second registration here would win, and ignore the format.
registerBlockType( metadata.name, {
	edit: Edit,
	save,
} );
