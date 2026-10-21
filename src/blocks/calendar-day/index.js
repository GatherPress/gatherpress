/**
 * GatherPress Calendar Day Block Registration
 *
 * @package
 * @since TBD
 */

import {
	registerBlockType,
	registerBlockBindingsSource,
} from '@wordpress/blocks';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import metadata from './block.json';
import Edit from './edit';
import save from './save';
import './style.scss';

registerBlockType( metadata.name, {
	edit: Edit,
	save,
} );

registerBlockBindingsSource( {
	name: 'gatherpress/calendar-day',
	label: __( 'Calendar Day Number', 'gatherpress' ),
	usesContext: [ 'gatherpress/dayNumber', 'gatherpress/isEmpty' ],
	getValues( { context } ) {
		if ( context?.[ 'gatherpress/isEmpty' ] ) {
			return { content: '' };
		}
		const dayNumber = context?.[ 'gatherpress/dayNumber' ];
		return {
			content: dayNumber ? String( dayNumber ) : '1',
		};
	},
} );
